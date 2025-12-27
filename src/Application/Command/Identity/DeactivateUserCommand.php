<?php

declare(strict_types=1);

namespace Application\Command\Identity;

use Application\Command\CommandInterface;

/**
 * Command to deactivate an active user.
 */
final readonly class DeactivateUserCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
