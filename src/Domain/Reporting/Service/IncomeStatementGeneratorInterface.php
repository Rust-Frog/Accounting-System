<?php

declare(strict_types=1);

namespace Domain\Reporting\Service;

use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\IncomeStatement;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Service interface for generating income statement reports.
 */
interface IncomeStatementGeneratorInterface
{
    /**
     * Generate income statement for given period.
     */
    public function generate(CompanyId $companyId, ReportPeriod $period): IncomeStatement;
}
