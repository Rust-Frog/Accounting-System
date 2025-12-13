<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObject;

use Domain\Identity\ValueObject\SessionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SessionIdTest extends TestCase
{
    public function test_generates_valid_session_id(): void
    {
        $sessionId = SessionId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $sessionId->toString()
        );
    }

    public function test_creates_session_id_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $sessionId = SessionId::fromString($uuidString);

        $this->assertEquals($uuidString, $sessionId->toString());
    }

    public function test_rejects_invalid_session_id_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SessionId::fromString('invalid-uuid');
    }

    public function test_equals_returns_true_for_same_session_id(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $sessionId1 = SessionId::fromString($uuidString);
        $sessionId2 = SessionId::fromString($uuidString);

        $this->assertTrue($sessionId1->equals($sessionId2));
    }
}
