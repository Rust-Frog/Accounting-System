<?php

declare(strict_types=1);

namespace Domain\Company\ValueObject;

enum CompanyStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canOperate(): bool
    {
        return $this === self::ACTIVE;
    }
}
