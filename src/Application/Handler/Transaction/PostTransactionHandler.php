<?php

declare(strict_types=1);

namespace Application\Handler\Transaction;

use Application\Command\CommandInterface;
use Application\Command\Transaction\PostTransactionCommand;
use Application\Dto\Transaction\TransactionDto;
use Application\Dto\Transaction\TransactionLineDto;
use Application\Handler\HandlerInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\EventDispatcherInterface;
use Domain\Shared\Exception\EntityNotFoundException;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Handler for posting a transaction.
 *
 * @implements HandlerInterface<PostTransactionCommand>
 */
final readonly class PostTransactionHandler implements HandlerInterface
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private \Domain\Approval\Repository\ApprovalRepositoryInterface $approvalRepository,
        private \Domain\Ledger\Repository\JournalEntryRepositoryInterface $journalEntryRepository,
        private \Domain\ChartOfAccounts\Repository\AccountRepositoryInterface $accountRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CommandInterface $command): TransactionDto
    {
        assert($command instanceof PostTransactionCommand);

        $transactionId = TransactionId::fromString($command->transactionId);

        // Find transaction
        $transaction = $this->transactionRepository->findById($transactionId);

        if ($transaction === null) {
            throw new EntityNotFoundException("Transaction not found: {$command->transactionId}");
        }

        $userId = UserId::fromString($command->postedBy);

        // 1. Create Approval Proof
        $contentHash = \Domain\Shared\ValueObject\HashChain\ContentHash::fromArray($transaction->toContentArray());
        $proof = \Domain\Shared\ValueObject\Proof\ApprovalProof::create(
            entityType: 'transaction',
            entityId: $transactionId->toString(),
            approvalType: 'posting_authorization', // Custom type for this
            approverId: $userId,
            entityHash: $contentHash,
            notes: 'Automated proof generation during transaction posting'
        );

        // 2. Create Approval Entity (Implicitly approved)
        // We use the request() factory then approve() immediately.
        $approval = \Domain\Approval\Entity\Approval::request(
            new \Domain\Approval\ValueObject\CreateApprovalRequest(
                companyId: $transaction->companyId(),
                approvalType: \Domain\Approval\ValueObject\ApprovalType::TRANSACTION_POSTING,
                entityType: 'transaction',
                entityId: $transactionId->toString(),
                reason: \Domain\Approval\ValueObject\ApprovalReason::transactionPosting($transactionId->toString()),
                requestedBy: $userId,
                amountCents: $transaction->totalDebits()->cents(),
                priority: 1 // High
            )
        );

        $approval->approve($userId, 'Auto-approved via PostTransaction', $proof);
        $this->approvalRepository->save($approval);

        // Post transaction
        $transaction->post($userId);

        // Persist
        $this->transactionRepository->save($transaction);

        // --- IMMUTABLE LEDGER ENTRY ---
        // Get latest chain hash for company
        $previousHash = $this->journalEntryRepository->getLatestHash($transaction->companyId());
        
        // Serialize bookings from transaction lines
        $bookings = [];
        foreach ($transaction->lines() as $line) {
            $bookings[] = [
                'account_id' => $line->accountId()->toString(),
                'type' => $line->lineType()->value,
                'amount' => $line->amount()->cents()
            ];
        }

        // Create Journal Entry
        $journalEntry = \Domain\Ledger\Entity\JournalEntry::create(
            companyId: $transaction->companyId(),
            transactionId: $transaction->id(),
            entryType: 'POSTING',
            bookings: $bookings,
            occurredAt: new \DateTimeImmutable(), // Now
            previousHash: $previousHash
        );

        $this->journalEntryRepository->save($journalEntry);
        // -----------------------------

        // --- UPDATE ACCOUNT BALANCES ---
        foreach ($transaction->lines() as $line) {
            $account = $this->accountRepository->findById($line->accountId());
            if ($account === null) {
                continue; // Skip if account not found (shouldn't happen)
            }

            $amount = $line->amount();
            if ($line->lineType()->value === 'debit') {
                $account->applyDebit($amount);
            } else {
                $account->applyCredit($amount);
            }

            $this->accountRepository->save($account);
        }
        // -------------------------------

        // Dispatch events
        foreach ($transaction->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        
        // Also release approval events?
        foreach ($approval->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $this->toDto($transaction);
    }
    
    // ... toDto ...
    private function toDto(Transaction $transaction): TransactionDto
    {
        $lines = [];
        $i = 0;
        foreach ($transaction->lines() as $line) {
            $lines[] = new TransactionLineDto(
                id: (string)$i,
                accountId: $line->accountId()->toString(),
                accountCode: 'Unknown',
                accountName: 'Unknown',
                lineType: $line->lineType()->value,
                amountCents: $line->amount()->cents(),
                lineOrder: $i++,
                description: $line->description() ?? '' 
            );
        }

        return new TransactionDto(
            id: $transaction->id()->toString(),
            transactionNumber: $transaction->id()->toString(), 
            companyId: $transaction->companyId()->toString(),
            status: $transaction->status()->value,
            description: $transaction->description(),
            totalDebitsCents: $transaction->totalDebits()->cents(),
            totalCreditsCents: $transaction->totalCredits()->cents(),
            lines: $lines,
            referenceNumber: $transaction->referenceNumber(),
            transactionDate: $transaction->transactionDate()->format('Y-m-d'),
            createdAt: $transaction->createdAt()->format('Y-m-d H:i:s'),
            postedAt: $transaction->postedAt()?->format('Y-m-d H:i:s'),
        );
    }
}
