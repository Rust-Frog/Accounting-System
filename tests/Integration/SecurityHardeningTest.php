<?php

declare(strict_types=1);

namespace Tests\Integration;

use Application\Command\Transaction\PostTransactionCommand;
use Application\Handler\Transaction\PostTransactionHandler;
use Infrastructure\Container\ContainerBuilder;
use PDO;

final class SecurityHardeningTest extends BaseIntegrationTestCase
{
    use DatabaseTestHelper;

    private PostTransactionHandler $postHandler;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Boot container and get handler
        $container = ContainerBuilder::build();
        // Since container creates its own PDO, we need to ensure it uses the test transaction if possible,
        // but ContainerBuilder usually creates a new PDO connection. 
        // For integration tests extending BaseIntegrationTestCase, we rely on $this->pdo.
        // However, the handler requires Repositories that require PDO.
        // We can manually construct the handler with our test PDO if the container doesn't facilitate test DB injection easily,
        // OR we just rely on the fact that both connect to the same DB (accounting-mysql-dev).
        // Since we wrap standard tests in a transaction, external connections might hang or not see changes 
        // if isolation level is SERIALIZABLE/REPEATABLE READ.
        // BUT, since we are doing extensive integration, let's try to inject our PDO into the container if possible?
        // ContainerBuilder::build() returns a compiled container usually.
        // Let's just use the container's handler and accept that it might use a separate connection.
        // This means assertions must be done via the SAME connection or we might need to commit.
        // BaseIntegrationTestCase rolls back in tearDown, so we need to be careful.
        // If the container uses a new PDO, it won't see UNCOMMITTED changes from this->pdo if using transactions.
        
        // BETTER APPROACH: creating the Handler usage manually with our Repositories using OUR PDO.
        
        $tRepo = new \Infrastructure\Persistence\Mysql\Repository\MysqlTransactionRepository($this->pdo);
        $aRepo = new \Infrastructure\Persistence\Mysql\Repository\MysqlApprovalRepository($this->pdo);
        $jRepo = new \Infrastructure\Persistence\Mysql\Repository\MysqlJournalEntryRepository($this->pdo);
        $dispatcher = $container->get(\Domain\Shared\Event\EventDispatcherInterface::class); // Events are fine
        
        $this->postHandler = new PostTransactionHandler($tRepo, $aRepo, $jRepo, $dispatcher);
    }

    public function testTransactionPostingGeneratesApprovalProof(): void
    {
        $companyId = \Domain\Shared\ValueObject\Uuid::generate()->toString();
        $transactionId = \Domain\Shared\ValueObject\Uuid::generate()->toString();
        $userId = \Domain\Shared\ValueObject\Uuid::generate()->toString();
        $accountId = \Domain\Shared\ValueObject\Uuid::generate()->toString();

        // 1. Setup Data
        $this->createCompany($this->pdo, $companyId, 'Secure Corp');
        $this->createUser($this->pdo, $userId, $companyId, 'admin_' . uniqid());
        
        // Create Transaction (Wait, DatabaseTestHelper::createTransaction creates a POSTED one?)
        // Let's look at helper. createTransaction(..., status) ?? No it defaults.
        // I should check helper. createTransaction status defaults to POSTED in line 42?
        // Let's manually insert a DRAFT transaction.
        
        $stmt = $this->pdo->prepare("INSERT INTO transactions (
            id, company_id, description, transaction_date, status, created_by, created_at
        ) VALUES (
            :id, :company_id, 'Draft Transaction', NOW(), 'draft', :user, NOW()
        )");
        $stmt->execute([
            'id' => $transactionId,
            'company_id' => $companyId,
            'user' => $userId
        ]);
        
        // Add lines
        $this->createAccount($this->pdo, $accountId, $companyId);
        $this->createTransactionLine($this->pdo, \Domain\Shared\ValueObject\Uuid::generate()->toString(), $transactionId, $accountId, 1000, 'debit');
        $this->createTransactionLine($this->pdo, \Domain\Shared\ValueObject\Uuid::generate()->toString(), $transactionId, $accountId, 1000, 'credit');
        
        // 2. Execute Handler
        $command = new PostTransactionCommand($transactionId, $userId);
        $this->postHandler->handle($command);
        
        // 3. Verify Transaction Status
        $stmt = $this->pdo->prepare("SELECT status, posted_at, posted_by FROM transactions WHERE id = :id");
        $stmt->execute(['id' => $transactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('posted', $row['status']);
        $this->assertEquals($userId, $row['posted_by']);
        $this->assertNotNull($row['posted_at']);
        
        // 4. Verify Approval Creation
        $stmt = $this->pdo->prepare("SELECT * FROM approvals WHERE entity_id = :id AND entity_type = 'transaction'");
        $stmt->execute(['id' => $transactionId]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($approval, 'Approval record should exist');
        $this->assertEquals('approved', $approval['status']);
        // 5. Verify Proof Content
        $proof = json_decode($approval['proof_json'], true);
        $this->assertEquals('transaction', $proof['entity_type']);
        $this->assertEquals($transactionId, $proof['entity_id']);
        $this->assertEquals($userId, $proof['approver_id']);
        $this->assertNotEmpty($proof['entity_hash']);

        // 6. Verify Immutable Ledger Entry
        $stmt = $this->pdo->prepare("SELECT * FROM journal_entries WHERE transaction_id = :id");
        $stmt->execute(['id' => $transactionId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($entry, 'Journal Entry should exist');
        $this->assertEquals('POSTING', $entry['entry_type']);
        $this->assertNotEmpty($entry['content_hash'], 'Content Hash must be present');
        
        if (empty($entry['previous_hash'])) {
            $this->assertNull($entry['chain_hash'], 'Genesis entry should have no chain hash');
        } else {
            $this->assertNotEmpty($entry['chain_hash'], 'Chain Hash must be present');
        }
    }
}
