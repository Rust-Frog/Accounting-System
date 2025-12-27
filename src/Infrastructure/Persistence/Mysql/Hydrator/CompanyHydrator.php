<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Hydrator;

use DateTimeImmutable;
use Domain\Company\Entity\Company;
use Domain\Company\ValueObject\Address;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\CompanyStatus;
use Domain\Company\ValueObject\TaxIdentifier;
use Domain\Shared\ValueObject\Currency;
use ReflectionClass;

/**
 * Hydrates Company entities from database rows and extracts data for persistence.
 */
final class CompanyHydrator
{
    /**
     * Hydrate a Company entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): Company
    {
        $reflection = new ReflectionClass(Company::class);
        $company = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $company, 'companyId', CompanyId::fromString($row['id']));
        $this->setProperty($reflection, $company, 'companyName', $row['company_name']);
        $this->setProperty($reflection, $company, 'legalName', $row['legal_name']);
        $this->setProperty($reflection, $company, 'taxId', TaxIdentifier::fromString($row['tax_id']));
        $this->setProperty($reflection, $company, 'address', Address::create(
            $row['address_street'],
            $row['address_city'],
            $row['address_state'],
            $row['address_postal_code'],
            $row['address_country']
        ));
        $this->setProperty($reflection, $company, 'currency', Currency::from($row['currency']));
        $this->setProperty($reflection, $company, 'status', CompanyStatus::from($row['status']));
        $this->setProperty($reflection, $company, 'createdAt', new DateTimeImmutable($row['created_at']));
        $this->setProperty($reflection, $company, 'updatedAt', new DateTimeImmutable($row['updated_at']));
        $this->setProperty($reflection, $company, 'domainEvents', []);

        return $company;
    }

    /**
     * Extract data from Company entity for persistence.
     *
     * @return array<string, mixed>
     */
    public function extract(Company $company): array
    {
        return [
            'id' => $company->id()->toString(),
            'company_name' => $company->companyName(),
            'legal_name' => $company->legalName(),
            'tax_id' => $company->taxId()->toString(),
            'address_street' => $company->address()->street(),
            'address_city' => $company->address()->city(),
            'address_state' => $company->address()->state(),
            'address_postal_code' => $company->address()->postalCode(),
            'address_country' => $company->address()->country(),
            'currency' => $company->currency()->value,
            'status' => $company->status()->value,
            'created_at' => $company->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $company->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Set a property value using reflection.
     */
    private function setProperty(ReflectionClass $reflection, object $object, string $property, mixed $value): void
    {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
