<?php

declare(strict_types=1);

namespace Domain\Reporting\Service;

use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\BalanceSheet;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Service interface for generating balance sheet reports.
 */
interface BalanceSheetGeneratorInterface
{
    /**
     * Generate balance sheet as of end of period.
     */
    public function generate(CompanyId $companyId, ReportPeriod $period): BalanceSheet;
}
