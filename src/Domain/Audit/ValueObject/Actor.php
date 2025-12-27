<?php

declare(strict_types=1);

namespace Domain\Audit\ValueObject;

use Domain\Identity\ValueObject\UserId;

/**
 * Value object representing who performed an action.
 */
final readonly class Actor
{
    private function __construct(
        private ?string $userId,
        private string $actorType,
        private string $actorName,
        private ?string $impersonatedBy
    ) {
    }

    public static function user(UserId $userId, string $displayName): self
    {
        return new self($userId->toString(), 'user', $displayName, null);
    }

    public static function system(): self
    {
        return new self(null, 'system', 'SYSTEM', null);
    }

    public static function scheduler(): self
    {
        return new self(null, 'scheduler', 'SCHEDULER', null);
    }

    public static function impersonated(UserId $userId, string $displayName, UserId $impersonator): self
    {
        return new self($userId->toString(), 'user', $displayName, $impersonator->toString());
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function actorType(): string
    {
        return $this->actorType;
    }

    public function actorName(): string
    {
        return $this->actorName;
    }

    public function impersonatedBy(): ?string
    {
        return $this->impersonatedBy;
    }

    public function isSystem(): bool
    {
        return $this->actorType === 'system';
    }

    public function isImpersonated(): bool
    {
        return $this->impersonatedBy !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'actor_type' => $this->actorType,
            'actor_name' => $this->actorName,
            'impersonated_by' => $this->impersonatedBy,
        ];
    }
}
