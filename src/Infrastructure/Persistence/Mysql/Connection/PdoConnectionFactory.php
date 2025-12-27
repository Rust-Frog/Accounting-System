<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Connection;

use PDO;
use PDOException;

/**
 * Factory for creating PDO database connections.
 * Uses singleton pattern for connection reuse.
 */
final class PdoConnectionFactory
{
    private static ?PDO $connection = null;

    public function __construct()
    {
        // Allow instantiation for DI
    }

    /**
     * Get the database connection (Singleton).
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::makePdo();
        }

        return self::$connection;
    }

    /**
     * Create a new PDO connection (Instance method).
     * Used by services that need a fresh connection or injected factory.
     */
    public function createConnection(): PDO
    {
        return self::makePdo();
    }

    /**
     * Internal factory for PDO instance.
     *
     * @throws \RuntimeException if required environment variables are missing
     */
    private static function makePdo(): PDO
    {
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'accounting_system';
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');

        // Fail fast if credentials are not configured
        if ($username === false || $username === '') {
            throw new \RuntimeException('DB_USERNAME environment variable is required');
        }

        if ($password === false) {
            throw new \RuntimeException('DB_PASSWORD environment variable is required');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Close the connection.
     * Useful for testing or long-running scripts.
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Set a custom connection (for testing).
     */
    public static function setConnection(PDO $connection): void
    {
        self::$connection = $connection;
    }
}
