<?php

declare(strict_types=1);

namespace Domain\Transaction\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Repository for fetching company-specific edge case thresholds.
 */
interface ThresholdRepositoryInterface
{
    /**
     * Get thresholds for a company, returns defaults if not configured.
     */
    public function getForCompany(CompanyId $companyId): EdgeCaseThresholds;
}
