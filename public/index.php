<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Api\Controller\AccountController;
use Api\Controller\ApprovalController;
use Api\Controller\AuthController;
use Api\Controller\CompanyController;
use Api\Controller\ReportController;
use Api\Controller\TransactionController;
use Api\Middleware\AuthenticationMiddleware;
use Api\Middleware\CorsMiddleware;
use Api\Middleware\ErrorHandlerMiddleware;
use Api\Request\ServerRequest;
use Api\Router;
use Application\Handler\Transaction\CreateTransactionHandler;
use Application\Handler\Transaction\PostTransactionHandler;
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

// Create controllers
$authController = new AuthController(
    $container->get(AuthenticationServiceInterface::class),
    $container->get(UserRepositoryInterface::class)
);

$companyController = new CompanyController(
    $container->get(CompanyRepositoryInterface::class)
);

$accountController = new AccountController(
    $container->get(AccountRepositoryInterface::class)
);

$transactionController = new TransactionController(
    $container->get(TransactionRepositoryInterface::class),
    $container->get(CreateTransactionHandler::class),
    $container->get(PostTransactionHandler::class),
    $container->get(VoidTransactionHandler::class)
);

$approvalController = new ApprovalController(
    $container->get(ApprovalRepositoryInterface::class)
);

$reportController = new ReportController(
    $container->get(ReportRepositoryInterface::class)
);

// Create router
$router = new Router();

// Add middleware
$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
$router->addMiddleware(new ErrorHandlerMiddleware($debug));
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new \Api\Middleware\InputSanitizationMiddleware());
$router->addMiddleware(new AuthenticationMiddleware(
    $container->get(AuthenticationServiceInterface::class),
    $container->get(\Infrastructure\Service\JwtService::class),
    ['/api/v1/auth/register', '/api/v1/auth/login', '/api/v1/', '/']
));
$router->addMiddleware(new \Api\Middleware\RoleEnforcementMiddleware());
$router->addMiddleware(new \Api\Middleware\CompanyScopingMiddleware(
    $container->get(UserRepositoryInterface::class)
));

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
    ],
]));

$router->get('/api/v1/', fn() => new \Api\Response\JsonResponse([
    'status' => 'success',
    'message' => 'Accounting System API v1',
    'version' => '1.0.0',
]));

// Auth routes
$router->post('/api/v1/auth/register', [$authController, 'register']);
$router->post('/api/v1/auth/login', [$authController, 'login']);
$router->post('/api/v1/auth/logout', [$authController, 'logout']);
$router->get('/api/v1/auth/me', [$authController, 'me']);

// Company routes
$router->post('/api/v1/companies', [$companyController, 'create']);
$router->get('/api/v1/companies/{id}', [$companyController, 'get']);

// Account routes
$router->get('/api/v1/companies/{companyId}/accounts', [$accountController, 'list']);
$router->get('/api/v1/companies/{companyId}/accounts/{id}', [$accountController, 'get']);
$router->post('/api/v1/companies/{companyId}/accounts', [$accountController, 'create']);

// Transaction routes
$router->get('/api/v1/companies/{companyId}/transactions', [$transactionController, 'list']);
$router->get('/api/v1/companies/{companyId}/transactions/{id}', [$transactionController, 'get']);
$router->post('/api/v1/companies/{companyId}/transactions', [$transactionController, 'create']);
$router->post('/api/v1/companies/{companyId}/transactions/{id}/post', [$transactionController, 'post']);
$router->post('/api/v1/companies/{companyId}/transactions/{id}/void', [$transactionController, 'void']);

// Approval routes
$router->get('/api/v1/companies/{companyId}/approvals/pending', [$approvalController, 'pending']);
$router->post('/api/v1/companies/{companyId}/approvals/{id}/approve', [$approvalController, 'approve']);
$router->post('/api/v1/companies/{companyId}/approvals/{id}/reject', [$approvalController, 'reject']);

// Report routes
$router->get('/api/v1/companies/{companyId}/reports', [$reportController, 'list']);
$router->get('/api/v1/companies/{companyId}/reports/{id}', [$reportController, 'get']);

// Dispatch request
$request = ServerRequest::fromGlobals();
$response = $router->dispatch($request);
$response->send();
