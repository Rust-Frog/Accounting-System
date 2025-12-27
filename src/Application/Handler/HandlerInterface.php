<?php

declare(strict_types=1);

namespace Application\Handler;

use Application\Command\CommandInterface;

/**
 * Interface for command handlers.
 * Each handler processes a specific command type.
 *
 * @template T of CommandInterface
 */
interface HandlerInterface
{
    /**
     * Handle the given command.
     *
     * @param T $command
     * @return mixed Result of command execution
     */
    public function handle(CommandInterface $command): mixed;
}
