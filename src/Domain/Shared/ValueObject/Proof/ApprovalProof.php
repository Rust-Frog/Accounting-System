<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\Proof;

use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\HashChain\ContentHash;
use Domain\Shared\ValueObject\Uuid;
use JsonSerializable;

/**
 * Immutable cryptographic proof of an approval action.
 * Used to prove that a specific entity was approved at a specific time
 * by a specific approver, and that the entity has not been modified since.
 */
final class ApprovalProof implements JsonSerializable
{
    private function __construct(
        private readonly string $proofId,
        private readonly string $entityType,
        private readonly string $entityId,
        private readonly string $approvalType,
        private readonly UserId $approverId,
        private readonly ContentHash $entityHash,
        private readonly DateTimeImmutable $approvedAt,
        private readonly ?string $notes
    ) {
    }

    /**
     * Create a new approval proof with current timestamp.
     */
    public static function create(
        string $entityType,
        string $entityId,
        string $approvalType,
        UserId $approverId,
        ContentHash $entityHash,
        ?string $notes = null
    ): self {
        return new self(
            proofId: Uuid::generate()->toString(),
            entityType: $entityType,
            entityId: $entityId,
            approvalType: $approvalType,
            approverId: $approverId,
            entityHash: $entityHash,
            approvedAt: new DateTimeImmutable(),
            notes: $notes
        );
    }

    /**
     * Create with explicit timestamp (for testing/reconstruction).
     */
    public static function createWithTimestamp(
        string $entityType,
        string $entityId,
        string $approvalType,
        UserId $approverId,
        ContentHash $entityHash,
        DateTimeImmutable $approvedAt,
        ?string $notes = null
    ): self {
        return new self(
            proofId: Uuid::generate()->toString(),
            entityType: $entityType,
            entityId: $entityId,
            approvalType: $approvalType,
            approverId: $approverId,
            entityHash: $entityHash,
            approvedAt: $approvedAt,
            notes: $notes
        );
    }

    /**
     * Compute the hash of this proof (for chaining/verification).
     */
    public function computeProofHash(): ContentHash
    {
        return ContentHash::fromArray([
            'proof_id' => $this->proofId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'approval_type' => $this->approvalType,
            'approver_id' => $this->approverId->toString(),
            'entity_hash' => $this->entityHash->toString(),
            'approved_at' => $this->approvedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Verify that the entity has not changed since approval.
     */
    public function verify(ContentHash $currentEntityHash): bool
    {
        return $this->entityHash->equals($currentEntityHash);
    }

    // Getters (no setters - immutable)
    public function proofId(): string
    {
        return $this->proofId;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function approvalType(): string
    {
        return $this->approvalType;
    }

    public function approverId(): UserId
    {
        return $this->approverId;
    }

    public function entityHash(): ContentHash
    {
        return $this->entityHash;
    }

    public function approvedAt(): DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'proof_id' => $this->proofId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'approval_type' => $this->approvalType,
            'approver_id' => $this->approverId->toString(),
            'entity_hash' => $this->entityHash->toString(),
            'approved_at' => $this->approvedAt->format('Y-m-d H:i:s.u'),
            'notes' => $this->notes,
            'proof_hash' => $this->computeProofHash()->toString(),
        ];
    }
}
