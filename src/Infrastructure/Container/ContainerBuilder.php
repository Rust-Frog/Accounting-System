<?php

declare(strict_types=1);

namespace Infrastructure\Container;

use Application\Handler\Transaction\CreateTransactionHandler;
use Application\Handler\Transaction\DeleteTransactionHandler;
use Application\Handler\Transaction\PostTransactionHandler;
use Application\Handler\Transaction\UpdateTransactionHandler;
use Application\Handler\Transaction\VoidTransactionHandler;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Audit\Repository\ActivityLogRepositoryInterface;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\Service\AuthenticationServiceInterface;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Reporting\Repository\ReportRepositoryInterface;
use Domain\Shared\Event\EventDispatcherInterface;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Infrastructure\Persistence\Mysql\Repository\MysqlAccountRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlActivityLogRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlApprovalRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlCompanyRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlLedgerRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlReportRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlTransactionRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlUserRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlUserSettingsRepository;
use Domain\Identity\Repository\UserSettingsRepositoryInterface;
use Infrastructure\Persistence\Mysql\Connection\PdoConnectionFactory;
use Infrastructure\Service\BcryptPasswordHashingService;
use Infrastructure\Service\InMemoryEventDispatcher;
use Infrastructure\Service\JwtService;
use Infrastructure\Service\SessionAuthenticationService;
use Infrastructure\Authorization\OwnershipVerifier;
use Domain\Authorization\OwnershipVerifierInterface;
use Api\Validation\TransactionValidation;
use Api\Validation\CompanyValidation;
use Api\Validation\UserValidation;
use Api\Validation\AccountValidation;
use Api\Middleware\AuthorizationGuard;
use PDO;
use Psr\Container\ContainerInterface;

/**
 * Container factory for configuring all dependencies.
 */
final class ContainerBuilder
{
    /**
     * Build and configure the container with all dependencies.
     */
    public static function build(): Container
    {
        $container = new Container();

        self::registerDatabase($container);
        self::registerRepositories($container);
        self::registerServices($container);
        self::registerHandlers($container);

        return $container;
    }

    private static function registerDatabase(Container $container): void
    {
        // Database connection - use singleton factory to ensure single connection
        // This is critical for tests that wrap in transactions
        $container->singleton(PDO::class, function () {
            return PdoConnectionFactory::getConnection();
        });

        // Database connection factory
        $container->singleton(PdoConnectionFactory::class, fn() =>
            new PdoConnectionFactory()
        );

        // Redis Client
        $container->singleton(\Predis\ClientInterface::class, function () {
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = $_ENV['REDIS_PORT'] ?? 6379;
            return new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => $host,
                'port'   => $port,
            ]);
        });
    }

    private static function registerRepositories(Container $container): void
    {
        $container->singleton(UserRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlUserRepository($c->get(PDO::class))
        );

        $container->singleton(CompanyRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlCompanyRepository($c->get(PDO::class))
        );

        $container->singleton(AccountRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlAccountRepository($c->get(PDO::class))
        );

        $container->singleton(TransactionRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlTransactionRepository($c->get(PDO::class))
        );

        $container->singleton(LedgerRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlLedgerRepository($c->get(PDO::class))
        );

        $container->singleton(ApprovalRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlApprovalRepository($c->get(PDO::class))
        );

        $container->singleton(ActivityLogRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlActivityLogRepository($c->get(PDO::class))
        );

        $container->singleton(ReportRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlReportRepository($c->get(PDO::class))
        );

        $container->singleton(\Domain\Ledger\Repository\JournalEntryRepositoryInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Persistence\Mysql\Repository\MysqlJournalEntryRepository($c->get(PDO::class))
        );

        $container->singleton(\Domain\Ledger\Repository\BalanceChangeRepositoryInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Persistence\Mysql\Repository\MysqlBalanceChangeRepository($c->get(PDO::class))
        );

        $container->singleton(UserSettingsRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlUserSettingsRepository($c->get(PDO::class))
        );

        $container->singleton(\Domain\Reporting\Repository\ClosedPeriodRepositoryInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Persistence\Mysql\Repository\MysqlClosedPeriodRepository($c->get(PDO::class))
        );
    }

    private static function registerServices(Container $container): void
    {
        self::registerCoreServices($container);
        self::registerAuditServices($container);
        self::registerAuthServices($container);
        self::registerValidationServices($container);
        self::registerAuthorizationServices($container);
    }

    private static function registerCoreServices(Container $container): void
    {
        // Event Dispatcher and Listeners
        $container->singleton(EventDispatcherInterface::class, function (ContainerInterface $c) {
            $dispatcher = new \Infrastructure\Service\InMemoryEventDispatcher();
            
            // Register ActivityLogListener
            if ($c->has(\Application\Listener\ActivityLogListener::class)) {
                $listener = $c->get(\Application\Listener\ActivityLogListener::class);
                $dispatcher->addListener('*', $listener);
            }

            // Register BalanceUpdateListener
            if ($c->has(\Application\Listener\BalanceUpdateListener::class)) {
                $listener = $c->get(\Application\Listener\BalanceUpdateListener::class);
                $dispatcher->addListener(\Domain\Transaction\Event\TransactionPosted::class, $listener);
                $dispatcher->addListener(\Domain\Transaction\Event\TransactionVoided::class, $listener);
            }
            
            return $dispatcher;
        });

        // JWT Service
        $container->singleton(JwtService::class, function () {
            $secretKey = $_ENV['JWT_SECRET'] ?? null;
            $expiration = (int) ($_ENV['JWT_EXPIRATION'] ?? 3600);
            $issuer = $_ENV['JWT_ISSUER'] ?? 'accounting-api';

            // Fail fast if JWT secret is not configured
            if (empty($secretKey)) {
                throw new \RuntimeException('JWT_SECRET environment variable is required (use a strong random string)');
            }

            return new JwtService($secretKey, $expiration, $issuer);
        });

        // Transaction Number Generator
        $container->singleton(
            \Domain\Transaction\Service\TransactionNumberGeneratorInterface::class,
            fn(ContainerInterface $c) => new \Infrastructure\Service\TransactionNumberGenerator(
                $c->get(PDO::class)
            )
        );

        // Transaction Validation Service
        $container->singleton(
            \Domain\Transaction\Service\TransactionValidationService::class,
            fn(ContainerInterface $c) => new \Domain\Transaction\Service\TransactionValidationService(
                $c->get(\Domain\ChartOfAccounts\Repository\AccountRepositoryInterface::class)
            )
        );

        // Edge Case Threshold Repository
        $container->singleton(
            \Domain\Transaction\Repository\ThresholdRepositoryInterface::class,
            fn(ContainerInterface $c) => new \Infrastructure\Persistence\Mysql\Repository\MysqlThresholdRepository(
                $c->get(PDO::class)
            )
        );

        // Edge Case Detection Service
        $container->singleton(
            \Domain\Transaction\Service\EdgeCaseDetectionServiceInterface::class,
            fn(ContainerInterface $c) => new \Domain\Transaction\Service\EdgeCaseDetectionService(
                $c->get(\Domain\Transaction\Repository\ThresholdRepositoryInterface::class),
                $c->get(\Domain\ChartOfAccounts\Repository\AccountRepositoryInterface::class),
                $c->get(\Domain\Ledger\Repository\LedgerRepositoryInterface::class),
                $c->get(\Domain\Transaction\Repository\TransactionRepositoryInterface::class),
                new \Domain\Ledger\Service\BalanceCalculationService()
            )
        );

        // Report Export Service
        $container->singleton(\Domain\Reporting\Service\ReportExportService::class, fn() =>
            new \Domain\Reporting\Service\ReportExportService()
        );
    }

    private static function registerAuditServices(Container $container): void
    {
        $container->singleton(\Domain\Audit\Service\AuditChainServiceInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Service\AuditChainService(
                $c->get(\Domain\Audit\Repository\ActivityLogRepositoryInterface::class)
            )
        );

        $container->singleton(\Domain\Audit\Service\ActivityLogService::class, fn(ContainerInterface $c) =>
            new \Domain\Audit\Service\ActivityLogService(
                $c->get(\Domain\Audit\Repository\ActivityLogRepositoryInterface::class),
                $c->get(\Domain\Audit\Service\AuditChainServiceInterface::class)
            )
        );

        $container->singleton(\Application\Listener\ActivityLogListener::class, fn(ContainerInterface $c) =>
            new \Application\Listener\ActivityLogListener(
                $c->get(\Domain\Audit\Service\ActivityLogService::class),
                $c->get(\Domain\Identity\Repository\UserRepositoryInterface::class)
            )
        );

        $container->singleton(\Application\Listener\BalanceUpdateListener::class, fn(ContainerInterface $c) =>
            new \Application\Listener\BalanceUpdateListener(
                $c->get(\Domain\Transaction\Repository\TransactionRepositoryInterface::class),
                $c->get(\Domain\Ledger\Repository\LedgerRepositoryInterface::class),
                $c->get(\Domain\ChartOfAccounts\Repository\AccountRepositoryInterface::class)
            )
        );

        // System-wide Activity Service (Global Audit Trail)
        $container->singleton(\Domain\Audit\Repository\SystemActivityRepositoryInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Persistence\MySQL\MySQLSystemActivityRepository(
                $c->get(PDO::class)
            )
        );

        $container->singleton(\Domain\Audit\Service\SystemActivityService::class, fn(ContainerInterface $c) =>
            new \Domain\Audit\Service\SystemActivityService(
                $c->get(\Domain\Audit\Repository\SystemActivityRepositoryInterface::class)
            )
        );
    }

    private static function registerAuthServices(Container $container): void
    {
        $container->singleton('password_service', fn() =>
            new BcryptPasswordHashingService()
        );

        $container->singleton(AuthenticationServiceInterface::class, fn(ContainerInterface $c) =>
            new SessionAuthenticationService(
                $c->get(\Predis\ClientInterface::class),
                $c->get(UserRepositoryInterface::class),
                $c->get('password_service')
            )
        );

        $container->singleton(\Infrastructure\Service\TotpService::class, fn() =>
            new \Infrastructure\Service\TotpService()
        );

        $container->singleton(\Api\Middleware\SetupMiddleware::class, fn(ContainerInterface $c) =>
            new \Api\Middleware\SetupMiddleware(
                $c->get(UserRepositoryInterface::class)
            )
        );

        $container->singleton(\Api\Middleware\RoleEnforcementMiddleware::class, fn(ContainerInterface $c) =>
            new \Api\Middleware\RoleEnforcementMiddleware(
                $c->get(\Domain\Audit\Service\AuditChainServiceInterface::class)
            )
        );
    }

    private static function registerValidationServices(Container $container): void
    {
        // Request Validator (base service)
        $container->singleton(\Domain\Shared\Validation\RequestValidator::class, fn() =>
            new \Domain\Shared\Validation\RequestValidator()
        );

        // Transaction Validation
        $container->singleton(TransactionValidation::class, fn(ContainerInterface $c) =>
            new TransactionValidation(
                $c->get(\Domain\Shared\Validation\RequestValidator::class)
            )
        );

        // Company Validation
        $container->singleton(CompanyValidation::class, fn(ContainerInterface $c) =>
            new CompanyValidation(
                $c->get(\Domain\Shared\Validation\RequestValidator::class)
            )
        );

        // User Validation
        $container->singleton(UserValidation::class, fn(ContainerInterface $c) =>
            new UserValidation(
                $c->get(\Domain\Shared\Validation\RequestValidator::class)
            )
        );

        // Account Validation
        $container->singleton(AccountValidation::class, fn(ContainerInterface $c) =>
            new AccountValidation(
                $c->get(\Domain\Shared\Validation\RequestValidator::class)
            )
        );
    }

    private static function registerAuthorizationServices(Container $container): void
    {
        // Ownership Verifier
        $container->singleton(OwnershipVerifierInterface::class, fn(ContainerInterface $c) =>
            new OwnershipVerifier(
                $c->get(TransactionRepositoryInterface::class),
                $c->get(AccountRepositoryInterface::class),
                $c->get(UserRepositoryInterface::class)
            )
        );

        // Authorization Guard
        $container->singleton(AuthorizationGuard::class, fn(ContainerInterface $c) =>
            new AuthorizationGuard(
                $c->get(OwnershipVerifierInterface::class),
                $c->get(\Domain\Audit\Service\SystemActivityService::class)
            )
        );
    }

    private static function registerHandlers(Container $container): void
    {
        $container->singleton(CreateTransactionHandler::class, fn(ContainerInterface $c) =>
            new CreateTransactionHandler(
                $c->get(TransactionRepositoryInterface::class),
                $c->get(AccountRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class),
                $c->get(\Domain\Transaction\Service\TransactionNumberGeneratorInterface::class),
                $c->get(\Domain\Company\Repository\CompanyRepositoryInterface::class),
                $c->get(\Domain\Reporting\Repository\ClosedPeriodRepositoryInterface::class),
                $c->get(\Domain\Transaction\Service\TransactionValidationService::class),
                $c->get(\Domain\Transaction\Service\EdgeCaseDetectionServiceInterface::class),
                $c->get(ApprovalRepositoryInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Transaction\UpdateTransactionHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Transaction\UpdateTransactionHandler(
                $c->get(TransactionRepositoryInterface::class),
                $c->get(AccountRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(PostTransactionHandler::class, fn(ContainerInterface $c) =>
            new PostTransactionHandler(
                $c->get(TransactionRepositoryInterface::class),
                $c->get(ApprovalRepositoryInterface::class),
                $c->get(\Domain\Ledger\Repository\JournalEntryRepositoryInterface::class),
                $c->get(AccountRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(VoidTransactionHandler::class, fn(ContainerInterface $c) =>
            new VoidTransactionHandler(
                $c->get(TransactionRepositoryInterface::class),
                $c->get(\Domain\Ledger\Repository\JournalEntryRepositoryInterface::class),
                $c->get(AccountRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(DeleteTransactionHandler::class, fn(ContainerInterface $c) =>
            new DeleteTransactionHandler(
                $c->get(TransactionRepositoryInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Admin\SetupAdminHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Admin\SetupAdminHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(\Infrastructure\Service\TotpService::class)
            )
        );

        // Report Generation Handler
        $container->singleton(\Application\Handler\Reporting\GenerateReportHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Reporting\GenerateReportHandler(
                $c->get(\Domain\Ledger\Repository\BalanceChangeRepositoryInterface::class),
                $c->get(AccountRepositoryInterface::class),
                $c->get(ReportRepositoryInterface::class)
            )
        );

        // Trial Balance Generator Service
        $container->singleton(\Domain\Reporting\Service\TrialBalanceGeneratorInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Reporting\Service\TrialBalanceGenerator(
                $c->get(AccountRepositoryInterface::class)
            )
        );

        // Trial Balance Handler
        $container->singleton(\Application\Handler\Reporting\GenerateTrialBalanceHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Reporting\GenerateTrialBalanceHandler(
                $c->get(\Domain\Reporting\Service\TrialBalanceGeneratorInterface::class)
            )
        );

        // Income Statement Generator & Handler
        $container->singleton(\Domain\Reporting\Service\IncomeStatementGeneratorInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Reporting\Service\IncomeStatementGenerator(
                $c->get(AccountRepositoryInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Reporting\GenerateIncomeStatementHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Reporting\GenerateIncomeStatementHandler(
                $c->get(\Domain\Reporting\Service\IncomeStatementGeneratorInterface::class)
            )
        );

        // Balance Sheet Generator & Handler
        $container->singleton(\Domain\Reporting\Service\BalanceSheetGeneratorInterface::class, fn(ContainerInterface $c) =>
            new \Infrastructure\Reporting\Service\BalanceSheetGenerator(
                $c->get(AccountRepositoryInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Reporting\GenerateBalanceSheetHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Reporting\GenerateBalanceSheetHandler(
                $c->get(\Domain\Reporting\Service\BalanceSheetGeneratorInterface::class)
            )
        );

        // User Management Handlers
        $container->singleton(\Application\Handler\Identity\ApproveUserHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Identity\ApproveUserHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Identity\DeclineUserHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Identity\DeclineUserHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Identity\DeactivateUserHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Identity\DeactivateUserHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Identity\ActivateUserHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Identity\ActivateUserHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );

        // Settings Handlers
        $container->singleton(\Application\Handler\Settings\UpdateSettingsHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Settings\UpdateSettingsHandler(
                $c->get(UserSettingsRepositoryInterface::class),
                $c->get(UserRepositoryInterface::class)
            )
        );

        $container->singleton(\Application\Handler\Settings\SecuritySettingsHandler::class, fn(ContainerInterface $c) =>
            new \Application\Handler\Settings\SecuritySettingsHandler(
                $c->get(UserRepositoryInterface::class),
                $c->get(UserSettingsRepositoryInterface::class),
                $c->get(\Infrastructure\Service\TotpService::class)
            )
        );
    }
}

