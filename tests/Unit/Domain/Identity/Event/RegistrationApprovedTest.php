<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Event;

use DateTimeImmutable;
use Domain\Identity\Event\RegistrationApproved;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class RegistrationApprovedTest extends TestCase
{
    public function test_implements_domain_event(): void
    {
        $userId = UserId::generate();
        $approverId = UserId::generate();
        $event = new RegistrationApproved($userId, $approverId);

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    public function test_has_correct_event_name(): void
    {
        $userId = UserId::generate();
        $approverId = UserId::generate();
        $event = new RegistrationApproved($userId, $approverId);

        $this->assertEquals('registration.approved', $event->eventName());
    }

    public function test_records_occurred_on_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $event = new RegistrationApproved(UserId::generate(), UserId::generate());
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredOn());
        $this->assertLessThanOrEqual($after, $event->occurredOn());
    }

    public function test_exposes_user_id(): void
    {
        $userId = UserId::generate();
        $approverId = UserId::generate();
        $event = new RegistrationApproved($userId, $approverId);

        $this->assertTrue($userId->equals($event->userId()));
    }

    public function test_exposes_approver_id(): void
    {
        $userId = UserId::generate();
        $approverId = UserId::generate();
        $event = new RegistrationApproved($userId, $approverId);

        $this->assertTrue($approverId->equals($event->approverId()));
    }

    public function test_can_convert_to_array(): void
    {
        $userId = UserId::generate();
        $approverId = UserId::generate();
        $event = new RegistrationApproved($userId, $approverId);

        $array = $event->toArray();

        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('approver_id', $array);
        $this->assertArrayHasKey('occurred_on', $array);
        $this->assertEquals($userId->toString(), $array['user_id']);
        $this->assertEquals($approverId->toString(), $array['approver_id']);
    }
}
