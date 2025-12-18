<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Approval\Entity;

use Domain\Approval\Entity\Approval;
use Domain\Approval\ValueObject\ApprovalReason;
use Domain\Approval\ValueObject\ApprovalStatus;
use Domain\Approval\ValueObject\ApprovalType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleException;
use PHPUnit\Framework\TestCase;

final class ApprovalTest extends TestCase
{
    private CompanyId $companyId;
    private UserId $requesterId;
    private UserId $adminId;

    protected function setUp(): void
    {
        $this->companyId = CompanyId::generate();
        $this->requesterId = UserId::generate();
        $this->adminId = UserId::generate();
    }

    public function test_creates_approval_request(): void
    {
        $approval = $this->createApproval();

        $this->assertTrue($approval->status()->isPending());
        $this->assertEquals(ApprovalType::NEGATIVE_EQUITY, $approval->approvalType());
        $this->assertEquals('Transaction', $approval->entityType());
        $this->assertNotEmpty($approval->releaseEvents());
    }

    public function test_approve_changes_status(): void
    {
        $approval = $this->createApproval();
        $approval->releaseEvents(); // Clear creation event

        $approval->approve($this->adminId, 'Approved for year-end');

        $this->assertTrue($approval->status()->isApproved());
        $this->assertEquals($this->adminId, $approval->reviewedBy());
        $this->assertEquals('Approved for year-end', $approval->reviewNotes());
        $this->assertCount(1, $approval->releaseEvents());
    }

    public function test_cannot_self_approve(): void
    {
        $approval = $this->createApproval();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot approve or reject your own request');

        $approval->approve($this->requesterId);
    }

    public function test_reject_requires_reason(): void
    {
        $approval = $this->createApproval();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('at least 10 characters');

        $approval->reject($this->adminId, 'short');
    }

    public function test_reject_with_valid_reason(): void
    {
        $approval = $this->createApproval();
        $approval->releaseEvents();

        $approval->reject($this->adminId, 'This transaction lacks proper documentation');

        $this->assertTrue($approval->status()->isRejected());
        $this->assertCount(1, $approval->releaseEvents());
    }

    public function test_cannot_approve_already_approved(): void
    {
        $approval = $this->createApproval();
        $approval->approve($this->adminId);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot transition');

        $anotherAdmin = UserId::generate();
        $approval->approve($anotherAdmin);
    }

    public function test_only_requester_can_cancel(): void
    {
        $approval = $this->createApproval();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only the requester can cancel');

        $approval->cancel($this->adminId, 'Want to cancel');
    }

    public function test_requester_can_cancel(): void
    {
        $approval = $this->createApproval();
        $approval->releaseEvents();

        $approval->cancel($this->requesterId, 'Editing the transaction');

        $this->assertEquals(ApprovalStatus::CANCELLED, $approval->status());
        $this->assertCount(1, $approval->releaseEvents());
    }

    public function test_expire_changes_status(): void
    {
        $approval = $this->createApproval();
        $approval->releaseEvents();

        $approval->expire();

        $this->assertEquals(ApprovalStatus::EXPIRED, $approval->status());
        $this->assertCount(1, $approval->releaseEvents());
    }

    public function test_default_priority_from_type(): void
    {
        $approval = $this->createApproval();

        // NEGATIVE_EQUITY has priority 2
        $this->assertEquals(2, $approval->priority());
    }

    public function test_expires_at_is_set(): void
    {
        $approval = $this->createApproval();

        // NEGATIVE_EQUITY has 24 hour expiration
        $this->assertNotNull($approval->expiresAt());
        $hoursDiff = ($approval->expiresAt()->getTimestamp() - $approval->requestedAt()->getTimestamp()) / 3600;
        $this->assertEquals(24, round($hoursDiff));
    }

    private function createApproval(): Approval
    {
        return Approval::request(new \Domain\Approval\ValueObject\CreateApprovalRequest(
            companyId: $this->companyId,
            approvalType: ApprovalType::NEGATIVE_EQUITY,
            entityType: 'Transaction',
            entityId: 'txn-123',
            reason: ApprovalReason::negativeEquity("Owner's Capital", -50000),
            requestedBy: $this->requesterId,
            amountCents: 550000,
            priority: 2
        ));
    }
}
