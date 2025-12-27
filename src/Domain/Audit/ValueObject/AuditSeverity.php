<?php

declare(strict_types=1);

namespace Domain\Audit\ValueObject;

enum AuditSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';
    case SECURITY = 'security';

    public function isSecurityRelated(): bool
    {
        return $this === self::SECURITY || $this === self::CRITICAL;
    }

    public function requiresNotification(): bool
    {
        return $this === self::CRITICAL || $this === self::SECURITY;
    }
}
