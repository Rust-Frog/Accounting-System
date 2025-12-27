<?php

declare(strict_types=1);

namespace Domain\Transaction\Service;

use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;

/**
 * Transaction Validation Service.
 * 
 * Validates transaction data against business rules before creation.
 * Phase 1: Hard blocks only (violations that should never be allowed).
 */
final class TransactionValidationService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository
    ) {
    }

    /**
     * Validate transaction lines.
     * 
     * @param array<array{account_id: string, debit_cents: int, credit_cents: int}> $lines
     * @return ValidationResult
     */
    public function validate(array $lines, CompanyId $companyId): ValidationResult
    {
        $errors = [];

        // Rule 1: Must have at least one line
        if (empty($lines)) {
            $errors[] = 'Transaction must have at least one line';
            return ValidationResult::invalid($errors);
        }

        // Rule 2: Check each line individually
        $totalDebits = 0;
        $totalCredits = 0;
        $accountIds = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $accountId = $line['account_id'] ?? null;
            $debitCents = (int) ($line['debit_cents'] ?? 0);
            $creditCents = (int) ($line['credit_cents'] ?? 0);

            // Rule 2a: Zero amount line
            if ($debitCents === 0 && $creditCents === 0) {
                $errors[] = "Line {$lineNumber}: Amount cannot be zero";
            }

            // Rule 2b: Both debit and credit filled (single line can't have both)
            if ($debitCents > 0 && $creditCents > 0) {
                $errors[] = "Line {$lineNumber}: Cannot have both debit and credit on same line";
            }

            // Rule 2c: Negative amounts
            if ($debitCents < 0 || $creditCents < 0) {
                $errors[] = "Line {$lineNumber}: Amount cannot be negative";
            }

            // Rule 2d: Missing account
            if (empty($accountId)) {
                $errors[] = "Line {$lineNumber}: Account is required";
            }

            // Track for duplicate and balance checks
            $totalDebits += $debitCents;
            $totalCredits += $creditCents;

            if ($accountId) {
                $accountIds[] = $accountId;
            }
        }

        // Rule 3: Transaction must be balanced
        if ($totalDebits !== $totalCredits) {
            $diffCents = abs($totalDebits - $totalCredits);
            $errors[] = sprintf(
                'Transaction is unbalanced: Debits ($%.2f) ≠ Credits ($%.2f), difference: $%.2f',
                $totalDebits / 100,
                $totalCredits / 100,
                $diffCents / 100
            );
        }

        // Rule 4: Check for duplicate account lines (same account appears twice)
        $accountCounts = array_count_values($accountIds);
        foreach ($accountCounts as $accountId => $count) {
            if ($count > 1) {
                $errors[] = "Same account '{$accountId}' appears {$count} times - use separate transactions or combine amounts";
            }
        }

        // Rule 5: Check accounts exist and are active
        foreach (array_unique($accountIds) as $accountIdString) {
            $accountIdVO = AccountId::fromString($accountIdString);
            $account = $this->accountRepository->findById($accountIdVO);
            if ($account === null) {
                $errors[] = "Account '{$accountIdString}' not found";
            } elseif (!$account->isActive()) {
                $errors[] = "Account '{$account->name()}' is inactive and cannot be used";
            }
        }

        if (!empty($errors)) {
            return ValidationResult::invalid($errors);
        }

        return ValidationResult::valid();
    }
}
