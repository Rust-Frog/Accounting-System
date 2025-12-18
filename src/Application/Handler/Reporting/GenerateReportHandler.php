<?php

declare(strict_types=1);

namespace Application\Handler\Reporting;

use Application\Command\CommandInterface;
use Application\Command\Reporting\GenerateReportCommand;
use Application\Handler\HandlerInterface;
use DateTimeImmutable;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Repository\BalanceChangeRepositoryInterface;
use Domain\Reporting\Entity\Report;
use Domain\Reporting\Repository\ReportRepositoryInterface;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Handler for generating financial reports.
 *
 * @implements HandlerInterface<GenerateReportCommand>
 */
final readonly class GenerateReportHandler implements HandlerInterface
{
    public function __construct(
        private BalanceChangeRepositoryInterface $balanceChangeRepository,
        private AccountRepositoryInterface $accountRepository,
        private ReportRepositoryInterface $reportRepository,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof GenerateReportCommand);

        $companyId = CompanyId::fromString($command->companyId);
        $periodStart = new DateTimeImmutable($command->periodStart);
        $periodEnd = new DateTimeImmutable($command->periodEnd);

        // Get all accounts for the company
        $accounts = $this->accountRepository->findByCompany($companyId);

        // Get balance changes aggregated by account
        $balanceChanges = $this->balanceChangeRepository->sumChangesByCompanyAndPeriod(
            $companyId,
            $periodStart,
            $periodEnd
        );

        // Calculate report data based on type
        $reportData = match ($command->reportType) {
            'balance_sheet' => $this->calculateBalanceSheet($accounts, $balanceChanges),
            'income_statement' => $this->calculateIncomeStatement($accounts, $balanceChanges),
            default => throw new \InvalidArgumentException("Unknown report type: {$command->reportType}"),
        };

        // Create and save report
        $report = Report::reconstruct(
            ReportId::generate(),
            $companyId,
            ReportPeriod::custom($periodStart, $periodEnd),
            $command->reportType,
            $reportData,
            new DateTimeImmutable()
        );

        $this->reportRepository->save($report);

        return [
            'id' => $report->id()->toString(),
            'type' => $command->reportType,
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'data' => $reportData,
            'generated_at' => $report->generatedAt()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Calculate Balance Sheet: Assets = Liabilities + Equity
     */
    private function calculateBalanceSheet(array $accounts, array $balanceChanges): array
    {
        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($accounts as $account) {
            $accountId = $account->id()->toString();
            $balance = $balanceChanges[$accountId] ?? 0;
            $accountData = [
                'id' => $accountId,
                'code' => $account->code()->toString(),
                'name' => $account->name(),
                'balance_cents' => $balance,
            ];

            $type = $account->accountType();

            if ($type === AccountType::ASSET) {
                $assets[] = $accountData;
                $totalAssets += $balance;
            } elseif ($type === AccountType::LIABILITY) {
                $liabilities[] = $accountData;
                $totalLiabilities += $balance;
            } elseif ($type === AccountType::EQUITY) {
                $equity[] = $accountData;
                $totalEquity += $balance;
            }
        }

        return [
            'assets' => [
                'accounts' => $assets,
                'total_cents' => $totalAssets,
            ],
            'liabilities' => [
                'accounts' => $liabilities,
                'total_cents' => $totalLiabilities,
            ],
            'equity' => [
                'accounts' => $equity,
                'total_cents' => $totalEquity,
            ],
            'balance_check' => [
                'assets' => $totalAssets,
                'liabilities_plus_equity' => $totalLiabilities + $totalEquity,
                'is_balanced' => $totalAssets === ($totalLiabilities + $totalEquity),
            ],
        ];
    }

    /**
     * Calculate Income Statement: Revenue - Expenses = Net Income
     */
    private function calculateIncomeStatement(array $accounts, array $balanceChanges): array
    {
        $revenue = [];
        $expenses = [];
        $totalRevenue = 0;
        $totalExpenses = 0;

        foreach ($accounts as $account) {
            $accountId = $account->id()->toString();
            $balance = $balanceChanges[$accountId] ?? 0;
            $accountData = [
                'id' => $accountId,
                'code' => $account->code()->toString(),
                'name' => $account->name(),
                'balance_cents' => $balance,
            ];

            $type = $account->accountType();

            if ($type === AccountType::REVENUE) {
                $revenue[] = $accountData;
                $totalRevenue += $balance;
            } elseif ($type === AccountType::EXPENSE) {
                $expenses[] = $accountData;
                $totalExpenses += $balance;
            }
        }

        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'revenue' => [
                'accounts' => $revenue,
                'total_cents' => $totalRevenue,
            ],
            'expenses' => [
                'accounts' => $expenses,
                'total_cents' => $totalExpenses,
            ],
            'net_income_cents' => $netIncome,
            'is_profitable' => $netIncome > 0,
        ];
    }
}
