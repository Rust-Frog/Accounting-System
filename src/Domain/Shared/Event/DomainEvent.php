<?php

declare(strict_types=1);

namespace Domain\Shared\Event;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredOn(): DateTimeImmutable;

    public function eventName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
