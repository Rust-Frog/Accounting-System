<?php

declare(strict_types=1);

namespace Domain\Approval\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class ApprovalGranted implements DomainEvent
{
    public function __construct(
        private string $approvalId,
        private string $entityType,
        private string $entityId,
        private string $approvedBy,
        private ?string $notes,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'approval.granted';
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
            'approved_by' => $this->approvedBy,
            'notes' => $this->notes,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    public function approvalId(): string
    {
        return $this->approvalId;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }
}
