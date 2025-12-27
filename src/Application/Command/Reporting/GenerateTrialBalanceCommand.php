<?php

declare(strict_types=1);

namespace Application\Command\Reporting;

use Application\Command\CommandInterface;

/**
 * Command to generate a trial balance report.
 * 
 * Following CQRS pattern - this is a query-like command
 * that generates a report on-demand.
 */
final readonly class GenerateTrialBalanceCommand implements CommandInterface
{
    public function __construct(
        public string $companyId,
        public string $asOfDate,
        public string $generatedBy
    ) {
    }
}
