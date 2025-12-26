<?php

declare(strict_types=1);

namespace Infrastructure\Reporting\Service;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\TrialBalance;
use Domain\Reporting\Service\TrialBalanceGeneratorInterface;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;
use Domain\Reporting\ValueObject\TrialBalanceEntry;

/**
 * Trial Balance Generator implementation.
 * 
 * Generates a trial balance report by aggregating account balances
 * and verifying that total debits equal total credits.
 * 
 * Following DDD: This is an infrastructure service that implements
 * the domain service interface.
 */
final readonly class TrialBalanceGenerator implements TrialBalanceGeneratorInterface
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository
    ) {
    }

    /**
     * Generate trial balance as of a specific date.
     * 
     * BR-FR-001: Trial balance must show all accounts with their debit/credit balances.
     * BR-FR-002: Total debits must equal total credits for a balanced trial balance.
     */
    public function generate(CompanyId $companyId, DateTimeImmutable $asOfDate): TrialBalance
    {
        // Get all accounts for the company
        $accounts = $this->accountRepository->findByCompany($companyId);

        $entries = [];
        $totalDebitsCents = 0;
        $totalCreditsCents = 0;

        foreach ($accounts as $account) {
            // Skip inactive accounts with zero balance
            if (!$account->isActive() && $account->balance()->cents() === 0) {
                continue;
            }

            $balanceCents = $account->balance()->cents();
            $normalBalance = $account->normalBalance()->value;

            // Determine if balance goes in debit or credit column
            // Based on normal balance and actual balance sign
            $debitBalanceCents = 0;
            $creditBalanceCents = 0;

            if ($normalBalance === 'debit') {
                // Debit-normal accounts (Assets, Expenses)
                // Positive balance = Debit, Negative balance = Credit
                if ($balanceCents >= 0) {
                    $debitBalanceCents = $balanceCents;
                } else {
                    $creditBalanceCents = abs($balanceCents);
                }
            } else {
                // Credit-normal accounts (Liabilities, Equity, Revenue)
                // Positive balance = Credit, Negative balance = Debit
                if ($balanceCents >= 0) {
                    $creditBalanceCents = $balanceCents;
                } else {
                    $debitBalanceCents = abs($balanceCents);
                }
            }

            $entries[] = new TrialBalanceEntry(
                accountId: $account->id()->toString(),
                accountCode: (string) $account->code()->toInt(),
                accountName: $account->name(),
                accountType: $account->accountType()->value,
                debitBalanceCents: $debitBalanceCents,
                creditBalanceCents: $creditBalanceCents
            );

            $totalDebitsCents += $debitBalanceCents;
            $totalCreditsCents += $creditBalanceCents;
        }

        // Sort entries by account code
        usort($entries, fn($a, $b) => $a->accountCode() <=> $b->accountCode());

        // Create period ending on asOfDate
        $periodStart = new DateTimeImmutable('1970-01-01');
        $period = ReportPeriod::custom($periodStart, $asOfDate);

        return new TrialBalance(
            id: ReportId::generate(),
            companyId: $companyId,
            period: $period,
            generatedAt: new DateTimeImmutable(),
            entries: $entries,
            totalDebitsCents: $totalDebitsCents,
            totalCreditsCents: $totalCreditsCents
        );
    }
}
