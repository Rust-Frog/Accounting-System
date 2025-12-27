<?php

declare(strict_types=1);

namespace Domain\Audit\Service;

use Domain\Audit\Entity\SystemActivity;
use Domain\Audit\Repository\SystemActivityRepositoryInterface;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\HashChain\ContentHash;

/**
 * Service for logging system-wide activities with hash chain.
 */
final class SystemActivityService
{
    public function __construct(
        private readonly SystemActivityRepositoryInterface $repository
    ) {
    }

    /**
     * Log a system activity with automatic hash chaining.
     */
    public function log(
        string $activityType,
        string $entityType,
        string $entityId,
        string $description,
        ?UserId $actorUserId = null,
        ?string $actorUsername = null,
        ?string $actorIpAddress = null,
        string $severity = 'info',
        ?array $metadata = null
    ): SystemActivity {
        // Get the latest activity for hash chain
        $latest = $this->repository->findLatest();
        
        $previousHash = $latest?->chainHash();
        $previousId = $latest?->id();

        // Create new activity with hash chain
        $activity = SystemActivity::create(
            actorUserId: $actorUserId,
            actorUsername: $actorUsername,
            actorIpAddress: $actorIpAddress,
            activityType: $activityType,
            severity: $severity,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            metadata: $metadata,
            previousHash: $previousHash,
            previousId: $previousId
        );

        // Persist
        $this->repository->save($activity);

        return $activity;
    }

    public function getRecent(int $limit = 4, int $offset = 0): array
    {
        return $this->repository->findRecent($limit, $offset);
    }

    /**
     * Get total count of system activities.
     */
    public function getTotalCount(): int
    {
        return $this->repository->count();
    }

    /**
     * Verify chain integrity.
     */
    public function verifyIntegrity(): IntegrityResult
    {
        $activities = $this->repository->findRecent(10000);
        
        if (empty($activities)) {
            return IntegrityResult::verified(0);
        }

        // Reverse for chronological order (oldest first)
        $activities = array_reverse($activities);
        
        $expectedHash = ContentHash::genesis('system');
        $verified = 0;

        foreach ($activities as $activity) {
            if (!$activity->verifyChain($expectedHash)) {
                return IntegrityResult::broken($activity->id()->toString(), $verified);
            }
            $expectedHash = $activity->chainHash();
            $verified++;
        }

        return IntegrityResult::verified($verified);
    }
}
