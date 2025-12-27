<?php

declare(strict_types=1);

namespace Api\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Validated request for transaction creation.
 */
final class CreateTransactionRequest extends ValidatedRequest
{
    protected function validate(): void
    {
        $this->requireField('description', 'Description');
        $this->requireMinLength('description', 1, 'Description');
        $this->requireMaxLength('description', 500, 'Description');

        $this->requireField('lines', 'Transaction lines');
        $this->requireArray('lines', 'Transaction lines');
        $this->requireMinArrayLength('lines', 2, 'Transaction lines');

        // Validate each line
        $lines = $this->get('lines', []);
        if (is_array($lines)) {
            foreach ($lines as $index => $line) {
                $this->validateLine($line, $index);
            }

            // Check that debits = credits
            $this->validateBalanced($lines);
        }
    }

    /**
     * Validate a single transaction line.
     */
    private function validateLine(mixed $line, int $index): void
    {
        if (!is_array($line)) {
            $this->addError("lines.{$index}", 'Line must be an object');
            return;
        }

        if (empty($line['account_id'])) {
            $this->addError("lines.{$index}.account_id", 'Account ID is required');
        }

        if (empty($line['line_type'])) {
            $this->addError("lines.{$index}.line_type", 'Line type is required');
        } elseif (!in_array($line['line_type'], ['debit', 'credit'], true)) {
            $this->addError("lines.{$index}.line_type", 'Line type must be debit or credit');
        }

        if (!isset($line['amount_cents'])) {
            $this->addError("lines.{$index}.amount_cents", 'Amount is required');
        } elseif (!is_int($line['amount_cents']) && !ctype_digit((string) $line['amount_cents'])) {
            $this->addError("lines.{$index}.amount_cents", 'Amount must be an integer');
        } elseif ((int) $line['amount_cents'] <= 0) {
            $this->addError("lines.{$index}.amount_cents", 'Amount must be positive');
        }
    }

    /**
     * Validate that debits and credits are balanced.
     */
    private function validateBalanced(array $lines): void
    {
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $amount = (int) ($line['amount_cents'] ?? 0);
            $type = $line['line_type'] ?? '';

            if ($type === 'debit') {
                $totalDebits += $amount;
            } elseif ($type === 'credit') {
                $totalCredits += $amount;
            }
        }

        if ($totalDebits !== $totalCredits) {
            $this->addError('lines', "Transaction must be balanced. Debits: {$totalDebits}, Credits: {$totalCredits}");
        }
    }

    public function description(): string
    {
        return (string) $this->get('description');
    }

    /**
     * @return array<array{account_id: string, line_type: string, amount_cents: int, description?: string}>
     */
    public function lines(): array
    {
        return (array) $this->get('lines', []);
    }

    public function currency(): string
    {
        return (string) $this->get('currency', 'USD');
    }

    public function date(): ?string
    {
        return $this->get('date');
    }

    public function referenceNumber(): ?string
    {
        return $this->get('reference_number');
    }
}
