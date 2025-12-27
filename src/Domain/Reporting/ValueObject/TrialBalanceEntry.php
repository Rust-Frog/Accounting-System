<?php

declare(strict_types=1);

namespace Domain\Reporting\ValueObject;

/**
 * Value object representing a single entry in a trial balance.
 */
final readonly class TrialBalanceEntry
{
    public function __construct(
        private string $accountId,
        private string $accountCode,
        private string $accountName,
        private string $accountType,
        private int $debitBalanceCents,
        private int $creditBalanceCents
    ) {
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function accountCode(): string
    {
        return $this->accountCode;
    }

    public function accountName(): string
    {
        return $this->accountName;
    }

    public function accountType(): string
    {
        return $this->accountType;
    }

    public function debitBalanceCents(): int
    {
        return $this->debitBalanceCents;
    }

    public function creditBalanceCents(): int
    {
        return $this->creditBalanceCents;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'account_type' => $this->accountType,
            'debit_balance_cents' => $this->debitBalanceCents,
            'credit_balance_cents' => $this->creditBalanceCents,
        ];
    }
}
