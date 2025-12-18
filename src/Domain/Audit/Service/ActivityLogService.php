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
     *
     * @param CompanyId $companyId
     * @param Actor $actor
     * @param ActivityType $activityType
     * @param array{type: string, id: string, action: string} $entityInfo
     * @param array{prev: array, new: array, changes: array} $stateInfo
     * @param RequestContext $context
     * @return ActivityLog
     */
    public function logActivity(LogActivityRequest $request): ActivityLog {
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

        // 6. Update cache in AuditChainService (manual update not needed if appendEntry did it, 
        // but appendEntry implementation in Infrastructure was doing calculation. 
        // We should PROBABLY update the cache. 
        // The AuditChainService interface should strictly have appendEntry or updateCache?
        // Our simplified AuditChainService implementation in Infrastructure has appendEntry which returns hash AND updates cache.
        // But it re-calculates. 
        // Let's rely on a new method or modify appendEntry to accept the calculated hash?
        // Or just re-call appendEntry? No, that would re-calculate.
        // Checking AuditChainService::appendEntry source: it does getLatestHash, computeEntryHash, then updates cache.
        // If we use appendEntry, we don't need to do steps 2-3 manually?
        // YES! The current implementation of appendEntry does exactly what we want.
        // BUT, it takes an ActivityLog.
        // If we pass the tempLog (without hashes), appendEntry computes content hash, gets prev hash, and calculates chain hash.
        // It returns the ChainHash.
        // But it doesn't give us the previousHash or contentHash to put INTO the log entity for persistence!
        // This is the Catch-22.
        
        // We should just use the logic here (Steps 1-4) and then validly tell AuditChainService "Hey, I added this, update your cache".
        // But AuditChainService doesn't have `updateCache`.
        // We should probably modify AuditChainServiceInterface to allow getting/setting or make this logic PART of AuditChainService?
        // Immutability of Domain Entities makes "updating" hard.
        // Best approach: This service controls the creation. 
        // We need to update AuditChainService to allow "registering" a new latest hash without re-computing if we do it here.
        // OR we expose `getLatestHash` (we did) and we just rely on repository for next time?
        // But AuditChainService has in-memory cache.
        // We should probably rely on `getLatestHash` here. 
        // And if AuditChainService's cache is stale (bc we didn't call appendEntry), the next call might fail consistency?
        // No, `getLatestHash` checks cache OR repo. If we save to repo, next `getLatestHash` will find it in repo if we clear cache or if we add it.
        // We should update the cache. 
        // Let's assume for now we don't update cache explicitly, we just save to repo. 
        // `AuditChainService::getLatestHash` checks cache first. If we don't update cache, it returns old hash.
        // Then next log will use OLD hash -> Fork!
        // So we MUST update cache.
        // I will add `registerNewHash(CompanyId $id, ContentHash $hash)` to AuditChainService?
        // Or better: Use `appendEntry` but change its contract?
    }
}
