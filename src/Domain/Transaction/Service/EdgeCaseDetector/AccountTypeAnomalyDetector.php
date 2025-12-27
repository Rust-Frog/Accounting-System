<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects account type anomalies (contra entries):
 * - Debit to Revenue account (Rule #7)
 * - Credit to Expense account (Rule #8)
 * - Credit to Asset account (Rule #9)
 * - Debit to Liability account (Rule #10)
 * - Any entry to Equity account (Rule #11)
 */
final class AccountTypeAnomalyDetector
{
    /**
     * @param array<array{account: Account, debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(array $lines, EdgeCaseThresholds $thresholds): EdgeCaseDetectionResult
    {
        $flags = [];

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = $line['account'];
            $debitCents = $line['debit_cents'] ?? 0;
            $creditCents = $line['credit_cents'] ?? 0;
            $accountType = $account->accountType();
            $accountName = $account->name();

            // Rule #7: Contra Revenue (debit to revenue)
            if ($thresholds->requireApprovalContraEntry()) {
                if ($accountType === AccountType::REVENUE && $debitCents > 0) {
                    $flags[] = EdgeCaseFlag::contraRevenue($accountName, $debitCents);
                }

                // Rule #8: Contra Expense (credit to expense) - unusual, flag it
                if ($accountType === AccountType::EXPENSE && $creditCents > 0) {
                    $flags[] = EdgeCaseFlag::contraExpense($accountName, $creditCents);
                }

                // Rule #9: Asset Write-down (credit to asset)
                // Commenting out - credits to assets are normal (paying for things)
                // Only unusual if the asset is being written off without corresponding entry
                // TODO: Make smarter by checking if there's a corresponding expense/liability
                // if ($accountType === AccountType::ASSET && $creditCents > 0) {
                //     $flags[] = EdgeCaseFlag::assetWritedown($accountName, $creditCents);
                // }
            }

            // Rule #10: Liability Reduction (debit to liability)
            // Only flag if this would reduce liability below what's expected for normal payments
            // For now, don't flag at all - paying off liabilities is normal business
            // Uncomment to enable: if ($accountType === AccountType::LIABILITY && $debitCents > 0) {
            //     $flags[] = EdgeCaseFlag::liabilityReduction($accountName, $debitCents);
            // }

            // Rule #11: Equity Adjustment - ONLY flag DEBITS (equity reduction/withdrawal)
            // Credits to equity (investments) are normal business operations
            if ($thresholds->requireApprovalEquityAdjustment()) {
                if ($accountType === AccountType::EQUITY && $debitCents > 0) {
                    // Debiting equity = reducing owner's stake = unusual, needs review
                    $flags[] = EdgeCaseFlag::equityAdjustment($accountName, $debitCents, 'debit');
                }
                // Credits to equity are normal (investments, retained earnings) - no flag
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }
}
