<?php

declare(strict_types=1);

namespace Infrastructure\Container;

use Application\Handler\Transaction\CreateTransactionHandler;
use Application\Handler\Transaction\PostTransactionHandler;
use Application\Handler\Transaction\VoidTransactionHandler;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Audit\Repository\ActivityLogRepositoryInterface;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Identity\Repository\SessionRepositoryInterface;
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
use Infrastructure\Persistence\Mysql\Repository\MysqlSessionRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlTransactionRepository;
use Infrastructure\Persistence\Mysql\Repository\MysqlUserRepository;
use Infrastructure\Persistence\Mysql\Connection\PdoConnectionFactory;
use Infrastructure\Service\BcryptPasswordHashingService;
use Infrastructure\Service\InMemoryEventDispatcher;
use Infrastructure\Service\JwtService;
use Infrastructure\Service\SessionAuthenticationService;
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
        // Database connection
        $container->singleton(PDO::class, function () {
            $host = $_ENV['DB_HOST'] ?? 'mysql';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $database = $_ENV['DB_DATABASE'] ?? 'accounting';
            $username = $_ENV['DB_USERNAME'] ?? 'accounting_user';
            $password = $_ENV['DB_PASSWORD'] ?? 'accounting_pass';

            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
            
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        });

        // Database connection factory
        $container->singleton(PdoConnectionFactory::class, fn() =>
            new PdoConnectionFactory()
        );
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

        $container->singleton(SessionRepositoryInterface::class, fn(ContainerInterface $c) =>
            new MysqlSessionRepository($c->get(PDO::class))
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
    }

    private static function registerServices(Container $container): void
    {
        $container->singleton(EventDispatcherInterface::class, function (ContainerInterface $c) {
            $dispatcher = new \Infrastructure\Service\InMemoryEventDispatcher();
            
            // Register ActivityLogListener
            if ($c->has(\Application\Listener\ActivityLogListener::class)) {
                $listener = $c->get(\Application\Listener\ActivityLogListener::class);
                $dispatcher->addListener('*', $listener);
            }
            
            return $dispatcher;
        });

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
                $c->get(\Domain\Audit\Service\ActivityLogService::class)
            )
        );

        $container->singleton('password_service', fn() =>
            new BcryptPasswordHashingService()
        );

        $container->singleton(AuthenticationServiceInterface::class, fn(ContainerInterface $c) =>
            new SessionAuthenticationService(
                $c->get(PdoConnectionFactory::class),
                $c->get(UserRepositoryInterface::class),
                $c->get('password_service')
            )
        );

        // JWT Service
        $container->singleton(JwtService::class, function () {
            $secretKey = $_ENV['JWT_SECRET'] ?? 'default-secret-change-in-production';
            $expiration = (int) ($_ENV['JWT_EXPIRATION'] ?? 3600);
            $issuer = $_ENV['JWT_ISSUER'] ?? 'accounting-api';
            
            return new JwtService($secretKey, $expiration, $issuer);
        });
    }

    private static function registerHandlers(Container $container): void
    {
        $container->singleton(CreateTransactionHandler::class, fn(ContainerInterface $c) =>
            new CreateTransactionHandler(
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
                $c->get(EventDispatcherInterface::class)
            )
        );

        $container->singleton(VoidTransactionHandler::class, fn(ContainerInterface $c) =>
            new VoidTransactionHandler(
                $c->get(TransactionRepositoryInterface::class),
                $c->get(\Domain\Ledger\Repository\JournalEntryRepositoryInterface::class),
                $c->get(EventDispatcherInterface::class)
            )
        );
    }
}

