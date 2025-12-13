<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\Repository;

use Domain\Company\Entity\Company;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Company\ValueObject\Address;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\TaxIdentifier;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Currency;
use PHPUnit\Framework\TestCase;

final class CompanyRepositoryInterfaceTest extends TestCase
{
    public function test_interface_defines_save_method(): void
    {
        $repository = $this->createInMemoryRepository();
        $company = $this->createCompany();

        $repository->save($company);
        $found = $repository->findById($company->id());

        $this->assertSame($company, $found);
    }

    public function test_interface_defines_find_by_tax_id(): void
    {
        $repository = $this->createInMemoryRepository();
        $company = $this->createCompany();

        $repository->save($company);
        $found = $repository->findByTaxId($company->taxId());

        $this->assertSame($company, $found);
        $this->assertNull($repository->findByTaxId(TaxIdentifier::fromString('999-999-999')));
    }

    public function test_interface_defines_exists_by_tax_id(): void
    {
        $repository = $this->createInMemoryRepository();
        $company = $this->createCompany();

        $repository->save($company);

        $this->assertTrue($repository->existsByTaxId($company->taxId()));
        $this->assertFalse($repository->existsByTaxId(TaxIdentifier::fromString('999-999-999')));
    }

    public function test_interface_defines_find_pending_companies(): void
    {
        $repository = $this->createInMemoryRepository();

        $pendingCompany = $this->createCompany();

        $activeCompany = $this->createCompany('Active Corp', 'Active Corporation', '987-654-321');
        $activeCompany->activate(UserId::generate());

        $repository->save($pendingCompany);
        $repository->save($activeCompany);

        $pendingCompanies = $repository->findPendingCompanies();

        $this->assertCount(1, $pendingCompanies);
        $this->assertContains($pendingCompany, $pendingCompanies);
    }

    public function test_interface_defines_find_all(): void
    {
        $repository = $this->createInMemoryRepository();

        $company1 = $this->createCompany();
        $company2 = $this->createCompany('Second Corp', 'Second Corporation', '987-654-321');

        $repository->save($company1);
        $repository->save($company2);

        $all = $repository->findAll();

        $this->assertCount(2, $all);
    }

    private function createInMemoryRepository(): CompanyRepositoryInterface
    {
        return new class implements CompanyRepositoryInterface {
            /** @var array<string, Company> */
            private array $companies = [];

            public function save(Company $company): void
            {
                $this->companies[$company->id()->toString()] = $company;
            }

            public function findById(CompanyId $companyId): ?Company
            {
                return $this->companies[$companyId->toString()] ?? null;
            }

            public function findByTaxId(TaxIdentifier $taxId): ?Company
            {
                foreach ($this->companies as $company) {
                    if ($company->taxId()->equals($taxId)) {
                        return $company;
                    }
                }
                return null;
            }

            public function existsByTaxId(TaxIdentifier $taxId): bool
            {
                return $this->findByTaxId($taxId) !== null;
            }

            /**
             * @return array<Company>
             */
            public function findPendingCompanies(): array
            {
                return array_filter(
                    $this->companies,
                    fn(Company $company) => $company->status()->isPending()
                );
            }

            /**
             * @return array<Company>
             */
            public function findAll(): array
            {
                return array_values($this->companies);
            }
        };
    }

    private function createCompany(
        string $name = 'Test Company',
        string $legalName = 'Test Company Inc.',
        string $taxId = '123-456-789'
    ): Company {
        return Company::create(
            companyName: $name,
            legalName: $legalName,
            taxId: TaxIdentifier::fromString($taxId),
            address: Address::create(
                street: '123 Main Street',
                city: 'Manila',
                state: 'Metro Manila',
                postalCode: '1000',
                country: 'Philippines'
            ),
            currency: Currency::PHP
        );
    }
}
