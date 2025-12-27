<?php

declare(strict_types=1);

namespace Domain\Approval\Repository;

use Domain\Approval\Entity\Approval;
use Domain\Approval\ValueObject\ApprovalId;
use Domain\Approval\ValueObject\ApprovalStatus;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;

interface ApprovalRepositoryInterface
{
    public function save(Approval $approval): void;

    public function findById(ApprovalId $id): ?Approval;

    public function findByEntity(string $entityType, string $entityId): ?Approval;

    /**
     * @return array<Approval>
     */
    public function findPendingByCompany(CompanyId $companyId, int $limit = 20, int $offset = 0): array;

    /**
     * @return array<Approval>
     */
    public function findPendingForApprover(UserId $approverId): array;

    /**
     * @return array<Approval>
     */
    public function findByStatus(CompanyId $companyId, ApprovalStatus $status): array;

    /**
     * @return array<Approval>
     */
    public function findExpired(): array;

    public function countPendingByCompany(CompanyId $companyId): int;

    /**
     * Count all pending approvals system-wide.
     */
    public function countPending(): int;

    /**
     * Get recent pending approvals system-wide with company info.
     * @return array<array>
     */
    public function findRecentPending(int $limit = 5): array;
}
