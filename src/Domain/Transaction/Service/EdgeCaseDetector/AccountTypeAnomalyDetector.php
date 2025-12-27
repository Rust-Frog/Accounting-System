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

                // Rule #8: Contra Expense (credit to expense)
                if ($accountType === AccountType::EXPENSE && $creditCents > 0) {
                    $flags[] = EdgeCaseFlag::contraExpense($accountName, $creditCents);
                }

                // Rule #9: Asset Write-down (credit to asset)
                if ($accountType === AccountType::ASSET && $creditCents > 0) {
                    $flags[] = EdgeCaseFlag::assetWritedown($accountName, $creditCents);
                }
            }

            // Rule #10: Liability Reduction (debit to liability) - review only
            if ($accountType === AccountType::LIABILITY && $debitCents > 0) {
                $flags[] = EdgeCaseFlag::liabilityReduction($accountName, $debitCents);
            }

            // Rule #11: Equity Adjustment (any entry to equity)
            if ($thresholds->requireApprovalEquityAdjustment()) {
                if ($accountType === AccountType::EQUITY) {
                    $lineType = $debitCents > 0 ? 'debit' : 'credit';
                    $amount = $debitCents > 0 ? $debitCents : $creditCents;
                    if ($amount > 0) {
                        $flags[] = EdgeCaseFlag::equityAdjustment($accountName, $amount, $lineType);
                    }
                }
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }
}
