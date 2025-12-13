<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function test_domain_event_interface_has_required_methods(): void
    {
        $event = new class implements DomainEvent {
            private DateTimeImmutable $occurredOn;

            public function __construct()
            {
                $this->occurredOn = new DateTimeImmutable();
            }

            public function occurredOn(): DateTimeImmutable
            {
                return $this->occurredOn;
            }

            public function eventName(): string
            {
                return 'test.event';
            }

            public function toArray(): array
            {
                return [
                    'event_name' => $this->eventName(),
                    'occurred_on' => $this->occurredOn->format('Y-m-d H:i:s'),
                ];
            }
        };

        $this->assertInstanceOf(DomainEvent::class, $event);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->occurredOn());
        $this->assertEquals('test.event', $event->eventName());
        $this->assertIsArray($event->toArray());
    }
}
