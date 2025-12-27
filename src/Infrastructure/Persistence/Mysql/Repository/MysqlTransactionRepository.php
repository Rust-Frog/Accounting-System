<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use DateTimeImmutable;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;
use Domain\Transaction\ValueObject\TransactionStatus;
use Infrastructure\Persistence\Mysql\Hydrator\TransactionHydrator;
use PDO;

/**
 * MySQL implementation of TransactionRepositoryInterface.
 * Manages Transaction as an aggregate root with embedded lines.
 */
final class MysqlTransactionRepository extends AbstractMysqlRepository implements TransactionRepositoryInterface
{
    private TransactionHydrator $hydrator;

    public function __construct(?PDO $connection = null)
    {
        parent::__construct($connection);
        $this->hydrator = new TransactionHydrator();
    }

    public function save(Transaction $transaction): void
    {
        $this->beginTransaction();

        try {
            $data = $this->hydrator->extract($transaction);

            $exists = $this->exists(
                'SELECT 1 FROM transactions WHERE id = :id',
                ['id' => $data['id']]
            );

            if ($exists) {
                $this->updateTransaction($data);
            } else {
                $this->insertTransaction($data);
            }

            $this->syncTransactionLines($transaction, $data['id'], $exists);

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function findById(TransactionId $id): ?Transaction
    {
        $row = $this->fetchOne(
            'SELECT * FROM transactions WHERE id = :id',
            ['id' => $id->toString()]
        );

        if ($row === null) {
            return null;
        }

        $lineRows = $this->fetchAll(
            'SELECT * FROM transaction_lines WHERE transaction_id = :transaction_id ORDER BY line_order',
            ['transaction_id' => $id->toString()]
        );

        return $this->hydrator->hydrate($row, $lineRows);
    }

    public function findByNumber(CompanyId $companyId, string $transactionNumber): ?Transaction
    {
        // Note: transactionNumber might be referenceNumber or the ID
        $row = $this->fetchOne(
            'SELECT * FROM transactions WHERE company_id = :company_id AND 
             (reference_number = :ref OR id = :id)',
            [
                'company_id' => $companyId->toString(),
                'ref' => $transactionNumber,
                'id' => $transactionNumber,
            ]
        );

        if ($row === null) {
            return null;
        }

        $lineRows = $this->fetchAll(
            'SELECT * FROM transaction_lines WHERE transaction_id = :transaction_id ORDER BY line_order',
            ['transaction_id' => $row['id']]
        );

        return $this->hydrator->hydrate($row, $lineRows);
    }

    /**
     * @return array<Transaction>
     */
    public function findByCompany(
        CompanyId $companyId, 
        ?TransactionStatus $status = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $sql = 'SELECT * FROM transactions WHERE company_id = :company_id';
        $params = ['company_id' => $companyId->toString()];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status->value;
        }

        $sql .= ' ORDER BY transaction_date DESC, created_at DESC';
        
        $rows = $this->fetchPaged(
            $sql,
            $params,
            new \Domain\Shared\ValueObject\Pagination($limit, $offset)
        );

        return $this->hydrateMultiple($rows);
    }

    /**
     * @return array<Transaction>
     */
    public function findByDateRange(CompanyId $companyId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM transactions 
             WHERE company_id = :company_id 
             AND transaction_date >= :from_date 
             AND transaction_date <= :to_date
             ORDER BY transaction_date ASC',
            [
                'company_id' => $companyId->toString(),
                'from_date' => $from->format('Y-m-d'),
                'to_date' => $to->format('Y-m-d'),
            ]
        );

        return $this->hydrateMultiple($rows);
    }

    /**
     * @return array<Transaction>
     */
    public function findByAccount(AccountId $accountId, ?TransactionStatus $status = null): array
    {
        $sql = 'SELECT DISTINCT t.* FROM transactions t
                INNER JOIN transaction_lines tl ON t.id = tl.transaction_id
                WHERE tl.account_id = :account_id';
        $params = ['account_id' => $accountId->toString()];

        if ($status !== null) {
            $sql .= ' AND t.status = :status';
            $params['status'] = $status->value;
        }

        $sql .= ' ORDER BY t.transaction_date DESC';

        $rows = $this->fetchAll($sql, $params);

        return $this->hydrateMultiple($rows);
    }

    /**
     * @return array<Transaction>
     */
    public function findPendingApproval(CompanyId $companyId): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM transactions 
             WHERE company_id = :company_id AND status = 'draft'
             ORDER BY created_at ASC",
            ['company_id' => $companyId->toString()]
        );

        return $this->hydrateMultiple($rows);
    }

    public function getNextTransactionNumber(CompanyId $companyId): string
    {
        $result = $this->fetchOne(
            'SELECT COUNT(*) + 1 as next_num FROM transactions WHERE company_id = :company_id',
            ['company_id' => $companyId->toString()]
        );

        $nextNum = $result !== null ? (int) $result['next_num'] : 1;

        return sprintf('TXN-%s-%06d', date('Y'), $nextNum);
    }

    public function countByStatus(CompanyId $companyId, TransactionStatus $status): int
    {
        $result = $this->fetchOne(
            'SELECT COUNT(*) as count FROM transactions 
             WHERE company_id = :company_id AND status = :status',
            [
                'company_id' => $companyId->toString(),
                'status' => $status->value,
            ]
        );

        return $result !== null ? (int) $result['count'] : 0;
    }

    public function countToday(): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM transactions 
             WHERE DATE(transaction_date) = CURDATE()"
        );

        return $result !== null ? (int) $result['count'] : 0;
    }

    /**
     * Hydrate multiple transactions with their lines.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<Transaction>
     */
    private function hydrateMultiple(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Get all transaction IDs
        $transactionIds = array_column($rows, 'id');

        // Fetch all lines for these transactions in one query
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT * FROM transaction_lines 
             WHERE transaction_id IN ($placeholders) 
             ORDER BY transaction_id, line_order"
        );
        $stmt->execute($transactionIds);
        $allLines = $stmt->fetchAll();

        // Group lines by transaction_id
        $linesByTransaction = [];
        foreach ($allLines as $lineRow) {
            $linesByTransaction[$lineRow['transaction_id']][] = $lineRow;
        }

        // Hydrate each transaction with its lines
        $transactions = [];
        foreach ($rows as $row) {
            $lineRows = $linesByTransaction[$row['id']] ?? [];
            $transactions[] = $this->hydrator->hydrate($row, $lineRows);
        }

        return $transactions;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertTransaction(array $data): void
    {
        $sql = <<<SQL
            INSERT INTO transactions (
                id, company_id, transaction_date, description, reference_number,
                status, created_by, created_at, posted_by, posted_at,
                voided_by, voided_at, void_reason
            ) VALUES (
                :id, :company_id, :transaction_date, :description, :reference_number,
                :status, :created_by, :created_at, :posted_by, :posted_at,
                :voided_by, :voided_at, :void_reason
            )
        SQL;

        $this->execute($sql, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateTransaction(array $data): void
    {
        $sql = <<<SQL
            UPDATE transactions SET
                transaction_date = :transaction_date,
                description = :description,
                reference_number = :reference_number,
                status = :status,
                posted_by = :posted_by,
                posted_at = :posted_at,
                voided_by = :voided_by,
                voided_at = :voided_at,
                void_reason = :void_reason
            WHERE id = :id
        SQL;

        $params = [
            'transaction_date' => $data['transaction_date'],
            'description' => $data['description'],
            'reference_number' => $data['reference_number'],
            'status' => $data['status'],
            'posted_by' => $data['posted_by'],
            'posted_at' => $data['posted_at'],
            'voided_by' => $data['voided_by'],
            'voided_at' => $data['voided_at'],
            'void_reason' => $data['void_reason'],
            'id' => $data['id'],
        ];

        $this->execute($sql, $params);
    }

    /**
     * @param array<string, mixed> $lineData
     */
    private function insertLine(array $lineData): void
    {
        $sql = <<<SQL
            INSERT INTO transaction_lines (
                id, transaction_id, account_id, line_type, amount_cents, 
                currency, description, line_order
            ) VALUES (
                :id, :transaction_id, :account_id, :line_type, :amount_cents,
                :currency, :description, :line_order
            )
        SQL;

        $this->execute($sql, $lineData);
    }
    private function syncTransactionLines(Transaction $transaction, string $transactionId, bool $exists): void
    {
        // Replace all lines (delete old, insert new)
        $this->execute(
            'DELETE FROM transaction_lines WHERE transaction_id = :transaction_id',
            ['transaction_id' => $transactionId]
        );

        foreach ($transaction->lines() as $index => $line) {
            $lineData = $this->hydrator->extractLine($line, $transactionId, $index);
            $this->insertLine($lineData);
        }
    }

    public function delete(TransactionId $id): void
    {
        $this->beginTransaction();

        try {
            $this->execute(
                'DELETE FROM transaction_lines WHERE transaction_id = :transaction_id',
                ['transaction_id' => $id->toString()]
            );

            $this->execute(
                'DELETE FROM transactions WHERE id = :id',
                ['id' => $id->toString()]
            );

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getLastActivityDate(AccountId $accountId): ?DateTimeImmutable
    {
        $result = $this->fetchOne(
            'SELECT MAX(t.transaction_date) as last_activity
             FROM transactions t
             INNER JOIN transaction_lines tl ON t.id = tl.transaction_id
             WHERE tl.account_id = :account_id
             AND t.status != :voided_status',
            [
                'account_id' => $accountId->toString(),
                'voided_status' => TransactionStatus::VOIDED->value,
            ]
        );

        if ($result === null || $result['last_activity'] === null) {
            return null;
        }

        return new DateTimeImmutable($result['last_activity']);
    }

    public function findSimilarTransaction(
        CompanyId $companyId,
        int $totalAmountCents,
        string $description,
        DateTimeImmutable $transactionDate,
    ): ?string {
        // Find transaction with same amount on same day
        // Sum debit amounts to get total (could also sum credits, they should be equal)
        $result = $this->fetchOne(
            'SELECT t.reference_number, t.id
             FROM transactions t
             INNER JOIN (
                 SELECT transaction_id, SUM(amount_cents) as total_amount
                 FROM transaction_lines
                 WHERE line_type = :line_type
                 GROUP BY transaction_id
             ) tl_sum ON t.id = tl_sum.transaction_id
             WHERE t.company_id = :company_id
             AND t.transaction_date = :transaction_date
             AND tl_sum.total_amount = :total_amount
             AND t.status != :voided_status
             LIMIT 1',
            [
                'company_id' => $companyId->toString(),
                'transaction_date' => $transactionDate->format('Y-m-d'),
                'total_amount' => $totalAmountCents,
                'line_type' => 'debit',
                'voided_status' => TransactionStatus::VOIDED->value,
            ]
        );

        if ($result === null) {
            return null;
        }

        // Return reference number if available, otherwise ID
        return $result['reference_number'] ?? $result['id'];
    }
}
