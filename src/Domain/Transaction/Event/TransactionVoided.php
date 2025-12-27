<?php

declare(strict_types=1);

namespace Domain\Transaction\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class TransactionVoided implements DomainEvent
{
    public function __construct(
        private string $transactionId,
        private string $companyId,
        private string $voidReason,
        private string $voidedBy,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'transaction.voided';
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
            'company_id' => $this->companyId,
            'void_reason' => $this->voidReason,
            'voided_by' => $this->voidedBy,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
