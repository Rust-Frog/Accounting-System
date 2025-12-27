<?php

declare(strict_types=1);

namespace Domain\Ledger\ValueObject;

use DateTimeImmutable;

final class BalanceMetrics
{
    public function __construct(
        private readonly int $currentBalanceCents,
        private readonly int $openingBalanceCents,
        private readonly int $totalDebitsCents,
        private readonly int $totalCreditsCents,
        private readonly int $transactionCount,
        private readonly ?DateTimeImmutable $lastTransactionAt,
        private readonly int $version
    ) {
    }

    public static function initialize(int $openingBalanceCents): self
    {
        return new self(
            currentBalanceCents: $openingBalanceCents,
            openingBalanceCents: $openingBalanceCents,
            totalDebitsCents: 0,
            totalCreditsCents: 0,
            transactionCount: 0,
            lastTransactionAt: null,
            version: 1
        );
    }

    public static function reconstruct(
        int $currentBalanceCents,
        int $openingBalanceCents,
        int $totalDebitsCents,
        int $totalCreditsCents,
        int $transactionCount,
        ?DateTimeImmutable $lastTransactionAt,
        int $version
    ): self {
        return new self(
            currentBalanceCents: $currentBalanceCents,
            openingBalanceCents: $openingBalanceCents,
            totalDebitsCents: $totalDebitsCents,
            totalCreditsCents: $totalCreditsCents,
            transactionCount: $transactionCount,
            lastTransactionAt: $lastTransactionAt,
            version: $version
        );
    }

    public function withUpdate(int $changeCents, int $debitAmount, int $creditAmount, DateTimeImmutable $occurredAt): self
    {
        return new self(
            currentBalanceCents: $this->currentBalanceCents + $changeCents,
            openingBalanceCents: $this->openingBalanceCents,
            totalDebitsCents: $this->totalDebitsCents + $debitAmount,
            totalCreditsCents: $this->totalCreditsCents + $creditAmount,
            transactionCount: $this->transactionCount + 1,
            lastTransactionAt: $occurredAt,
            version: $this->version + 1
        );
    }

    public function currentBalanceCents(): int
    {
        return $this->currentBalanceCents;
    }

    public function openingBalanceCents(): int
    {
        return $this->openingBalanceCents;
    }

    public function totalDebitsCents(): int
    {
        return $this->totalDebitsCents;
    }

    public function totalCreditsCents(): int
    {
        return $this->totalCreditsCents;
    }

    public function transactionCount(): int
    {
        return $this->transactionCount;
    }

    public function lastTransactionAt(): ?DateTimeImmutable
    {
        return $this->lastTransactionAt;
    }

    public function version(): int
    {
        return $this->version;
    }
}
