<?php

declare(strict_types=1);

namespace Application\Command\Identity;

use Application\Command\CommandInterface;

/**
 * Command to activate a deactivated user.
 */
final readonly class ActivateUserCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
