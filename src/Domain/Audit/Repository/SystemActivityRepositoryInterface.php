<?php

declare(strict_types=1);

namespace Domain\Audit\Repository;

use Domain\Audit\Entity\SystemActivity;
use Domain\Audit\ValueObject\ActivityId;

/**
 * Repository interface for system-wide activities.
 */
interface SystemActivityRepositoryInterface
{
    /**
     * Save a system activity.
     */
    public function save(SystemActivity $activity): void;

    /**
     * Find recent activities.
     * 
     * @param int $limit Max activities to return
     * @param int $offset Pagination offset
     * @return SystemActivity[]
     */
    public function findRecent(int $limit = 10, int $offset = 0): array;

    /**
     * Find the latest activity (for hash chain).
     */
    public function findLatest(): ?SystemActivity;

    /**
     * Find by ID.
     */
    public function findById(ActivityId $id): ?SystemActivity;

    /**
     * Find by activity type.
     * 
     * @return SystemActivity[]
     */
    public function findByType(string $activityType, int $limit = 10, int $offset = 0): array;

    /**
     * Count total activities.
     */
    public function count(): int;
}
