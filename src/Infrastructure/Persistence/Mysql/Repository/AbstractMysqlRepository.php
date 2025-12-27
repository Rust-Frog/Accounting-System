<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Infrastructure\Persistence\Mysql\Connection\PdoConnectionFactory;
use PDO;

/**
 * Base repository with common database operations.
 */
abstract class AbstractMysqlRepository
{
    protected PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? PdoConnectionFactory::getConnection();
    }

    private bool $weStartedTransaction = false;

    /**
     * Begin a database transaction.
     */
    protected function beginTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            if ($this->connection->beginTransaction()) {
                $this->weStartedTransaction = true;
            }
        }
    }

    /**
     * Commit the current transaction.
     */
    protected function commit(): void
    {
        if ($this->weStartedTransaction && $this->connection->inTransaction()) {
            $this->connection->commit();
            $this->weStartedTransaction = false;
        }
    }

    /**
     * Rollback the current transaction.
     */
    protected function rollback(): void
    {
        if ($this->weStartedTransaction && $this->connection->inTransaction()) {
            $this->connection->rollBack();
            $this->weStartedTransaction = false;
        }
    }

    /**
     * Execute a query and return all results.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query with pagination (strict LIMIT/OFFSET binding).
     *
     * @param string $sql SQL query without LIMIT/OFFSET
     * @param array<string, mixed> $params
     * @param \Domain\Shared\ValueObject\Pagination $pagination
     * @return array<int, array<string, mixed>>
     */
    protected function fetchPaged(string $sql, array $params, \Domain\Shared\ValueObject\Pagination $pagination): array
    {
        $sql .= ' LIMIT :limit OFFSET :offset';
        
        $stmt = $this->connection->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->bindValue(':limit', $pagination->limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a query and return a single row.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Execute a query (INSERT, UPDATE, DELETE).
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return int Affected rows
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Check if a record exists.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     */
    protected function exists(string $sql, array $params = []): bool
    {
        return $this->fetchOne($sql, $params) !== null;
    }

    /**
     * Get count from a query.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     */
    protected function count(string $sql, array $params = []): int
    {
        $result = $this->fetchOne($sql, $params);
        return $result !== null ? (int) ($result['count'] ?? 0) : 0;
    }
}
