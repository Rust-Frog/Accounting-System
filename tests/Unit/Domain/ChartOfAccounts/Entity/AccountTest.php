<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ChartOfAccounts\Entity;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function test_creates_account_with_required_fields(): void
    {
        $accountCode = AccountCode::fromInt(1000);
        $companyId = CompanyId::generate();
        $accountName = 'Cash';

        $account = Account::create(
            accountCode: $accountCode,
            accountName: $accountName,
            companyId: $companyId
        );

        $this->assertInstanceOf(AccountId::class, $account->id());
        $this->assertTrue($accountCode->equals($account->code()));
        $this->assertEquals($accountName, $account->name());
        $this->assertTrue($companyId->equals($account->companyId()));
    }

    public function test_derives_account_type_from_code(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(AccountType::ASSET, $account->accountType());
    }

    public function test_derives_asset_account_type_from_1000_range(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1500),
            accountName: 'Accounts Receivable',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(AccountType::ASSET, $account->accountType());
    }

    public function test_derives_liability_account_type_from_2000_range(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(2000),
            accountName: 'Accounts Payable',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(AccountType::LIABILITY, $account->accountType());
    }

    public function test_derives_equity_account_type_from_3000_range(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(3000),
            accountName: 'Capital',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(AccountType::EQUITY, $account->accountType());
    }

    public function test_derives_revenue_account_type_from_4000_range(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(4000),
            accountName: 'Sales Revenue',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(AccountType::REVENUE, $account->accountType());
    }

    public function test_derives_expense_account_type_from_5000_range(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(5000),
            accountName: 'Salary Expense',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(AccountType::EXPENSE, $account->accountType());
    }

    public function test_asset_account_has_debit_normal_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(NormalBalance::DEBIT, $account->normalBalance());
    }

    public function test_liability_account_has_credit_normal_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(2000),
            accountName: 'Accounts Payable',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(NormalBalance::CREDIT, $account->normalBalance());
    }

    public function test_equity_account_has_credit_normal_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(3000),
            accountName: 'Capital',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(NormalBalance::CREDIT, $account->normalBalance());
    }

    public function test_revenue_account_has_credit_normal_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(4000),
            accountName: 'Sales Revenue',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(NormalBalance::CREDIT, $account->normalBalance());
    }

    public function test_expense_account_has_debit_normal_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(5000),
            accountName: 'Salary Expense',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(NormalBalance::DEBIT, $account->normalBalance());
    }

    public function test_account_is_active_by_default(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $this->assertTrue($account->isActive());
    }

    public function test_can_deactivate_account_with_zero_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $account->deactivate();

        $this->assertFalse($account->isActive());
    }

    public function test_cannot_deactivate_account_with_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );
        $account->recordBalance(Money::fromCents(10000, Currency::PHP));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot deactivate account with non-zero balance');

        $account->deactivate();
    }

    public function test_can_record_balance(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );
        $balance = Money::fromCents(50000, Currency::PHP);

        $account->recordBalance($balance);

        $this->assertTrue($balance->equals($account->balance()));
    }

    public function test_balance_is_zero_by_default(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $this->assertEquals(0, $account->balance()->cents());
    }

    public function test_can_set_description(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate(),
            description: 'Cash on hand and in banks'
        );

        $this->assertEquals('Cash on hand and in banks', $account->description());
    }

    public function test_description_is_null_by_default(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $this->assertNull($account->description());
    }

    public function test_can_set_parent_account(): void
    {
        $parentId = AccountId::generate();
        $account = Account::create(
            accountCode: AccountCode::fromInt(1010),
            accountName: 'Petty Cash',
            companyId: CompanyId::generate(),
            parentAccountId: $parentId
        );

        $this->assertNotNull($account->parentAccountId());
        $this->assertTrue($parentId->equals($account->parentAccountId()));
    }

    public function test_parent_account_is_null_by_default(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $this->assertNull($account->parentAccountId());
    }

    public function test_can_reactivate_account(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );
        $account->deactivate();
        $this->assertFalse($account->isActive());

        $account->activate();

        $this->assertTrue($account->isActive());
    }

    public function test_can_update_name(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $account->rename('Cash on Hand');

        $this->assertEquals('Cash on Hand', $account->name());
    }

    public function test_can_update_description(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $account->updateDescription('Updated description');

        $this->assertEquals('Updated description', $account->description());
    }

    public function test_records_domain_event_on_creation(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromInt(1000),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );

        $events = $account->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('account.created', $events[0]->eventName());
    }
}
