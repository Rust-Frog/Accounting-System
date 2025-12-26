<?php

declare(strict_types=1);

namespace Domain\Company\ValueObject;

enum CompanyStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DEACTIVATED = 'deactivated';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isDeactivated(): bool
    {
        return $this === self::DEACTIVATED;
    }

    public function canOperate(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canBeActivated(): bool
    {
        return $this === self::PENDING;
    }

    public function canBeSuspended(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canBeReactivated(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function canBeDeactivated(): bool
    {
        return $this === self::ACTIVE || $this === self::SUSPENDED;
    }
}
