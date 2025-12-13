<?php

declare(strict_types=1);

namespace Domain\Identity\Event;

use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;

final class UserAuthenticated implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly UserId $userId,
        private readonly ?string $ipAddress = null
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.authenticated';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId->toString(),
            'occurred_on' => $this->occurredOn->format('Y-m-d H:i:s'),
        ];

        if ($this->ipAddress !== null) {
            $data['ip_address'] = $this->ipAddress;
        }

        return $data;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }
}
