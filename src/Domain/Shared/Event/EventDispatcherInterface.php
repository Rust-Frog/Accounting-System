<?php

declare(strict_types=1);

namespace Domain\Shared\Event;

/**
 * Interface for dispatching domain events.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch a domain event to all registered listeners.
     */
    public function dispatch(DomainEvent $event): void;
}
