<?php

declare(strict_types=1);

namespace Application\Command\Reporting;

use Application\Command\CommandInterface;

/**
 * Command to generate a financial report (Balance Sheet or Income Statement).
 */
final readonly class GenerateReportCommand implements CommandInterface
{
    public function __construct(
        public string $companyId,
        public string $reportType, // 'balance_sheet' | 'income_statement'
        public string $periodStart,
        public string $periodEnd,
        public string $generatedBy,
    ) {
    }
}
