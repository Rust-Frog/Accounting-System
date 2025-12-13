<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ChartOfAccounts\ValueObject;

use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use PHPUnit\Framework\TestCase;

final class AccountTypeTest extends TestCase
{
    public function test_has_asset_type(): void
    {
        $type = AccountType::ASSET;
        $this->assertEquals('asset', $type->value);
    }

    public function test_has_liability_type(): void
    {
        $type = AccountType::LIABILITY;
        $this->assertEquals('liability', $type->value);
    }

    public function test_has_equity_type(): void
    {
        $type = AccountType::EQUITY;
        $this->assertEquals('equity', $type->value);
    }

    public function test_has_revenue_type(): void
    {
        $type = AccountType::REVENUE;
        $this->assertEquals('revenue', $type->value);
    }

    public function test_has_expense_type(): void
    {
        $type = AccountType::EXPENSE;
        $this->assertEquals('expense', $type->value);
    }

    public function test_asset_has_debit_normal_balance(): void
    {
        $this->assertEquals(NormalBalance::DEBIT, AccountType::ASSET->normalBalance());
    }

    public function test_liability_has_credit_normal_balance(): void
    {
        $this->assertEquals(NormalBalance::CREDIT, AccountType::LIABILITY->normalBalance());
    }

    public function test_equity_has_credit_normal_balance(): void
    {
        $this->assertEquals(NormalBalance::CREDIT, AccountType::EQUITY->normalBalance());
    }

    public function test_revenue_has_credit_normal_balance(): void
    {
        $this->assertEquals(NormalBalance::CREDIT, AccountType::REVENUE->normalBalance());
    }

    public function test_expense_has_debit_normal_balance(): void
    {
        $this->assertEquals(NormalBalance::DEBIT, AccountType::EXPENSE->normalBalance());
    }

    public function test_derives_type_from_code_range_1000(): void
    {
        $this->assertEquals(AccountType::ASSET, AccountType::fromCodeRange(1000));
        $this->assertEquals(AccountType::ASSET, AccountType::fromCodeRange(1999));
    }

    public function test_derives_type_from_code_range_2000(): void
    {
        $this->assertEquals(AccountType::LIABILITY, AccountType::fromCodeRange(2000));
        $this->assertEquals(AccountType::LIABILITY, AccountType::fromCodeRange(2999));
    }

    public function test_derives_type_from_code_range_3000(): void
    {
        $this->assertEquals(AccountType::EQUITY, AccountType::fromCodeRange(3000));
        $this->assertEquals(AccountType::EQUITY, AccountType::fromCodeRange(3999));
    }

    public function test_derives_type_from_code_range_4000(): void
    {
        $this->assertEquals(AccountType::REVENUE, AccountType::fromCodeRange(4000));
        $this->assertEquals(AccountType::REVENUE, AccountType::fromCodeRange(4999));
    }

    public function test_derives_type_from_code_range_5000(): void
    {
        $this->assertEquals(AccountType::EXPENSE, AccountType::fromCodeRange(5000));
        $this->assertEquals(AccountType::EXPENSE, AccountType::fromCodeRange(5999));
    }

    public function test_throws_for_invalid_code_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AccountType::fromCodeRange(9000);
    }
}
