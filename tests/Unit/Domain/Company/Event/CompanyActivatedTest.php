<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\Event;

use DateTimeImmutable;
use Domain\Company\Event\CompanyActivated;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class CompanyActivatedTest extends TestCase
{
    public function test_implements_domain_event(): void
    {
        $companyId = CompanyId::generate();
        $activatedBy = UserId::generate();
        $event = new CompanyActivated($companyId, $activatedBy);

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    public function test_has_correct_event_name(): void
    {
        $event = new CompanyActivated(CompanyId::generate(), UserId::generate());

        $this->assertEquals('company.activated', $event->eventName());
    }

    public function test_records_occurred_on_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $event = new CompanyActivated(CompanyId::generate(), UserId::generate());
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredOn());
        $this->assertLessThanOrEqual($after, $event->occurredOn());
    }

    public function test_exposes_company_id(): void
    {
        $companyId = CompanyId::generate();
        $event = new CompanyActivated($companyId, UserId::generate());

        $this->assertTrue($companyId->equals($event->companyId()));
    }

    public function test_exposes_activated_by(): void
    {
        $activatedBy = UserId::generate();
        $event = new CompanyActivated(CompanyId::generate(), $activatedBy);

        $this->assertTrue($activatedBy->equals($event->activatedBy()));
    }

    public function test_can_convert_to_array(): void
    {
        $companyId = CompanyId::generate();
        $activatedBy = UserId::generate();
        $event = new CompanyActivated($companyId, $activatedBy);

        $array = $event->toArray();

        $this->assertArrayHasKey('company_id', $array);
        $this->assertArrayHasKey('activated_by', $array);
        $this->assertArrayHasKey('occurred_on', $array);
        $this->assertEquals($companyId->toString(), $array['company_id']);
        $this->assertEquals($activatedBy->toString(), $array['activated_by']);
    }
}
