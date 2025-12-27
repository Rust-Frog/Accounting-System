<?php

declare(strict_types=1);

namespace Domain\Transaction\Service;

use Domain\Transaction\Entity\Transaction;

/**
 * Domain service for validating transactions.
 * Implements all business rules for transaction validation.
 */
final class TransactionValidator
{
    private const MINIMUM_LINES = 2;

    /**
     * Validate a transaction against all business rules.
     *
     * Rules validated:
     * - BR-TXN-001: Double-entry (debits = credits)
     * - BR-TXN-002: Minimum 2 lines with at least one debit and one credit
     * - BR-TXN-003: Positive amounts (validated at line creation)
     */
    public function validate(Transaction $transaction): ValidationResult
    {
        $errors = [];

        $errors = $this->validateMinimumLines($transaction, $errors);
        $errors = $this->validateDebitCreditPresence($transaction, $errors);
        $errors = $this->validateDoubleEntry($transaction, $errors);

        if (empty($errors)) {
            return ValidationResult::valid();
        }

        return ValidationResult::invalid($errors);
    }

    /**
     * Validate minimum number of lines (BR-TXN-002).
     *
     * @param array<string> $errors
     * @return array<string>
     */
    private function validateMinimumLines(Transaction $transaction, array $errors): array
    {
        if ($transaction->lineCount() < self::MINIMUM_LINES) {
            $errors[] = 'Transaction must have at least 2 lines';
        }

        return $errors;
    }

    /**
     * Validate at least one debit and one credit line (BR-TXN-002).
     *
     * @param array<string> $errors
     * @return array<string>
     */
    private function validateDebitCreditPresence(Transaction $transaction, array $errors): array
    {
        if (!$transaction->hasDebitLines() || !$transaction->hasCreditLines()) {
            $errors[] = 'Transaction must have at least one debit and one credit';
        }

        return $errors;
    }

    /**
     * Validate double-entry rule: debits must equal credits (BR-TXN-001).
     *
     * @param array<string> $errors
     * @return array<string>
     */
    private function validateDoubleEntry(Transaction $transaction, array $errors): array
    {
        if ($transaction->lineCount() < 2) {
            // Skip if minimum lines not met - already has that error
            return $errors;
        }

        if (!$transaction->isBalanced()) {
            $errors[] = sprintf(
                'Transaction is not balanced: Debits (%d cents) != Credits (%d cents)',
                $transaction->totalDebits()->cents(),
                $transaction->totalCredits()->cents()
            );
        }

        return $errors;
    }
}
