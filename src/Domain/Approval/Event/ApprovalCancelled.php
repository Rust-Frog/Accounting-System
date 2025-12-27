<?php

declare(strict_types=1);

namespace Domain\Approval\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class ApprovalCancelled implements DomainEvent
{
    public function __construct(
        private string $approvalId,
        private string $entityType,
        private string $entityId,
        private string $cancelledBy,
        private string $reason,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'approval.cancelled';
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
            'cancelled_by' => $this->cancelledBy,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    public function approvalId(): string
    {
        return $this->approvalId;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
