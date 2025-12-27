<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Api\Controller\AccountController;
use Api\Controller\ApprovalController;
use Api\Controller\AuthController;
use Api\Controller\CompanyController;
use Api\Controller\ReportController;
use Api\Controller\SetupController;
use Api\Controller\TransactionController;
use Api\Controller\HealthController;
use Api\Middleware\AuthenticationMiddleware;
use Api\Middleware\ApiVersionMiddleware;
use Api\Middleware\RequestLoggingMiddleware;
use Api\Middleware\CorsMiddleware;
use Api\Middleware\ErrorHandlerMiddleware;
use Api\Request\ServerRequest;
use Api\Router;
use Application\Handler\Transaction\CreateTransactionHandler;
use Application\Handler\Transaction\DeleteTransactionHandler;
use Application\Handler\Transaction\PostTransactionHandler;
use Application\Handler\Transaction\UpdateTransactionHandler;
use Application\Handler\Transaction\VoidTransactionHandler;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\Service\AuthenticationServiceInterface;
use Domain\Reporting\Repository\ReportRepositoryInterface;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Infrastructure\Container\ContainerBuilder;

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Build container
$container = ContainerBuilder::build();

// Redis Client
// Redis Client
$redis = $container->get(\Predis\ClientInterface::class);

// Create controllers
$authController = new AuthController(
    $container->get(AuthenticationServiceInterface::class),
    $container->get(UserRepositoryInterface::class),
    $container->get(\Infrastructure\Service\TotpService::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class)
);

$companyController = new CompanyController(
    $container->get(CompanyRepositoryInterface::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class),
    $container->get(\Api\Validation\CompanyValidation::class),
    $container->get(\Api\Middleware\AuthorizationGuard::class),
    $container->get(PDO::class)
);

$accountController = new AccountController(
    $container->get(AccountRepositoryInterface::class),
    $container->get(TransactionRepositoryInterface::class),
    $container->get(CompanyRepositoryInterface::class),
    $container->get(PDO::class),
    $container->get(\Api\Validation\AccountValidation::class),
    $container->get(\Api\Middleware\AuthorizationGuard::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class)
);

$transactionController = new TransactionController(
    $container->get(TransactionRepositoryInterface::class),
    $container->get(CreateTransactionHandler::class),
    $container->get(UpdateTransactionHandler::class),
    $container->get(DeleteTransactionHandler::class),
    $container->get(PostTransactionHandler::class),
    $container->get(VoidTransactionHandler::class),
    $container->get(PDO::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class),
    $container->get(\Api\Validation\TransactionValidation::class),
    $container->get(\Api\Middleware\AuthorizationGuard::class)
);

$approvalController = new ApprovalController(
    $container->get(ApprovalRepositoryInterface::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class),
    $container->get(\Domain\Reporting\Repository\ClosedPeriodRepositoryInterface::class)
);

$auditController = new \Api\Controller\AuditController(
    $container->get(\Domain\Audit\Service\SystemActivityService::class)
);

$reportController = new ReportController(
    $container->get(ReportRepositoryInterface::class),
    $container->get(\Application\Handler\Reporting\GenerateReportHandler::class)
);

$setupController = new SetupController(
    $container->get(UserRepositoryInterface::class),
    $container->get(\Application\Handler\Admin\SetupAdminHandler::class),
    $container->get(\Infrastructure\Service\TotpService::class)
);

$dashboardController = new \Api\Controller\DashboardController(
    $container->get(TransactionRepositoryInterface::class),
    $container->get(ApprovalRepositoryInterface::class),
    $container->get(AccountRepositoryInterface::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class)
);

$healthController = new HealthController(
    $container->get(PDO::class),
    $container->get(\Predis\ClientInterface::class)
);

$healthController = new \Api\Controller\HealthController(
    $container->get(PDO::class),
    $container->get(\Predis\ClientInterface::class)
);

$transactionValidationController = new \Api\Controller\TransactionValidationController(
    $container->get(\Domain\Transaction\Service\TransactionValidationService::class),
    $container->get(\Domain\Transaction\Service\EdgeCaseDetectionServiceInterface::class)
);

// Create router
$router = new Router();

// Add middleware
$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
$router->addMiddleware(new ErrorHandlerMiddleware($debug));
$router->addMiddleware(new \Api\Middleware\RequestIdMiddleware());
$router->addMiddleware(new ApiVersionMiddleware());
$router->addMiddleware(new RequestLoggingMiddleware($container->get(\Domain\Audit\Service\SystemActivityService::class)));
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new \Api\Middleware\SecurityHeadersMiddleware());
$router->addMiddleware(new \Api\Middleware\InputSanitizationMiddleware());
$router->addMiddleware(new AuthenticationMiddleware(
    $container->get(AuthenticationServiceInterface::class),
    $container->get(\Domain\Identity\Repository\UserRepositoryInterface::class),
    $container->get(\Infrastructure\Service\JwtService::class),
    [
        '/',                        // Root info
        '/health',                  // Health check (no auth needed)
        '/api/v1/auth/register',    // Registration
        '/api/v1/auth/login',       // Login
        '/api/v1/setup/',           // Setup routes (handled by SetupMiddleware)
    ]
));
$router->addMiddleware(new \Api\Middleware\RateLimitMiddleware($redis));
$router->addMiddleware($container->get(\Api\Middleware\RoleEnforcementMiddleware::class));
$router->addMiddleware(new \Api\Middleware\CompanyScopingMiddleware());
$router->addMiddleware($container->get(\Api\Middleware\SetupMiddleware::class));

// API Info route
$router->get('/', fn() => \Api\Response\JsonResponse::success([
    'service' => 'Accounting System API',
    'version' => '2.0.0',
    'endpoints' => [
        'auth' => '/api/v1/auth',
        'companies' => '/api/v1/companies',
        'accounts' => '/api/v1/companies/{companyId}/accounts',
        'transactions' => '/api/v1/companies/{companyId}/transactions',
        'approvals' => '/api/v1/companies/{companyId}/approvals',
        'reports' => '/api/v1/companies/{companyId}/reports',
        'health' => '/health',
    ],
]));

// Health check route (for container orchestration)
$router->get('/health', [$healthController, 'check']);

$router->get('/api/v1/', fn() => new \Api\Response\JsonResponse([
    'status' => 'success',
    'message' => 'Accounting System API v1',
    'version' => '1.0.0',
]));

// Health routes
$router->get('/health', [$healthController, 'check']);
$router->get('/api/v1/health', [$healthController, 'check']);

// Auth routes
$router->post('/api/v1/auth/register', [$authController, 'register']);
$router->post('/api/v1/auth/login', [$authController, 'login']);
$router->post('/api/v1/auth/logout', [$authController, 'logout']);
$router->get('/api/v1/auth/me', [$authController, 'me']);

// Setup routes
$router->get('/api/v1/setup/status', [$setupController, 'status']);
$router->post('/api/v1/setup/init', [$setupController, 'init']);
$router->post('/api/v1/setup/complete', [$setupController, 'complete']);

// Dashboard routes (system-wide, no company scoping)
$router->get('/api/v1/dashboard/stats', [$dashboardController, 'stats']);
$router->get('/api/v1/dashboard/recent-approvals', [$dashboardController, 'recentApprovals']);
$router->get('/api/v1/activities', [$dashboardController, 'recentActivities']);

// Company routes
$router->get('/api/v1/companies', [$companyController, 'list']);
$router->post('/api/v1/companies', [$companyController, 'create']);
$router->get('/api/v1/companies/{id}', [$companyController, 'get']);
$router->post('/api/v1/companies/{id}/activate', [$companyController, 'activate']);
$router->post('/api/v1/companies/{id}/suspend', [$companyController, 'suspend']);
$router->post('/api/v1/companies/{id}/reactivate', [$companyController, 'reactivate']);
$router->post('/api/v1/companies/{id}/deactivate', [$companyController, 'deactivate']);

// Account routes
$router->get('/api/v1/companies/{companyId}/accounts', [$accountController, 'list']);
$router->get('/api/v1/companies/{companyId}/accounts/{id}', [$accountController, 'get']);
$router->get('/api/v1/companies/{companyId}/accounts/{id}/transactions', [$accountController, 'transactions']);
$router->post('/api/v1/companies/{companyId}/accounts', [$accountController, 'create']);
$router->put('/api/v1/companies/{companyId}/accounts/{id}', [$accountController, 'update']);
$router->post('/api/v1/companies/{companyId}/accounts/{id}/toggle', [$accountController, 'toggle']);

// Transaction routes
$router->get('/api/v1/companies/{companyId}/transactions', [$transactionController, 'list']);
$router->get('/api/v1/companies/{companyId}/transactions/{id}', [$transactionController, 'get']);
$router->post('/api/v1/companies/{companyId}/transactions', [$transactionController, 'create']);
$router->put('/api/v1/companies/{companyId}/transactions/{id}', [$transactionController, 'update']);
$router->delete('/api/v1/companies/{companyId}/transactions/{id}', [$transactionController, 'delete']);
$router->post('/api/v1/companies/{companyId}/transactions/{id}/post', [$transactionController, 'post']);
$router->post('/api/v1/companies/{companyId}/transactions/{id}/void', [$transactionController, 'void']);
$router->post('/api/v1/companies/{companyId}/transactions/validate', [$transactionValidationController, 'validate']);

// Approval routes
$router->get('/api/v1/companies/{companyId}/approvals/pending', [$approvalController, 'pending']);
$router->post('/api/v1/companies/{companyId}/approvals/{id}/approve', [$approvalController, 'approve']);
$router->post('/api/v1/companies/{companyId}/approvals/{id}/reject', [$approvalController, 'reject']);

// Audit routes (admin only)
$router->get('/api/v1/audit/verify', [$auditController, 'verify']);
$router->get('/api/v1/audit/stats', [$auditController, 'stats']);

// Report routes
$router->get('/api/v1/companies/{companyId}/reports', [$reportController, 'list']);
$router->get('/api/v1/companies/{companyId}/reports/{id}', [$reportController, 'get']);
$router->post('/api/v1/companies/{companyId}/reports/generate', [$reportController, 'generate']);

// Journal routes
$journalController = new \Api\Controller\JournalController(
    $container->get(\Domain\Ledger\Repository\JournalEntryRepositoryInterface::class)
);
$router->get('/api/v1/companies/{companyId}/journal', [$journalController, 'list']);
$router->get('/api/v1/companies/{companyId}/journal/{id}', [$journalController, 'get']);

// Ledger routes
$ledgerController = new \Api\Controller\LedgerController(
    $container->get(AccountRepositoryInterface::class),
    $container->get(TransactionRepositoryInterface::class),
    $container->get(\Domain\Ledger\Repository\JournalEntryRepositoryInterface::class)
);
$router->get('/api/v1/companies/{companyId}/ledger', [$ledgerController, 'show']);
$router->get('/api/v1/companies/{companyId}/ledger/summary', [$ledgerController, 'summary']);

// Trial Balance routes
$trialBalanceController = new \Api\Controller\TrialBalanceController(
    $container->get(\Application\Handler\Reporting\GenerateTrialBalanceHandler::class)
);
$router->get('/api/v1/companies/{companyId}/trial-balance', [$trialBalanceController, 'generate']);

// Income Statement routes
$incomeStatementController = new \Api\Controller\IncomeStatementController(
    $container->get(\Application\Handler\Reporting\GenerateIncomeStatementHandler::class)
);
$router->get('/api/v1/companies/{companyId}/income-statement', [$incomeStatementController, 'generate']);

// Period Close routes
$periodCloseController = new \Api\Controller\PeriodCloseController(
    $container->get(\Domain\Approval\Repository\ApprovalRepositoryInterface::class)
);
$router->post('/api/v1/companies/{companyId}/period-close', [$periodCloseController, 'requestClose']);
$router->get('/api/v1/companies/{companyId}/period-close', [$periodCloseController, 'list']);

// Balance Sheet routes
$balanceSheetController = new \Api\Controller\BalanceSheetController(
    $container->get(\Application\Handler\Reporting\GenerateBalanceSheetHandler::class)
);
$router->get('/api/v1/companies/{companyId}/balance-sheet', [$balanceSheetController, 'generate']);

// User Management routes (admin only)
$userController = new \Api\Controller\UserController(
    $container->get(UserRepositoryInterface::class),
    $container->get(\Application\Handler\Identity\ApproveUserHandler::class),
    $container->get(\Application\Handler\Identity\DeclineUserHandler::class),
    $container->get(\Application\Handler\Identity\DeactivateUserHandler::class),
    $container->get(\Application\Handler\Identity\ActivateUserHandler::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class)
);
$router->get('/api/v1/users', [$userController, 'list']);
$router->post('/api/v1/users', [$userController, 'create']);
$router->get('/api/v1/users/{id}', [$userController, 'get']);
$router->post('/api/v1/users/{id}/approve', [$userController, 'approve']);
$router->post('/api/v1/users/{id}/decline', [$userController, 'decline']);
$router->post('/api/v1/users/{id}/deactivate', [$userController, 'deactivate']);
$router->post('/api/v1/users/{id}/activate', [$userController, 'activate']);

// Audit Log routes (read-only)
$auditLogController = new \Api\Controller\AuditLogController(
    $container->get(\Domain\Audit\Repository\ActivityLogRepositoryInterface::class)
);
$router->get('/api/v1/companies/{companyId}/audit-logs', [$auditLogController, 'list']);
$router->get('/api/v1/companies/{companyId}/audit-logs/stats', [$auditLogController, 'stats']);
$router->get('/api/v1/companies/{companyId}/audit-logs/{id}', [$auditLogController, 'get']);

// Settings routes (current user)
$settingsController = new \Api\Controller\SettingsController(
    $container->get(\Application\Handler\Settings\UpdateSettingsHandler::class),
    $container->get(\Application\Handler\Settings\SecuritySettingsHandler::class),
    $container->get(UserRepositoryInterface::class),
    $container->get(\Domain\Audit\Service\SystemActivityService::class)
);
$router->get('/api/v1/settings', [$settingsController, 'get']);
$router->put('/api/v1/settings/theme', [$settingsController, 'updateTheme']);
$router->put('/api/v1/settings/localization', [$settingsController, 'updateLocalization']);
$router->put('/api/v1/settings/notifications', [$settingsController, 'updateNotifications']);
$router->put('/api/v1/settings/session', [$settingsController, 'updateSessionTimeout']);
$router->post('/api/v1/settings/password', [$settingsController, 'changePassword']);
$router->post('/api/v1/settings/otp/enable', [$settingsController, 'enableOtp']);
$router->post('/api/v1/settings/otp/verify', [$settingsController, 'verifyOtp']);
$router->post('/api/v1/settings/otp/disable', [$settingsController, 'disableOtp']);
$router->post('/api/v1/settings/backup-codes/regenerate', [$settingsController, 'regenerateBackupCodes']);

// Dispatch request
$request = ServerRequest::fromGlobals();
$response = $router->dispatch($request);
$response->send();
