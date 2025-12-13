<?php

declare(strict_types=1);

namespace Domain\Shared\Event;

interface EventDispatcher
{
    public function dispatch(DomainEvent $event): void;
}
