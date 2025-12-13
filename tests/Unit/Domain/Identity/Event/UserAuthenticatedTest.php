<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Event;

use DateTimeImmutable;
use Domain\Identity\Event\UserAuthenticated;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class UserAuthenticatedTest extends TestCase
{
    public function test_implements_domain_event(): void
    {
        $userId = UserId::generate();
        $event = new UserAuthenticated($userId);

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    public function test_has_correct_event_name(): void
    {
        $userId = UserId::generate();
        $event = new UserAuthenticated($userId);

        $this->assertEquals('user.authenticated', $event->eventName());
    }

    public function test_records_occurred_on_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $event = new UserAuthenticated(UserId::generate());
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredOn());
        $this->assertLessThanOrEqual($after, $event->occurredOn());
    }

    public function test_exposes_user_id(): void
    {
        $userId = UserId::generate();
        $event = new UserAuthenticated($userId);

        $this->assertTrue($userId->equals($event->userId()));
    }

    public function test_can_convert_to_array(): void
    {
        $userId = UserId::generate();
        $event = new UserAuthenticated($userId);

        $array = $event->toArray();

        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('occurred_on', $array);
        $this->assertEquals($userId->toString(), $array['user_id']);
    }

    public function test_can_include_ip_address(): void
    {
        $userId = UserId::generate();
        $ipAddress = '192.168.1.1';
        $event = new UserAuthenticated($userId, $ipAddress);

        $this->assertEquals($ipAddress, $event->ipAddress());
        $this->assertEquals($ipAddress, $event->toArray()['ip_address']);
    }
}
