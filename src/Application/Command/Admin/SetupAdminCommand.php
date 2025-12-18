<?php

declare(strict_types=1);

namespace Application\Command\Admin;

final class SetupAdminCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $password,
        public readonly string $otpSecret,
        public readonly string $otpCode
    ) {
    }
}
