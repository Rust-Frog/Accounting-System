<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Ledger\Entity\BalanceChange;
use Domain\Ledger\Repository\BalanceChangeRepositoryInterface;
use Domain\Ledger\ValueObject\LedgerId;

class MysqlBalanceChangeRepository extends AbstractMysqlRepository implements BalanceChangeRepositoryInterface
{
    public function save(BalanceChange $change): void
    {
        $sql = "INSERT INTO balance_changes (
            id,
            account_id,
            transaction_line_id,
            line_type,
            amount_cents,
            previous_balance_cents,
            new_balance_cents,
            change_cents,
            is_reversal,
            occurred_at
        ) VALUES (
            :id,
            :account_id,
            :transaction_line_id,
            :line_type,
            :amount_cents,
            :previous_balance_cents,
            :new_balance_cents,
            :change_cents,
            :is_reversal,
            :occurred_at
        )";

        $params = [
            'id' => $change->id()->toString(),
            'account_id' => $change->accountId()->toString(),
            'transaction_line_id' => $change->transactionId()->toString(),
            'line_type' => $change->lineType()->value,
            'amount_cents' => $change->amountCents(),
            'previous_balance_cents' => $change->previousBalanceCents(),
            'new_balance_cents' => $change->newBalanceCents(),
            'change_cents' => $change->changeCents(),
            'is_reversal' => $change->isReversal() ? 1 : 0,
            'occurred_at' => $change->occurredAt()->format('Y-m-d H:i:s'),
        ];

        $this->connection->prepare($sql)->execute($params);
    }
    
    public function findByAccount(
        \Domain\ChartOfAccounts\ValueObject\AccountId $accountId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        $sql = "SELECT * FROM balance_changes 
                WHERE account_id = :account_id 
                AND occurred_at >= :from_date 
                AND occurred_at <= :to_date 
                ORDER BY occurred_at DESC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'account_id' => $accountId->toString(),
            'from_date' => $from->format('Y-m-d H:i:s'),
            'to_date' => $to->format('Y-m-d H:i:s'),
        ]);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrateBalanceChange($row);
        }
        return $results;
    }

    public function findByTransaction(\Domain\Transaction\ValueObject\TransactionId $transactionId): array
    {
         $sql = "SELECT * FROM balance_changes WHERE transaction_line_id = :transaction_id";
         $stmt = $this->connection->prepare($sql);
         $stmt->execute(['transaction_id' => $transactionId->toString()]);
         
         $results = [];
         while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
             $results[] = $this->hydrateBalanceChange($row);
         }
         return $results;
    }

    public function isTransactionReversed(\Domain\Transaction\ValueObject\TransactionId $transactionId): bool
    {
        $sql = "SELECT COUNT(*) FROM balance_changes WHERE transaction_line_id = :transaction_id AND is_reversal = 1";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['transaction_id' => $transactionId->toString()]);
        
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateBalanceChange(array $row): BalanceChange
    {
        return BalanceChange::reconstruct(
            \Domain\Ledger\ValueObject\BalanceChangeId::fromString($row['id']),
            \Domain\ChartOfAccounts\ValueObject\AccountId::fromString($row['account_id']),
            \Domain\Transaction\ValueObject\TransactionId::fromString($row['transaction_line_id']),
            \Domain\Transaction\ValueObject\LineType::from($row['line_type']),
            (int)$row['amount_cents'],
            (int)($row['previous_balance_cents'] ?? 0),
            (int)($row['new_balance_cents'] ?? 0),
            (int)($row['change_cents'] ?? 0), // Use stored value if available
            (bool)($row['is_reversal'] ?? false),
            new \DateTimeImmutable($row['occurred_at'])
        );
    }

    /**
     * Aggregate balance changes by account for a company within a period.
     * Uses efficient SQL aggregation with JOIN to filter by company.
     *
     * @return array<string, int> Account ID => Net change in cents
     */
    public function sumChangesByCompanyAndPeriod(
        \Domain\Company\ValueObject\CompanyId $companyId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        $sql = "SELECT bc.account_id, SUM(bc.change_cents) as net_change
                FROM balance_changes bc
                JOIN accounts a ON bc.account_id = a.id
                WHERE a.company_id = :company_id
                  AND bc.occurred_at >= :from_date
                  AND bc.occurred_at <= :to_date
                GROUP BY bc.account_id";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId->toString(),
            'from_date' => $from->format('Y-m-d H:i:s'),
            'to_date' => $to->format('Y-m-d H:i:s'),
        ]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[$row['account_id']] = (int)$row['net_change'];
        }

        return $results;
    }
}
