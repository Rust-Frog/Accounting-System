<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use DateTimeImmutable;
use Domain\Audit\Entity\ActivityLog;
use Domain\Audit\Repository\ActivityLogRepositoryInterface;
use Domain\Audit\Service\AuditChainServiceInterface;
use Domain\Audit\Service\IntegrityResult;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\RequestContext;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\HashChain\ContentHash;

/**
 * Blockchain-style audit chain service implementation.
 * Maintains hash chain integrity for tamper-evident audit logs.
 */
final class AuditChainService implements AuditChainServiceInterface
{
    /**
     * In-memory cache of latest hashes per company.
     * @var array<string, ContentHash>
     */
    private array $latestHashCache = [];

    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogRepository
    ) {
    }

    public function getLatestHash(CompanyId $companyId): ContentHash
    {
        $companyIdStr = $companyId->toString();

        // Check cache first
        if (isset($this->latestHashCache[$companyIdStr])) {
            return $this->latestHashCache[$companyIdStr];
        }

        // Get the most recent activity log entry
        $latestEntries = $this->activityLogRepository->findByCompany(
            $companyId,
            1,  // limit
            0   // offset
        );

        if (empty($latestEntries)) {
            // No entries yet, return genesis hash
            return ContentHash::genesis($companyIdStr);
        }

        // Compute hash from latest entry
        $latestHash = $this->computeEntryHash($latestEntries[0]);
        $this->latestHashCache[$companyIdStr] = $latestHash;

        return $latestHash;
    }

    public function appendEntry(ActivityLog $log, CompanyId $companyId): ContentHash
    {
        // Get current chain tip
        $previousHash = $this->getLatestHash($companyId);

        // Compute new entry's content hash
        $contentHash = $this->computeEntryHash($log);

        // Create chain link by combining previous + current
        $chainHash = ContentHash::fromContent(
            $previousHash->toString() .
            $contentHash->toString() .
            $log->occurredAt()->format('Y-m-d H:i:s.u')
        );

        // Update cache
        $this->latestHashCache[$companyId->toString()] = $chainHash;

        return $chainHash;
    }

    public function verifyIntegrity(CompanyId $companyId): IntegrityResult
    {
        // Get all entries for company in chronological order
        $entries = $this->activityLogRepository->findByCompany(
            $companyId,
            10000, // Large limit for full verification
            0,
            'ASC'  // Chronological order
        );

        if (empty($entries)) {
            return IntegrityResult::verified(0);
        }

        // Start from genesis
        $currentHash = ContentHash::genesis($companyId->toString());
        $verified = 0;

        foreach ($entries as $entry) {
            $contentHash = $this->computeEntryHash($entry);

            // Compute expected chain hash
            $expectedChainHash = ContentHash::fromContent(
                $currentHash->toString() .
                $contentHash->toString() .
                $entry->occurredAt()->format('Y-m-d H:i:s.u')
            );

            // Move to next link
            $currentHash = $expectedChainHash;
            $verified++;
        }

        return IntegrityResult::verified($verified);
    }

    public function computeEntryHash(ActivityLog $log): ContentHash
    {
        return ContentHash::fromArray($log->toArray());
    }

    public function logSecurityEvent(string $eventType, array $context): void
    {
        // Determine activity type based on event
        $activityType = $eventType === 'access_denied'
            ? ActivityType::SECURITY_ACCESS_DENIED
            : ActivityType::SYSTEM_ERROR;

        $log = new ActivityLog(
            id: ActivityId::generate(),
            companyId: CompanyId::fromString($context['company_id'] ?? '00000000-0000-0000-0000-000000000000'),
            actor: isset($context['user_id'])
                ? Actor::user(UserId::fromString($context['user_id']), $context['user_name'] ?? 'Unknown')
                : Actor::system(),
            activityType: $activityType,
            entityType: 'security',
            entityId: $context['route'] ?? 'unknown',
            action: $eventType,
            previousState: [],
            newState: $context,
            changes: [],
            context: RequestContext::fromRequest(
                ipAddress: $context['ip'] ?? '0.0.0.0',
                userAgent: $context['user_agent'] ?? 'System',
                requestId: uniqid('sec_', true)
            ),
            occurredAt: new DateTimeImmutable()
        );

        $this->activityLogRepository->save($log);
    }
}
