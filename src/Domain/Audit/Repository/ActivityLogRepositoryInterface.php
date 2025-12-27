<?php

declare(strict_types=1);

namespace Domain\Audit\Repository;

use DateTimeImmutable;
use Domain\Audit\Entity\ActivityLog;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;

/**
 * Repository interface for audit logs (append-only).
 * BR-AT-001: No update or delete methods.
 */
interface ActivityLogRepositoryInterface
{
    /**
     * Save activity log (append-only).
     */
    public function save(ActivityLog $log): void;

    public function findById(ActivityId $id): ?ActivityLog;

    /**
     * @return array<ActivityLog>
     */
    public function findByEntity(string $entityType, string $entityId): array;

    /**
     * @return array<ActivityLog>
     */
    public function findByUser(UserId $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<ActivityLog>
     */
    public function findByCompanyAndDateRange(
        CompanyId $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array;

    /**
     * @return array<ActivityLog>
     */
    public function getRecent(CompanyId $companyId, int $limit = 100): array;

    /**
     * @return array<ActivityLog>
     */
    public function findByCompany(
        CompanyId $companyId,
        int $limit = 100,
        int $offset = 0,
        string $sortOrder = 'DESC'
    ): array;

    /**
     * Count total logs for a company.
     */
    public function countByCompany(CompanyId $companyId): int;

    // NOTE: No update or delete methods - audit logs are immutable
}
