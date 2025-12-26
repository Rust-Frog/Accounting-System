<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Infrastructure\Persistence\Mysql\Hydrator\AccountHydrator;
use PDO;

/**
 * MySQL implementation of AccountRepositoryInterface.
 */
final class MysqlAccountRepository extends AbstractMysqlRepository implements AccountRepositoryInterface
{
    private AccountHydrator $hydrator;

    public function __construct(?PDO $connection = null)
    {
        parent::__construct($connection);
        $this->hydrator = new AccountHydrator();
    }

    public function save(Account $account): void
    {
        $data = $this->hydrator->extract($account);

        $exists = $this->exists(
            'SELECT 1 FROM accounts WHERE id = :id',
            ['id' => $data['id']]
        );

        if ($exists) {
            $this->update($data);
        } else {
            $this->insert($data);
        }
    }

    public function findById(AccountId $accountId): ?Account
    {
        $row = $this->fetchOne(
            'SELECT * FROM accounts WHERE id = :id',
            ['id' => $accountId->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function findByCode(AccountCode $code, CompanyId $companyId): ?Account
    {
        $row = $this->fetchAccountByCode($code, $companyId);
        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function existsByCode(AccountCode $code, CompanyId $companyId): bool
    {
        return $this->fetchAccountByCode($code, $companyId) !== null;
    }

    /**
     * @return array<Account>
     */
    public function findByCompany(CompanyId $companyId): array
    {
        return $this->findAccountsByCompany($companyId, activeOnly: false);
    }

    /**
     * @return array<Account>
     */
    public function findActiveByCompany(CompanyId $companyId): array
    {
        return $this->findAccountsByCompany($companyId, activeOnly: true);
    }

    /**
     * @return array<Account>
     */
    public function findByParent(AccountId $parentAccountId): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM accounts WHERE parent_account_id = :parent_id ORDER BY code ASC',
            ['parent_id' => $parentAccountId->toString()]
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    public function delete(AccountId $accountId): void
    {
        // Per user requirements, we use soft-delete via is_active = 0
        $this->execute(
            'UPDATE accounts SET is_active = 0 WHERE id = :id',
            ['id' => $accountId->toString()]
        );
    }

    /**
     * Fetch account row by code and company.
     *
     * @return array<string, mixed>|null
     */
    private function fetchAccountByCode(AccountCode $code, CompanyId $companyId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM accounts WHERE code = :code AND company_id = :company_id',
            [
                'code' => $code->toInt(),
                'company_id' => $companyId->toString(),
            ]
        );
    }

    /**
     * Find accounts by company with optional active filter.
     *
     * @return array<Account>
     */
    private function findAccountsByCompany(CompanyId $companyId, bool $activeOnly): array
    {
        $sql = 'SELECT * FROM accounts WHERE company_id = :company_id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY code ASC';

        $rows = $this->fetchAll($sql, ['company_id' => $companyId->toString()]);

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    public function countActive(): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM accounts WHERE is_active = 1"
        );

        return $result !== null ? (int) $result['count'] : 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insert(array $data): void
    {
        $sql = <<<SQL
            INSERT INTO accounts (
                id, company_id, code, name, type, description, is_active, 
                parent_account_id, balance_cents, currency
            ) VALUES (
                :id, :company_id, :code, :name, :type, :description, :is_active,
                :parent_account_id, :balance_cents, :currency
            )
        SQL;

        $this->execute($sql, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(array $data): void
    {
        $sql = <<<SQL
            UPDATE accounts SET
                name = :name,
                description = :description,
                is_active = :is_active,
                parent_account_id = :parent_account_id,
                balance_cents = :balance_cents,
                currency = :currency
            WHERE id = :id
        SQL;

        $this->execute($sql, [
            'id' => $data['id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'is_active' => $data['is_active'],
            'parent_account_id' => $data['parent_account_id'],
            'balance_cents' => $data['balance_cents'],
            'currency' => $data['currency'],
        ]);
    }
}

