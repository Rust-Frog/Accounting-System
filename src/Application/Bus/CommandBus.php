<?php

declare(strict_types=1);

namespace Application\Bus;

use Application\Command\CommandInterface;
use Application\Handler\HandlerInterface;

/**
 * Simple command bus implementation.
 * Routes commands to their respective handlers.
 */
final class CommandBus
{
    /** @var array<class-string<CommandInterface>, HandlerInterface> */
    private array $handlers = [];

    /**
     * Register a handler for a specific command type.
     *
     * @param class-string<CommandInterface> $commandClass
     */
    public function registerHandler(string $commandClass, HandlerInterface $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Dispatch a command to its handler.
     *
     * @throws \RuntimeException If no handler is registered for the command
     */
    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = $command::class;

        if (!isset($this->handlers[$commandClass])) {
            throw new \RuntimeException(
                sprintf('No handler registered for command: %s', $commandClass)
            );
        }

        return $this->handlers[$commandClass]->handle($command);
    }
}
