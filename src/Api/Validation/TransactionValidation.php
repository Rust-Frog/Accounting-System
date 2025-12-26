<?php

declare(strict_types=1);

namespace Api\Validation;

use Domain\Shared\Validation\RequestValidator;
use Domain\Shared\Validation\ValidationResult;

/**
 * Validation rules for transaction-related API requests.
 * Centralizes all transaction validation logic.
 */
final class TransactionValidation
{
    private RequestValidator $validator;

    public function __construct(?RequestValidator $validator = null)
    {
        $this->validator = $validator ?? new RequestValidator();
    }

    /**
     * Validate transaction creation request.
     */
    public function validateCreate(array $data): ValidationResult
    {
        $result = $this->validator->validate($data, [
            'description' => ['required', 'string', 'min:1', 'max:500'],
            'date' => ['date'],
            'reference_number' => ['string', 'max:100'],
            'currency' => ['currency'],
            'lines' => ['required', 'array', 'min:2'],
        ]);

        if ($result->isInvalid()) {
            return $result;
        }

        // Validate each line
        return $this->validateLines($data['lines'] ?? []);
    }

    /**
     * Validate transaction update request.
     */
    public function validateUpdate(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'description' => ['required', 'string', 'min:1', 'max:500'],
            'date' => ['date'],
            'reference_number' => ['string', 'max:100'],
            'lines' => ['required', 'array', 'min:2'],
        ]);
    }

    /**
     * Validate transaction lines.
     */
    private function validateLines(array $lines): ValidationResult
    {
        $errors = [];
        $totalDebits = 0;
        $totalCredits = 0;
        $hasDebit = false;
        $hasCredit = false;

        foreach ($lines as $index => $line) {
            $lineErrors = $this->validateLine($line, $index);
            if (!empty($lineErrors)) {
                $errors["lines.{$index}"] = $lineErrors;
            }

            // Track totals for balance validation
            $amount = (int) ($line['amount_cents'] ?? 0);
            $type = $line['line_type'] ?? '';
            
            if ($type === 'debit') {
                $totalDebits += $amount;
                $hasDebit = true;
            } elseif ($type === 'credit') {
                $totalCredits += $amount;
                $hasCredit = true;
            }
        }

        // Business rule: Must have at least one debit and one credit
        if (!$hasDebit || !$hasCredit) {
            $errors['lines'] = ['Transaction must have at least one debit and one credit line'];
        }

        // Business rule: Debits must equal credits
        if ($totalDebits !== $totalCredits) {
            $errors['balance'] = [
                sprintf('Transaction is not balanced: Debits (%d) != Credits (%d)', $totalDebits, $totalCredits)
            ];
        }

        if (empty($errors)) {
            return ValidationResult::valid();
        }

        return ValidationResult::invalid($errors);
    }

    /**
     * Validate a single transaction line.
     * @return string[] Errors for this line
     */
    private function validateLine(array $line, int $index): array
    {
        $errors = [];

        // Account ID required and must be valid UUID
        if (empty($line['account_id'])) {
            $errors[] = 'Account ID is required';
        } elseif (!$this->isValidUuid($line['account_id'])) {
            $errors[] = 'Account ID must be a valid UUID';
        }

        // Line type required and must be debit or credit
        if (empty($line['line_type'])) {
            $errors[] = 'Line type is required';
        } elseif (!in_array($line['line_type'], ['debit', 'credit'], true)) {
            $errors[] = 'Line type must be debit or credit';
        }

        // Amount required and must be positive
        if (!isset($line['amount_cents'])) {
            $errors[] = 'Amount is required';
        } elseif (!is_numeric($line['amount_cents']) || (int) $line['amount_cents'] <= 0) {
            $errors[] = 'Amount must be a positive number';
        }

        return $errors;
    }

    private function isValidUuid(string $value): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return (bool) preg_match($pattern, $value);
    }
}
