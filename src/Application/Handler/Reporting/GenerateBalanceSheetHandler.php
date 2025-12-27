<?php

declare(strict_types=1);

namespace Application\Handler\Reporting;

use Application\Command\Reporting\GenerateBalanceSheetCommand;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Service\BalanceSheetGeneratorInterface;
use Domain\Reporting\ValueObject\ReportPeriod;
use DateTimeImmutable;

/**
 * Handler for generating Balance Sheet reports.
 * 
 * Application layer handler following CQRS pattern.
 * Orchestrates the domain service to generate the report.
 */
final readonly class GenerateBalanceSheetHandler
{
    public function __construct(
        private BalanceSheetGeneratorInterface $generator
    ) {
    }

    /**
     * Handle the command and return balance sheet data.
     * 
     * @return array<string, mixed>
     */
    public function handle(GenerateBalanceSheetCommand $command): array
    {
        $companyId = CompanyId::fromString($command->companyId);
        
        // Parse as_of_date or use today
        $asOfDate = $command->asOfDate 
            ? new DateTimeImmutable($command->asOfDate)
            : new DateTimeImmutable();

        // Balance sheet is as of a point in time (use same date for start/end)
        $period = ReportPeriod::custom($asOfDate, $asOfDate);
        
        $balanceSheet = $this->generator->generate($companyId, $period);

        return $balanceSheet->toArray();
    }
}
