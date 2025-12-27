<?php

declare(strict_types=1);

namespace Application\Command\Identity;

use Application\Command\CommandInterface;

/**
 * Command to register a new user.
 */
final readonly class RegisterUserCommand implements CommandInterface
{
    public function __construct(
        public string $username,
        public string $email,
        public string $password,
        public string $firstName,
        public string $lastName,
        public ?string $companyId = null,
    ) {
    }
}
