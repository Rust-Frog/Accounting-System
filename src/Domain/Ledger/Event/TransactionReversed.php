<?php

declare(strict_types=1);

namespace Domain\Ledger\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class TransactionReversed implements DomainEvent
{
    /**
     * @param array<array<string, mixed>> $balanceRestorations
     */
    public function __construct(
        private string $transactionId,
        private string $reason,
        private string $reversedBy,
        private array $balanceRestorations,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'ledger.transaction_reversed';
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
            'transaction_id' => $this->transactionId,
            'reason' => $this->reason,
            'reversed_by' => $this->reversedBy,
            'balance_restorations' => $this->balanceRestorations,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    public function transactionId(): string
    {
        return $this->transactionId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function balanceRestorations(): array
    {
        return $this->balanceRestorations;
    }
}
