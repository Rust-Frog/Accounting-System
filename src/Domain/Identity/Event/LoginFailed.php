<?php

declare(strict_types=1);

namespace Domain\Identity\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final class LoginFailed implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly string $username,
        private readonly ?string $ipAddress = null,
        private readonly ?string $reason = null
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'login.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'username' => $this->username,
            'occurred_on' => $this->occurredOn->format('Y-m-d H:i:s'),
        ];

        if ($this->ipAddress !== null) {
            $data['ip_address'] = $this->ipAddress;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
