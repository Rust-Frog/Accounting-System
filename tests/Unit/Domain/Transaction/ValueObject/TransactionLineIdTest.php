<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\TransactionLineId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TransactionLineIdTest extends TestCase
{
    public function test_generates_valid_line_id(): void
    {
        $lineId = TransactionLineId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $lineId->toString()
        );
    }

    public function test_creates_line_id_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $lineId = TransactionLineId::fromString($uuidString);

        $this->assertEquals($uuidString, $lineId->toString());
    }

    public function test_rejects_invalid_line_id_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransactionLineId::fromString('invalid-uuid');
    }

    public function test_equals_returns_true_for_same_id(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = TransactionLineId::fromString($uuidString);
        $id2 = TransactionLineId::fromString($uuidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_equals_returns_false_for_different_id(): void
    {
        $id1 = TransactionLineId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $id2 = TransactionLineId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($id1->equals($id2));
    }
}
