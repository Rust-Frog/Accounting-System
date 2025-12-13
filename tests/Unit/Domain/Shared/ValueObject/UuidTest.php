<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObject;

use Domain\Shared\ValueObject\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_generates_valid_uuid(): void
    {
        $uuid = Uuid::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid->toString()
        );
    }

    public function test_creates_uuid_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = Uuid::fromString($uuidString);

        $this->assertEquals($uuidString, $uuid->toString());
    }

    public function test_rejects_invalid_uuid_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uuid::fromString('invalid-uuid');
    }

    public function test_rejects_empty_uuid_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uuid::fromString('');
    }

    public function test_equals_returns_true_for_same_uuid(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $uuid1 = Uuid::fromString($uuidString);
        $uuid2 = Uuid::fromString($uuidString);

        $this->assertTrue($uuid1->equals($uuid2));
    }

    public function test_equals_returns_false_for_different_uuid(): void
    {
        $uuid1 = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        $uuid2 = Uuid::fromString('550e8400-e29b-41d4-a716-446655440001');

        $this->assertFalse($uuid1->equals($uuid2));
    }

    public function test_generated_uuids_are_unique(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = Uuid::generate()->toString();
        }

        $this->assertEquals(100, count(array_unique($uuids)));
    }
}
