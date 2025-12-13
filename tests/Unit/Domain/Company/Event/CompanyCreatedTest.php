<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\Event;

use DateTimeImmutable;
use Domain\Company\Event\CompanyCreated;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class CompanyCreatedTest extends TestCase
{
    public function test_implements_domain_event(): void
    {
        $companyId = CompanyId::generate();
        $event = new CompanyCreated($companyId, 'Test Company');

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    public function test_has_correct_event_name(): void
    {
        $event = new CompanyCreated(CompanyId::generate(), 'Test Company');

        $this->assertEquals('company.created', $event->eventName());
    }

    public function test_records_occurred_on_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $event = new CompanyCreated(CompanyId::generate(), 'Test Company');
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredOn());
        $this->assertLessThanOrEqual($after, $event->occurredOn());
    }

    public function test_exposes_company_id(): void
    {
        $companyId = CompanyId::generate();
        $event = new CompanyCreated($companyId, 'Test Company');

        $this->assertTrue($companyId->equals($event->companyId()));
    }

    public function test_exposes_company_name(): void
    {
        $event = new CompanyCreated(CompanyId::generate(), 'Test Company');

        $this->assertEquals('Test Company', $event->companyName());
    }

    public function test_can_convert_to_array(): void
    {
        $companyId = CompanyId::generate();
        $event = new CompanyCreated($companyId, 'Test Company');

        $array = $event->toArray();

        $this->assertArrayHasKey('company_id', $array);
        $this->assertArrayHasKey('company_name', $array);
        $this->assertArrayHasKey('occurred_on', $array);
        $this->assertEquals($companyId->toString(), $array['company_id']);
        $this->assertEquals('Test Company', $array['company_name']);
    }
}
