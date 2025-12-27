<?php

declare(strict_types=1);

namespace Application\Command\Identity;

use Application\Command\CommandInterface;

/**
 * Command to authenticate a user.
 */
final readonly class AuthenticateCommand implements CommandInterface
{
    public function __construct(
        public string $username,
        public string $password,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }
}
