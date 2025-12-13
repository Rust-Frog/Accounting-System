<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ChartOfAccounts\ValueObject;

use Domain\ChartOfAccounts\ValueObject\AccountId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccountIdTest extends TestCase
{
    public function test_generates_valid_account_id(): void
    {
        $accountId = AccountId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $accountId->toString()
        );
    }

    public function test_creates_account_id_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $accountId = AccountId::fromString($uuidString);

        $this->assertEquals($uuidString, $accountId->toString());
    }

    public function test_rejects_invalid_account_id_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AccountId::fromString('invalid-uuid');
    }

    public function test_equals_returns_true_for_same_account_id(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $accountId1 = AccountId::fromString($uuidString);
        $accountId2 = AccountId::fromString($uuidString);

        $this->assertTrue($accountId1->equals($accountId2));
    }

    public function test_equals_returns_false_for_different_account_id(): void
    {
        $accountId1 = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $accountId2 = AccountId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($accountId1->equals($accountId2));
    }
}
