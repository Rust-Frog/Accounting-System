<?php

declare(strict_types=1);

namespace Domain\Approval\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class ApprovalExpired implements DomainEvent
{
    public function __construct(
        private string $approvalId,
        private string $entityType,
        private string $entityId,
        private ?DateTimeImmutable $expiresAt,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'approval.expired';
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
            'approval_id' => $this->approvalId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    public function approvalId(): string
    {
        return $this->approvalId;
    }
}
