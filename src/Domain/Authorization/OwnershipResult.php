<?php

declare(strict_types=1);

namespace Domain\Authorization;

/**
 * Result of an ownership verification check.
 */
final class OwnershipResult
{
    private function __construct(
        private readonly bool $isOwner,
        private readonly ?string $actualOwnerId,
        private readonly ?string $reason
    ) {
    }

    public static function owned(?string $ownerId = null): self
    {
        return new self(true, $ownerId, null);
    }

    public static function notOwned(string $reason, ?string $actualOwnerId = null): self
    {
        return new self(false, $actualOwnerId, $reason);
    }

    public static function notFound(string $resourceType, string $resourceId): self
    {
        return new self(false, null, "{$resourceType} with ID {$resourceId} not found");
    }

    public function isOwner(): bool
    {
        return $this->isOwner;
    }

    public function isNotOwner(): bool
    {
        return !$this->isOwner;
    }

    public function actualOwnerId(): ?string
    {
        return $this->actualOwnerId;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
