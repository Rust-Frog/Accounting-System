<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function test_event_dispatcher_interface_has_dispatch_method(): void
    {
        $dispatcher = new class implements EventDispatcher {
            public array $dispatchedEvents = [];

            public function dispatch(DomainEvent $event): void
            {
                $this->dispatchedEvents[] = $event;
            }
        };

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
                return [];
            }
        };

        $dispatcher->dispatch($event);

        $this->assertCount(1, $dispatcher->dispatchedEvents);
        $this->assertSame($event, $dispatcher->dispatchedEvents[0]);
    }
}
