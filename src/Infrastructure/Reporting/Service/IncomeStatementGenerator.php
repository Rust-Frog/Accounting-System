<?php

declare(strict_types=1);

namespace Infrastructure\Reporting\Service;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\IncomeStatement;
use Domain\Reporting\Service\IncomeStatementGeneratorInterface;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Income Statement Generator implementation.
 * 
 * Generates an income statement (Profit & Loss) report by aggregating
 * revenue and expense accounts for a given period.
 * 
 * Following DDD: This is an infrastructure service that implements
 * the domain service interface.
 */
final readonly class IncomeStatementGenerator implements IncomeStatementGeneratorInterface
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository
    ) {
    }

    /**
     * Generate income statement for given period.
     * 
     * BR-FR-006: Net Income = Total Revenue - Total Expenses
     */
    public function generate(CompanyId $companyId, ReportPeriod $period): IncomeStatement
    {
        // Get all accounts for the company
        $accounts = $this->accountRepository->findByCompany($companyId);

        $revenueAccounts = [];
        $expenseAccounts = [];
        $totalRevenueCents = 0;
        $totalExpensesCents = 0;

        foreach ($accounts as $account) {
            // Skip inactive accounts with zero balance
            if (!$account->isActive() && $account->balance()->cents() === 0) {
                continue;
            }

            $accountType = $account->accountType()->value;
            $balanceCents = $account->balance()->cents();

            // Revenue accounts (credit-normal, positive balance = revenue)
            if ($accountType === 'revenue') {
                $amount = $balanceCents; // Positive = revenue earned
                $revenueAccounts[] = [
                    'account_id' => $account->id()->toString(),
                    'account_code' => (string) $account->code()->toInt(),
                    'account_name' => $account->name(),
                    'amount_cents' => $amount,
                ];
                $totalRevenueCents += $amount;
            }

            // Expense accounts (debit-normal, positive balance = expenses incurred)
            if ($accountType === 'expense') {
                $amount = $balanceCents; // Positive = expenses incurred
                $expenseAccounts[] = [
                    'account_id' => $account->id()->toString(),
                    'account_code' => (string) $account->code()->toInt(),
                    'account_name' => $account->name(),
                    'amount_cents' => $amount,
                ];
                $totalExpensesCents += $amount;
            }
        }

        // Sort by account code
        usort($revenueAccounts, fn($a, $b) => $a['account_code'] <=> $b['account_code']);
        usort($expenseAccounts, fn($a, $b) => $a['account_code'] <=> $b['account_code']);

        return new IncomeStatement(
            id: ReportId::generate(),
            companyId: $companyId,
            period: $period,
            generatedAt: new DateTimeImmutable(),
            revenueAccounts: $revenueAccounts,
            totalRevenueCents: $totalRevenueCents,
            expenseAccounts: $expenseAccounts,
            totalExpensesCents: $totalExpensesCents
        );
    }
}
