<?php

declare(strict_types=1);

namespace Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Identity\ValueObject\SessionId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleException;

final class Session
{
    private const SESSION_DURATION_HOURS = 24;

    private function __construct(
        private readonly SessionId $sessionId,
        private readonly UserId $userId,
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private bool $isActive,
        private DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $lastActivityAt,
        private readonly DateTimeImmutable $createdAt
    ) {
    }

    public static function create(
        UserId $userId,
        string $ipAddress,
        string $userAgent
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            sessionId: SessionId::generate(),
            userId: $userId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            isActive: true,
            expiresAt: $now->modify('+' . self::SESSION_DURATION_HOURS . ' hours'),
            lastActivityAt: $now,
            createdAt: $now
        );
    }

    public function refresh(): void
    {
        if (!$this->isActive) {
            throw new BusinessRuleException('Cannot refresh terminated session');
        }

        $this->expiresAt = (new DateTimeImmutable())->modify('+' . self::SESSION_DURATION_HOURS . ' hours');
        $this->lastActivityAt = new DateTimeImmutable();
    }

    public function terminate(): void
    {
        $this->isActive = false;
    }

    public function recordActivity(): void
    {
        $this->lastActivityAt = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return !$this->isActive || $this->expiresAt <= new DateTimeImmutable();
    }

    // Getters
    public function id(): SessionId
    {
        return $this->sessionId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function lastActivityAt(): ?DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
