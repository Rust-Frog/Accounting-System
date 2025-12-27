<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

enum RegistrationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DECLINED = 'declined';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function canAuthenticate(): bool
    {
        return $this === self::APPROVED;
    }
}
