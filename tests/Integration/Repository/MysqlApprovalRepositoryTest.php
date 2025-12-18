<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use Domain\Approval\Entity\Approval;
use Domain\Approval\ValueObject\ApprovalId;
use Domain\Approval\ValueObject\ApprovalReason;
use Domain\Approval\ValueObject\ApprovalStatus;
use Domain\Approval\ValueObject\ApprovalType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Infrastructure\Persistence\Mysql\Repository\MysqlApprovalRepository;
use Tests\Integration\BaseIntegrationTestCase;
use Tests\Integration\DatabaseTestHelper;

class MysqlApprovalRepositoryTest extends BaseIntegrationTestCase
{
    use DatabaseTestHelper;

    private MysqlApprovalRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MysqlApprovalRepository($this->pdo);
    }

    public function testSaveAndFindById(): void
    {
        $companyId = CompanyId::generate();
        $this->createCompany($this->pdo, $companyId->toString());
        
        $userId = UserId::generate();
        $this->createUser($this->pdo, $userId->toString(), $companyId->toString());

        $approval = Approval::request(new \Domain\Approval\ValueObject\CreateApprovalRequest(
            $companyId,
            ApprovalType::HIGH_VALUE,
            'Transaction',
            'txn-12345',
            ApprovalReason::highValue(100000, 50000),
            $userId,
            100000,
            1 
        ));

        $this->repository->save($approval);

        $retrieved = $this->repository->findById($approval->id());

        $this->assertNotNull($retrieved);
        $this->assertEquals(ApprovalType::HIGH_VALUE, $retrieved->approvalType());
        $this->assertEquals(ApprovalStatus::PENDING, $retrieved->status());
    }

    public function testFindPendingByCompany(): void
    {
        $companyId = CompanyId::generate();
        $this->createCompany($this->pdo, $companyId->toString());
        
        $userId = UserId::generate();
        $this->createUser($this->pdo, $userId->toString(), $companyId->toString());

        $approval1 = Approval::request(new \Domain\Approval\ValueObject\CreateApprovalRequest(
            $companyId,
            ApprovalType::HIGH_VALUE,
            'Transaction',
            'txn-001',
            ApprovalReason::highValue(80000, 50000),
            $userId,
            80000,
            1
        ));

        $approval2 = Approval::request(new \Domain\Approval\ValueObject\CreateApprovalRequest(
            $companyId,
            ApprovalType::NEGATIVE_EQUITY,
            'Account',
            'acc-001',
            ApprovalReason::negativeEquity('Cash', -5000),
            $userId,
            0,
            2
        ));

        $this->repository->save($approval1);
        $this->repository->save($approval2);

        $pending = $this->repository->findPendingByCompany($companyId);

        $this->assertCount(2, $pending);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findById(ApprovalId::generate());
        $this->assertNull($result);
    }
}
