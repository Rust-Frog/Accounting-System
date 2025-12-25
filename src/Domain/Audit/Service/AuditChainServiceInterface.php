<?php

declare(strict_types=1);

namespace Domain\Audit\Service;

use Domain\Audit\Entity\ActivityLog;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\HashChain\ContentHash;

/**
 * Interface for managing blockchain-style audit hash chains.
 */
interface AuditChainServiceInterface
{
    /**
     * Get the latest hash for a company's audit chain.
     * Returns genesis hash if no entries exist.
     */
    public function getLatestHash(CompanyId $companyId): ContentHash;

    /**
     * Append an activity log entry to the chain.
     * Computes hash linking to previous entry.
     *
     * @return ContentHash The new chain hash after appending
     */
    public function appendEntry(ActivityLog $log, CompanyId $companyId): ContentHash;

    /**
     * Verify the integrity of a company's entire audit chain.
     *
     * @return IntegrityResult Contains verification status and any broken links
     */
    public function verifyIntegrity(CompanyId $companyId): IntegrityResult;

    /**
     * Compute and store the content hash for an activity log entry.
     */
    public function computeEntryHash(ActivityLog $log): ContentHash;

    /**
     * Log a security-related event for audit trail.
     *
     * @param string $eventType Type of security event (access_denied, login_failed, etc)
     * @param array<string, mixed> $context Event context data
     */
    public function logSecurityEvent(string $eventType, array $context): void;
}
