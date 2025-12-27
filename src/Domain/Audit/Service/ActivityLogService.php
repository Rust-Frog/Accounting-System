<?php

declare(strict_types=1);

namespace Domain\Audit\Service;

use DateTimeImmutable;
use Domain\Audit\Entity\ActivityLog;
use Domain\Audit\Repository\ActivityLogRepositoryInterface;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\RequestContext;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\HashChain\ChainLink;
use Domain\Shared\ValueObject\HashChain\ContentHash;

/**
 * Service to create and persist secure, hash-chained activity logs.
 */
final class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $repository,
        private readonly AuditChainServiceInterface $auditChainService
    ) {
    }

    /**
     * Create and log an activity with cryptographic hash chaining.
     */
    public function logActivity(LogActivityRequest $request): ActivityLog
    {
        $id = ActivityId::generate();
        $occurredAt = new DateTimeImmutable();
        
        $entityInfo = $request->entityInfo;
        $stateInfo = $request->stateInfo;
        
        $entityType = $entityInfo['type'];
        $entityId = $entityInfo['id'];
        $action = $entityInfo['action'];
        
        $previousState = $stateInfo['prev'] ?? [];
        $newState = $stateInfo['new'] ?? [];
        $changes = $stateInfo['changes'] ?? [];

        // 1. Create preliminary log object to calculate content hash
        $tempLog = new ActivityLog(
            $id,
            CompanyId::fromString($request->companyId),
            $request->actor,
            $request->activityType,
            $entityType, 
            $entityId,
            $action, 
            $previousState, 
            $newState, 
            $changes, 
            $request->context, 
            $occurredAt,
            null, null, null // Hashes null initially
        );

        $contentHash = ContentHash::fromArray($tempLog->toContentArray());

        // 2. Get previous hash from chain
        $previousHash = $this->auditChainService->getLatestHash(CompanyId::fromString($request->companyId));

        // 3. Create chain link and compute chain hash
        $chainLink = new ChainLink($previousHash, $contentHash, $occurredAt);
        // $chainHash = $chainLink->computeHash(); // Not explicitly needed for constructor if Link has it? 
        // Wait, ActivityLog constructor takes `ChainLink` object. 
        // The Entity stores ChainLink, but maybe also chainHash string? 
        // Let's check ActivityLog signature in original thought or file.
        // It takes: ... ContentHash, ContentHash|null, ChainLink
        // It seems correct.

        // 4. Create final linked ActivityLog
        $linkedLog = new ActivityLog(
            $id, 
            CompanyId::fromString($request->companyId), 
            $request->actor, 
            $request->activityType, 
            $entityType, 
            $entityId,
            $action, 
            $previousState, 
            $newState, 
            $changes, 
            $request->context, 
            $occurredAt,
            $contentHash,
            $previousHash,
            $chainLink
        );

        // 5. Persist
        $this->repository->save($linkedLog);

        return $linkedLog;
    }
}
