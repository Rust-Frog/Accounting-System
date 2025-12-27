<?php

declare(strict_types=1);

namespace Application\Dto\Approval;

use Application\Dto\DtoInterface;

/**
 * DTO representing an approval for external consumption.
 */
final readonly class ApprovalDto implements DtoInterface
{
    public function __construct(
        public string $id,
        public string $entityType,
        public string $entityId,
        public string $approvalType,
        public string $status,
        public string $requestedBy,
        public ?string $processedBy,
        public ?string $reason,
        public string $requestedAt,
        public ?string $processedAt,
        public ?string $expiresAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'approval_type' => $this->approvalType,
            'status' => $this->status,
            'requested_by' => $this->requestedBy,
            'processed_by' => $this->processedBy,
            'reason' => $this->reason,
            'requested_at' => $this->requestedAt,
            'processed_at' => $this->processedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
