<?php

declare(strict_types=1);

namespace Domain\Ledger\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class AccountBalanceChanged implements DomainEvent
{
    public function __construct(
        private string $accountId,
        private string $companyId,
        private string $accountType,
        private int $previousBalanceCents,
        private int $newBalanceCents,
        private int $changeCents,
        private string $transactionId,
        private bool $isReversal,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'ledger.account_balance_changed';
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'company_id' => $this->companyId,
            'account_type' => $this->accountType,
            'previous_balance_cents' => $this->previousBalanceCents,
            'new_balance_cents' => $this->newBalanceCents,
            'change_cents' => $this->changeCents,
            'transaction_id' => $this->transactionId,
            'is_reversal' => $this->isReversal,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function changeCents(): int
    {
        return $this->changeCents;
    }

    public function isReversal(): bool
    {
        return $this->isReversal;
    }
}
