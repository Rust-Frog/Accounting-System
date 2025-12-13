<?php

declare(strict_types=1);

namespace Domain\Identity\Event;

use DateTimeImmutable;
use Domain\Identity\ValueObject\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\ValueObject\Email;

final class UserRegistered implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly UserId $userId,
        private readonly Email $email,
        private readonly Role $role
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.registered';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId->toString(),
            'email' => $this->email->toString(),
            'role' => $this->role->value,
            'occurred_on' => $this->occurredOn->format('Y-m-d H:i:s'),
        ];
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
