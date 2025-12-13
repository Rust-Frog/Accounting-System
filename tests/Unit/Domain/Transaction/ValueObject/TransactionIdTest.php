<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\TransactionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TransactionIdTest extends TestCase
{
    public function test_generates_valid_transaction_id(): void
    {
        $transactionId = TransactionId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $transactionId->toString()
        );
    }

    public function test_creates_transaction_id_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $transactionId = TransactionId::fromString($uuidString);

        $this->assertEquals($uuidString, $transactionId->toString());
    }

    public function test_rejects_invalid_transaction_id_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransactionId::fromString('invalid-uuid');
    }

    public function test_equals_returns_true_for_same_id(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = TransactionId::fromString($uuidString);
        $id2 = TransactionId::fromString($uuidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_equals_returns_false_for_different_id(): void
    {
        $id1 = TransactionId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $id2 = TransactionId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($id1->equals($id2));
    }
}
