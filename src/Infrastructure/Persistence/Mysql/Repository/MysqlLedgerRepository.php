<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Ledger\Entity\AccountBalance;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\ChartOfAccounts\ValueObject\AccountType;

class MysqlLedgerRepository extends AbstractMysqlRepository implements LedgerRepositoryInterface
{
    public function saveBalance(AccountBalance $balance): void
    {
        $this->save($balance);
    }

    public function save(AccountBalance $balance): void
    {
        $sql = "INSERT INTO account_balances (
            id,
            account_id,
            company_id,
            period_start,
            period_end,
            opening_balance_cents,
            current_balance_cents,
            updated_at
        ) VALUES (
            :id,
            :account_id,
            :company_id,
            :period_start,
            :period_end,
            :opening_balance_cents,
            :current_balance_cents,
            NOW()
        ) ON DUPLICATE KEY UPDATE
            current_balance_cents = VALUES(current_balance_cents),
            updated_at = NOW()";

        // Note: AccountBalance entity doesn't track period start/end directly.
        // We use defaults for now as schema requires them.
        $now = new \DateTimeImmutable();
        $start = new \DateTimeImmutable('1970-01-01');

        $params = [
            'id' => $balance->id()->toString(),
            'account_id' => $balance->accountId()->toString(),
            'company_id' => $balance->companyId()->toString(),
            'period_start' => $start->format('Y-m-d H:i:s'),
            'period_end' => $now->format('Y-m-d H:i:s'),
            'opening_balance_cents' => $balance->openingBalanceCents(),
            'current_balance_cents' => $balance->currentBalanceCents(),
        ];

        $this->connection->prepare($sql)->execute($params);
    }
    
    // Interface Implementation Stubs for now
    public function getAccountBalance(CompanyId $companyId, AccountId $accountId): ?AccountBalance
    {
        return $this->findByAccount($accountId);
    }

    public function getAllBalances(CompanyId $companyId): array
    {
        $sql = "SELECT ab.* FROM account_balances ab
                INNER JOIN accounts a ON ab.account_id = a.id
                WHERE a.company_id = :company_id
                ORDER BY ab.period_end DESC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['company_id' => $companyId->toString()]);

        $balances = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $balances[] = $this->hydrateAccountBalance($row);
        }

        return $balances;
    }

    public function getBalancesByType(CompanyId $companyId, AccountType $type): array
    {
        $sql = "SELECT ab.* FROM account_balances ab
                INNER JOIN accounts a ON ab.account_id = a.id
                WHERE a.company_id = :company_id AND a.type = :type
                ORDER BY ab.period_end DESC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId->toString(),
            'type' => $type->value,
        ]);

        $balances = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $balances[] = $this->hydrateAccountBalance($row);
        }

        return $balances;
    }

    public function getBalanceCents(CompanyId $companyId, AccountId $accountId): int
    {
        // Use account_balances table as the source of truth per "Production Accounting System" recommendation.
        // This is maintained by BalanceUpdateListener (Event-Driven).
        $sql = "SELECT current_balance_cents 
                FROM account_balances 
                WHERE account_id = :account_id 
                AND company_id = :company_id
                ORDER BY period_end DESC 
                LIMIT 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'account_id' => $accountId->toString(),
            'company_id' => $companyId->toString(),
        ]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int) $row['current_balance_cents'] : 0;
    }

    public function initializeBalance(AccountBalance $balance): void
    {
        $this->save($balance);
    }

    public function findByAccount(AccountId $accountId): ?AccountBalance
    {
        // We typically want the *current* active balance period.
        // For simplicity, we'll fetch the latest one by period_end.
        $sql = "SELECT * FROM account_balances WHERE account_id = :account_id ORDER BY period_end DESC LIMIT 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['account_id' => $accountId->toString()]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateAccountBalance($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateAccountBalance(array $row): AccountBalance
    {
         $accountSql = "SELECT currency, type, company_id FROM accounts WHERE id = :id";
         $stmt = $this->connection->prepare($accountSql);
         $stmt->execute(['id' => $row['account_id']]);
         $accRow = $stmt->fetch(\PDO::FETCH_ASSOC);
         
         $currencyCode = $accRow['currency'] ?? 'USD';
         $typeStr = $accRow['type'] ?? 'ASSET';
         $companyIdStr = $accRow['company_id'] ?? $row['company_id'] ?? '00000000-0000-0000-0000-000000000000';

         $currency = \Domain\Shared\ValueObject\Currency::from($currencyCode);
         $accountType = \Domain\ChartOfAccounts\ValueObject\AccountType::from($typeStr);
         
         return AccountBalance::reconstruct(
            \Domain\Ledger\ValueObject\AccountBalanceId::fromString($row['id']),
            \Domain\ChartOfAccounts\ValueObject\AccountId::fromString($row['account_id']),
            \Domain\Company\ValueObject\CompanyId::fromString($companyIdStr),
            $accountType,
            $accountType->normalBalance(),
            $currency,
            \Domain\Ledger\ValueObject\BalanceMetrics::reconstruct(
                (int)$row['current_balance_cents'], // Note: reconstruct arg order: current, opening...
                (int)$row['opening_balance_cents'],
                0, 0, 0, null, 1
            )
        );
    }
}
