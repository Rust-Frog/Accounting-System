<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ChartOfAccounts\ValueObject;

use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccountCodeTest extends TestCase
{
    public function test_creates_account_code_from_valid_string(): void
    {
        $code = AccountCode::fromString('1000');
        $this->assertEquals('1000', $code->toString());
    }

    public function test_creates_account_code_from_integer(): void
    {
        $code = AccountCode::fromInt(1000);
        $this->assertEquals('1000', $code->toString());
        $this->assertEquals(1000, $code->toInt());
    }

    public function test_rejects_code_below_1000(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account code must be between 1000 and 5999');
        AccountCode::fromInt(999);
    }

    public function test_rejects_code_above_5999(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account code must be between 1000 and 5999');
        AccountCode::fromInt(6000);
    }

    public function test_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AccountCode::fromString('');
    }

    public function test_rejects_non_numeric_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AccountCode::fromString('ABCD');
    }

    public function test_derives_account_type_for_asset(): void
    {
        $code = AccountCode::fromInt(1500);
        $this->assertEquals(AccountType::ASSET, $code->accountType());
    }

    public function test_derives_account_type_for_liability(): void
    {
        $code = AccountCode::fromInt(2500);
        $this->assertEquals(AccountType::LIABILITY, $code->accountType());
    }

    public function test_derives_account_type_for_equity(): void
    {
        $code = AccountCode::fromInt(3500);
        $this->assertEquals(AccountType::EQUITY, $code->accountType());
    }

    public function test_derives_account_type_for_revenue(): void
    {
        $code = AccountCode::fromInt(4500);
        $this->assertEquals(AccountType::REVENUE, $code->accountType());
    }

    public function test_derives_account_type_for_expense(): void
    {
        $code = AccountCode::fromInt(5500);
        $this->assertEquals(AccountType::EXPENSE, $code->accountType());
    }

    public function test_equals_returns_true_for_same_code(): void
    {
        $code1 = AccountCode::fromInt(1000);
        $code2 = AccountCode::fromInt(1000);

        $this->assertTrue($code1->equals($code2));
    }

    public function test_equals_returns_false_for_different_code(): void
    {
        $code1 = AccountCode::fromInt(1000);
        $code2 = AccountCode::fromInt(2000);

        $this->assertFalse($code1->equals($code2));
    }

    public function test_pads_short_codes(): void
    {
        $code = AccountCode::fromString('1000');
        $this->assertEquals('1000', $code->toString());
    }
}
