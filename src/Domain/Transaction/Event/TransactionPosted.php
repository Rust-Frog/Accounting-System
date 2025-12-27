<?php

declare(strict_types=1);

namespace Domain\Transaction\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class TransactionPosted implements DomainEvent
{
    public function __construct(
        private string $transactionId,
        private string $companyId,
        private string $postedBy,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'transaction.posted';
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
            'posted_by' => $this->postedBy,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
