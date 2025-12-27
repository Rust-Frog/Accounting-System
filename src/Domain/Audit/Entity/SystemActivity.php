<?php

declare(strict_types=1);

namespace Domain\Audit\Entity;

use DateTimeImmutable;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\HashChain\ChainLink;
use Domain\Shared\ValueObject\HashChain\ContentHash;

/**
 * Immutable System-Wide Activity Log Entry.
 * 
 * Provides a blockchain-style, cryptographically linked record of all system activities.
 * Unlike company-scoped ActivityLog, this tracks GLOBAL system activities.
 */
final class SystemActivity
{
    private function __construct(
        private readonly ActivityId $id,
        private readonly int $sequenceNumber,
        private readonly ?ActivityId $previousId,
        private readonly ?UserId $actorUserId,
        private readonly ?string $actorUsername,
        private readonly ?string $actorIpAddress,
        private readonly string $activityType,
        private readonly string $severity,
        private readonly string $entityType,
        private readonly string $entityId,
        private readonly string $description,
        private readonly ?array $metadata,
        private readonly ContentHash $contentHash,
        private readonly ?ContentHash $previousHash,
        private readonly ContentHash $chainHash,
        private readonly DateTimeImmutable $createdAt
    ) {
    }

    /**
     * Create a new system activity with hash chain.
     */
    public static function create(
        ?UserId $actorUserId,
        ?string $actorUsername,
        ?string $actorIpAddress,
        string $activityType,
        string $severity,
        string $entityType,
        string $entityId,
        string $description,
        ?array $metadata,
        ?ContentHash $previousHash,
        ?ActivityId $previousId
    ): self {
        $id = ActivityId::generate();
        $createdAt = new DateTimeImmutable();
        
        // Compute content hash from business data
        $contentHash = ContentHash::fromArray([
            'id' => $id->toString(),
            'actor_user_id' => $actorUserId?->toString(),
            'actor_username' => $actorUsername,
            'activity_type' => $activityType,
            'severity' => $severity,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $metadata,
            'created_at' => $createdAt->format('Y-m-d H:i:s.u'),
        ]);
        
        // Compute chain hash (links to previous)
        $chainLink = new ChainLink(
            $previousHash ?? ContentHash::genesis('system'),
            $contentHash,
            $createdAt
        );
        $chainHash = $chainLink->computeHash();
        
        return new self(
            id: $id,
            sequenceNumber: 0, // Will be set by database
            previousId: $previousId,
            actorUserId: $actorUserId,
            actorUsername: $actorUsername,
            actorIpAddress: $actorIpAddress,
            activityType: $activityType,
            severity: $severity,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            metadata: $metadata,
            contentHash: $contentHash,
            previousHash: $previousHash,
            chainHash: $chainHash,
            createdAt: $createdAt
        );
    }

    /**
     * Reconstitute from database.
     */
    public static function reconstitute(
        ActivityId $id,
        int $sequenceNumber,
        ?ActivityId $previousId,
        ?UserId $actorUserId,
        ?string $actorUsername,
        ?string $actorIpAddress,
        string $activityType,
        string $severity,
        string $entityType,
        string $entityId,
        string $description,
        ?array $metadata,
        ContentHash $contentHash,
        ?ContentHash $previousHash,
        ContentHash $chainHash,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: $id,
            sequenceNumber: $sequenceNumber,
            previousId: $previousId,
            actorUserId: $actorUserId,
            actorUsername: $actorUsername,
            actorIpAddress: $actorIpAddress,
            activityType: $activityType,
            severity: $severity,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            metadata: $metadata,
            contentHash: $contentHash,
            previousHash: $previousHash,
            chainHash: $chainHash,
            createdAt: $createdAt
        );
    }

    // Getters
    public function id(): ActivityId { return $this->id; }
    public function sequenceNumber(): int { return $this->sequenceNumber; }
    public function previousId(): ?ActivityId { return $this->previousId; }
    public function actorUserId(): ?UserId { return $this->actorUserId; }
    public function actorUsername(): ?string { return $this->actorUsername; }
    public function actorIpAddress(): ?string { return $this->actorIpAddress; }
    public function activityType(): string { return $this->activityType; }
    public function severity(): string { return $this->severity; }
    public function entityType(): string { return $this->entityType; }
    public function entityId(): string { return $this->entityId; }
    public function description(): string { return $this->description; }
    public function metadata(): ?array { return $this->metadata; }
    public function contentHash(): ContentHash { return $this->contentHash; }
    public function previousHash(): ?ContentHash { return $this->previousHash; }
    public function chainHash(): ContentHash { return $this->chainHash; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'sequence_number' => $this->sequenceNumber,
            'actor_user_id' => $this->actorUserId?->toString(),
            'actor_username' => $this->actorUsername,
            'activity_type' => $this->activityType,
            'severity' => $this->severity,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Verify chain integrity.
     */
    public function verifyChain(ContentHash $expectedPreviousHash): bool
    {
        if ($this->previousHash === null) {
            // Genesis entry
            return $expectedPreviousHash->equals(ContentHash::genesis('system'));
        }
        return $this->previousHash->equals($expectedPreviousHash);
    }
}
