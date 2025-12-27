<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

enum Role: string
{
    case ADMIN = 'admin';
    case TENANT = 'tenant';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function canApprove(): bool
    {
        return $this === self::ADMIN;
    }
}
