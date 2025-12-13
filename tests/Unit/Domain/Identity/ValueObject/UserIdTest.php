<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObject;

use Domain\Identity\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function test_generates_valid_user_id(): void
    {
        $userId = UserId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $userId->toString()
        );
    }

    public function test_creates_user_id_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $userId = UserId::fromString($uuidString);

        $this->assertEquals($uuidString, $userId->toString());
    }

    public function test_rejects_invalid_user_id_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UserId::fromString('invalid-uuid');
    }

    public function test_equals_returns_true_for_same_user_id(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $userId1 = UserId::fromString($uuidString);
        $userId2 = UserId::fromString($uuidString);

        $this->assertTrue($userId1->equals($userId2));
    }

    public function test_equals_returns_false_for_different_user_id(): void
    {
        $userId1 = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $userId2 = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($userId1->equals($userId2));
    }
}
