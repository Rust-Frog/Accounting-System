<?php

declare(strict_types=1);

namespace Application\Command\Reporting;

/**
 * Command to generate a Balance Sheet report.
 * 
 * BR-FR-001: Assets = Liabilities + Equity
 */
final readonly class GenerateBalanceSheetCommand
{
    public function __construct(
        public string $companyId,
        public ?string $asOfDate = null
    ) {
    }
}
