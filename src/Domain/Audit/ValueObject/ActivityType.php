<?php

declare(strict_types=1);

namespace Domain\Audit\ValueObject;

enum ActivityType: string
{
    // Authentication
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case LOGIN_FAILED = 'login_failed';
    case PASSWORD_CHANGED = 'password_changed';

    // User Management
    case USER_CREATED = 'user_created';
    case USER_UPDATED = 'user_updated';
    case USER_DEACTIVATED = 'user_deactivated';
    case ROLE_CHANGED = 'role_changed';

    // Company Management
    case COMPANY_CREATED = 'company_created';
    case COMPANY_UPDATED = 'company_updated';
    case COMPANY_DEACTIVATED = 'company_deactivated';
    case SETTINGS_CHANGED = 'settings_changed';

    // Chart of Accounts
    case ACCOUNT_CREATED = 'account_created';
    case ACCOUNT_UPDATED = 'account_updated';
    case ACCOUNT_DEACTIVATED = 'account_deactivated';

    // Transactions
    case TRANSACTION_CREATED = 'transaction_created';
    case TRANSACTION_POSTED = 'transaction_posted';
    case TRANSACTION_VOIDED = 'transaction_voided';
    case TRANSACTION_EDITED = 'transaction_edited';

    // Approvals
    case APPROVAL_REQUESTED = 'approval_requested';
    case APPROVAL_GRANTED = 'approval_granted';
    case APPROVAL_DENIED = 'approval_denied';

    // Reports
    case REPORT_GENERATED = 'report_generated';
    case REPORT_EXPORTED = 'report_exported';

    // System
    case SYSTEM_ERROR = 'system_error';
    case DATA_EXPORTED = 'data_exported';
    case BACKUP_CREATED = 'backup_created';

    // Security
    case SECURITY_ACCESS_DENIED = 'security_access_denied';

}
