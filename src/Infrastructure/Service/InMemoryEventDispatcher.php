<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Event\EventDispatcherInterface;

/**
 * Simple in-memory event dispatcher implementation.
 * In a real application, this would integrate with a message queue.
 */
final class InMemoryEventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    /** @var array<DomainEvent> */
    private array $dispatchedEvents = [];

    /**
     * Register a listener for a specific event type.
     *
     * @param string $eventClass
     * @param callable $listener
     */
    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch a domain event to all registered listeners.
     */
    public function dispatch(DomainEvent $event): void
    {
        $this->dispatchedEvents[] = $event;

        // Dispatch to specific listeners and wildcard listeners
        $this->notifyListeners(get_class($event), $event);
        $this->notifyListeners('*', $event);
    }

    /**
     * Notify listeners for a specific event key (class name or wildcard).
     */
    private function notifyListeners(string $eventKey, DomainEvent $event): void
    {
        if (isset($this->listeners[$eventKey])) {
            foreach ($this->listeners[$eventKey] as $listener) {
                $listener($event);
            }
        }
    }

    /**
     * Dispatch multiple events.
     *
     * @param array<DomainEvent> $events
     */
    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    /**
     * Get all dispatched events (useful for testing).
     *
     * @return array<DomainEvent>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Clear dispatched events (useful for testing).
     */
    public function clearDispatchedEvents(): void
    {
        $this->dispatchedEvents = [];
    }

    /**
     * Clear all listeners.
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
    }
}
