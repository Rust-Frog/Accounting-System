<?php

declare(strict_types=1);

namespace Domain\Audit\Service;

use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\AuditSeverity;

final class ActivityClassification
{
    private const CATEGORY_MAP = [
        ActivityType::LOGIN->value => 'authentication',
        ActivityType::LOGOUT->value => 'authentication',
        ActivityType::LOGIN_FAILED->value => 'authentication',
        ActivityType::PASSWORD_CHANGED->value => 'authentication',
        ActivityType::USER_CREATED->value => 'user_management',
        ActivityType::USER_UPDATED->value => 'user_management',
        ActivityType::USER_DEACTIVATED->value => 'user_management',
        ActivityType::ROLE_CHANGED->value => 'user_management',
        ActivityType::COMPANY_CREATED->value => 'company_management',
        ActivityType::COMPANY_UPDATED->value => 'company_management',
        ActivityType::COMPANY_DEACTIVATED->value => 'company_management',
        ActivityType::SETTINGS_CHANGED->value => 'company_management',
        ActivityType::ACCOUNT_CREATED->value => 'chart_of_accounts',
        ActivityType::ACCOUNT_UPDATED->value => 'chart_of_accounts',
        ActivityType::ACCOUNT_DEACTIVATED->value => 'chart_of_accounts',
        ActivityType::TRANSACTION_CREATED->value => 'transactions',
        ActivityType::TRANSACTION_POSTED->value => 'transactions',
        ActivityType::TRANSACTION_VOIDED->value => 'transactions',
        ActivityType::TRANSACTION_EDITED->value => 'transactions',
        ActivityType::APPROVAL_REQUESTED->value => 'approvals',
        ActivityType::APPROVAL_GRANTED->value => 'approvals',
        ActivityType::APPROVAL_DENIED->value => 'approvals',
        ActivityType::REPORT_GENERATED->value => 'reports',
        ActivityType::REPORT_EXPORTED->value => 'reports',
        ActivityType::SYSTEM_ERROR->value => 'system',
        ActivityType::DATA_EXPORTED->value => 'system',
        ActivityType::BACKUP_CREATED->value => 'system',
    ];

    private const SEVERITY_EXCEPTIONS = [
        ActivityType::LOGIN_FAILED->value => AuditSeverity::SECURITY,
        ActivityType::USER_DEACTIVATED->value => AuditSeverity::WARNING,
        ActivityType::COMPANY_DEACTIVATED->value => AuditSeverity::WARNING,
        ActivityType::ACCOUNT_DEACTIVATED->value => AuditSeverity::WARNING,
        ActivityType::TRANSACTION_VOIDED->value => AuditSeverity::WARNING,
        ActivityType::APPROVAL_DENIED->value => AuditSeverity::WARNING,
        ActivityType::SYSTEM_ERROR->value => AuditSeverity::WARNING,
    ];

    private const NOTIFICATION_EXCEPTIONS = [
        ActivityType::LOGIN_FAILED->value => true,
        ActivityType::SYSTEM_ERROR->value => true,
        ActivityType::DATA_EXPORTED->value => true,
    ];

    public static function getCategory(ActivityType $type): string
    {
        return self::CATEGORY_MAP[$type->value] ?? 'system';
    }

    public static function getSeverity(ActivityType $type): AuditSeverity
    {
        return self::SEVERITY_EXCEPTIONS[$type->value] ?? AuditSeverity::INFO;
    }

    public static function requiresAdminNotification(ActivityType $type): bool
    {
        return self::NOTIFICATION_EXCEPTIONS[$type->value] ?? false;
    }
}
