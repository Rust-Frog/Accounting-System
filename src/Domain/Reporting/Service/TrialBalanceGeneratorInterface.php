<?php

declare(strict_types=1);

namespace Domain\Reporting\Service;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\TrialBalance;

/**
 * Service interface for generating trial balance reports.
 */
interface TrialBalanceGeneratorInterface
{
    /**
     * Generate trial balance as of a specific date.
     */
    public function generate(CompanyId $companyId, DateTimeImmutable $asOfDate): TrialBalance;
}
