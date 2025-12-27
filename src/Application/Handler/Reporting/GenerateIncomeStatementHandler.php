<?php

declare(strict_types=1);

namespace Application\Handler\Reporting;

use Application\Command\CommandInterface;
use Application\Command\Reporting\GenerateIncomeStatementCommand;
use Application\Handler\HandlerInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\IncomeStatement;
use Domain\Reporting\Service\IncomeStatementGeneratorInterface;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Handler for generating income statement reports.
 *
 * @implements HandlerInterface<GenerateIncomeStatementCommand>
 */
final readonly class GenerateIncomeStatementHandler implements HandlerInterface
{
    public function __construct(
        private IncomeStatementGeneratorInterface $generator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof GenerateIncomeStatementCommand);

        $companyId = CompanyId::fromString($command->companyId);

        // Create period from dates
        if ($command->startDate && $command->endDate) {
            $startDate = new \DateTimeImmutable($command->startDate);
            $endDate = new \DateTimeImmutable($command->endDate);
            $period = ReportPeriod::custom($startDate, $endDate);
        } elseif ($command->endDate) {
            // Default to current fiscal year start to end date
            $endDate = new \DateTimeImmutable($command->endDate);
            $startDate = new \DateTimeImmutable($endDate->format('Y') . '-01-01');
            $period = ReportPeriod::custom($startDate, $endDate);
        } else {
            // Default to current fiscal year
            $now = new \DateTimeImmutable();
            $startDate = new \DateTimeImmutable($now->format('Y') . '-01-01');
            $period = ReportPeriod::custom($startDate, $now);
        }

        $incomeStatement = $this->generator->generate($companyId, $period);

        return $this->toArray($incomeStatement);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(IncomeStatement $statement): array
    {
        return [
            'id' => $statement->id()->toString(),
            'company_id' => $statement->companyId()->toString(),
            'period' => $statement->period()->toArray(),
            'generated_at' => $statement->generatedAt()->format('c'),
            'revenue_accounts' => $statement->revenueAccounts(),
            'total_revenue_cents' => $statement->totalRevenueCents(),
            'expense_accounts' => $statement->expenseAccounts(),
            'total_expenses_cents' => $statement->totalExpensesCents(),
            'net_income_cents' => $statement->netIncomeCents(),
            'is_profit' => $statement->isProfit(),
            'is_loss' => $statement->isLoss(),
        ];
    }
}
