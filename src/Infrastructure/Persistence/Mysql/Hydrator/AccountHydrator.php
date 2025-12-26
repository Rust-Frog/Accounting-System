<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Hydrator;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use ReflectionClass;

/**
 * Hydrates Account entities from database rows and extracts data for persistence.
 */
final class AccountHydrator
{
    /**
     * Hydrate an Account entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): Account
    {
        $reflection = new ReflectionClass(Account::class);
        $account = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $account, 'id', AccountId::fromString($row['id']));
        $this->setProperty($reflection, $account, 'code', AccountCode::fromInt((int) $row['code']));
        $this->setProperty($reflection, $account, 'name', $row['name']);
        $this->setProperty($reflection, $account, 'companyId', CompanyId::fromString($row['company_id']));
        $this->setProperty($reflection, $account, 'description', $row['description']);
        $this->setProperty(
            $reflection,
            $account,
            'parentAccountId',
            $row['parent_account_id'] !== null ? AccountId::fromString($row['parent_account_id']) : null
        );
        $this->setProperty($reflection, $account, 'isActive', (bool) $row['is_active']);
        $this->setProperty(
            $reflection,
            $account,
            'balance',
            Money::fromSignedCents((int) $row['balance_cents'], Currency::from($row['currency']))
        );
        $this->setProperty($reflection, $account, 'domainEvents', []);

        return $account;
    }

    /**
     * Extract data from Account entity for persistence.
     *
     * @return array<string, mixed>
     */
    public function extract(Account $account): array
    {
        return [
            'id' => $account->id()->toString(),
            'company_id' => $account->companyId()->toString(),
            'code' => $account->code()->toInt(),
            'name' => $account->name(),
            'type' => $account->accountType()->value,
            'description' => $account->description(),
            'is_active' => $account->isActive() ? 1 : 0,
            'parent_account_id' => $account->parentAccountId()?->toString(),
            'balance_cents' => $account->balance()->cents(),
            'currency' => $account->balance()->currency()->value,
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
