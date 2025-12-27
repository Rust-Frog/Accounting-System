<?php

declare(strict_types=1);

namespace Application\Command\Reporting;

use Application\Command\CommandInterface;

/**
 * Command to generate an income statement report.
 */
final readonly class GenerateIncomeStatementCommand implements CommandInterface
{
    public function __construct(
        public string $companyId,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {
    }
}
