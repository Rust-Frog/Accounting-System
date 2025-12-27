<?php

declare(strict_types=1);

namespace Domain\Approval\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class ApprovalRequested implements DomainEvent
{
    /**
     * @param array<string, mixed> $reason
     */
    public function __construct(
        private string $approvalId,
        private string $companyId,
        private string $approvalType,
        private string $entityType,
        private string $entityId,
        private array $reason,
        private string $requestedBy,
        private int $priority,
        private ?DateTimeImmutable $expiresAt,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'approval.requested';
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
            'company_id' => $this->companyId,
            'approval_type' => $this->approvalType,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'reason' => $this->reason,
            'requested_by' => $this->requestedBy,
            'priority' => $this->priority,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
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
