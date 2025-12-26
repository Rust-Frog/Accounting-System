<?php

declare(strict_types=1);

namespace Application\Command\Identity;

use Application\Command\CommandInterface;

/**
 * Command to decline a pending user registration.
 */
final readonly class DeclineUserCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $declinerId,
    ) {
    }
}
