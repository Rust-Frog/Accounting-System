<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Identity\Entity\Session;
use Domain\Identity\ValueObject\SessionId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleException;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_creates_session_for_user(): void
    {
        $userId = UserId::generate();
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $session = Session::create($userId, $ipAddress, $userAgent);

        $this->assertInstanceOf(SessionId::class, $session->id());
        $this->assertTrue($userId->equals($session->userId()));
        $this->assertEquals($ipAddress, $session->ipAddress());
        $this->assertEquals($userAgent, $session->userAgent());
        $this->assertTrue($session->isActive());
    }

    public function test_session_has_expiration(): void
    {
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');

        $this->assertInstanceOf(DateTimeImmutable::class, $session->expiresAt());
        $this->assertGreaterThan(new DateTimeImmutable(), $session->expiresAt());
    }

    public function test_session_can_be_refreshed(): void
    {
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');
        $originalExpiry = $session->expiresAt();

        // Wait a tiny bit to ensure time difference
        usleep(1000);
        $session->refresh();

        $this->assertGreaterThan($originalExpiry, $session->expiresAt());
    }

    public function test_session_can_be_terminated(): void
    {
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');

        $session->terminate();

        $this->assertFalse($session->isActive());
    }

    public function test_terminated_session_cannot_be_refreshed(): void
    {
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');
        $session->terminate();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Cannot refresh terminated session');

        $session->refresh();
    }

    public function test_checks_if_session_is_expired(): void
    {
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');

        $this->assertFalse($session->isExpired());
    }

    public function test_session_records_last_activity(): void
    {
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');
        $before = new DateTimeImmutable();

        $session->recordActivity();

        $this->assertNotNull($session->lastActivityAt());
        $this->assertGreaterThanOrEqual($before, $session->lastActivityAt());
    }

    public function test_session_has_created_at_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $session = Session::create(UserId::generate(), '192.168.1.1', 'Agent');
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $session->createdAt());
        $this->assertLessThanOrEqual($after, $session->createdAt());
    }
}
