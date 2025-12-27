<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Company\Entity\Company;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\TaxIdentifier;
use Infrastructure\Persistence\Mysql\Hydrator\CompanyHydrator;
use PDO;

/**
 * MySQL implementation of CompanyRepositoryInterface.
 */
final class MysqlCompanyRepository extends AbstractMysqlRepository implements CompanyRepositoryInterface
{
    private CompanyHydrator $hydrator;

    public function __construct(?PDO $connection = null)
    {
        parent::__construct($connection);
        $this->hydrator = new CompanyHydrator();
    }

    public function save(Company $company): void
    {
        $data = $this->hydrator->extract($company);

        $exists = $this->exists(
            'SELECT 1 FROM companies WHERE id = :id',
            ['id' => $data['id']]
        );

        if ($exists) {
            $this->update($data);
        } else {
            $this->insert($data);
        }
    }

    public function findById(CompanyId $companyId): ?Company
    {
        $row = $this->fetchOne(
            'SELECT * FROM companies WHERE id = :id',
            ['id' => $companyId->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function findByTaxId(TaxIdentifier $taxId): ?Company
    {
        $row = $this->fetchOne(
            'SELECT * FROM companies WHERE tax_id = :tax_id',
            ['tax_id' => $taxId->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function existsByTaxId(TaxIdentifier $taxId): bool
    {
        return $this->exists(
            'SELECT 1 FROM companies WHERE tax_id = :tax_id',
            ['tax_id' => $taxId->toString()]
        );
    }

    /**
     * @return array<Company>
     */
    public function findPendingCompanies(): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM companies WHERE status = 'pending' ORDER BY created_at ASC"
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @return array<Company>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->fetchPaged(
            'SELECT * FROM companies ORDER BY company_name ASC',
            [],
            new \Domain\Shared\ValueObject\Pagination($limit, $offset)
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * Find only active companies.
     * @return array<Company>
     */
    public function findActiveCompanies(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->fetchPaged(
            "SELECT * FROM companies WHERE status = 'active' ORDER BY company_name ASC",
            [],
            new \Domain\Shared\ValueObject\Pagination($limit, $offset)
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insert(array $data): void
    {
        $sql = <<<SQL
            INSERT INTO companies (
                id, company_name, legal_name, tax_id,
                address_street, address_city, address_state, address_postal_code, address_country,
                currency, status, created_at, updated_at
            ) VALUES (
                :id, :company_name, :legal_name, :tax_id,
                :address_street, :address_city, :address_state, :address_postal_code, :address_country,
                :currency, :status, :created_at, :updated_at
            )
        SQL;

        $this->execute($sql, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(array $data): void
    {
        // Remove created_at - it's not updateable and not in the SQL
        unset($data['created_at']);

        $sql = <<<SQL
            UPDATE companies SET
                company_name = :company_name,
                legal_name = :legal_name,
                tax_id = :tax_id,
                address_street = :address_street,
                address_city = :address_city,
                address_state = :address_state,
                address_postal_code = :address_postal_code,
                address_country = :address_country,
                currency = :currency,
                status = :status,
                updated_at = :updated_at
            WHERE id = :id
        SQL;

        $this->execute($sql, $data);
    }
}
