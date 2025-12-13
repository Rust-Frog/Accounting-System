<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\Entity;

use DateTimeImmutable;
use Domain\Company\Entity\Company;
use Domain\Company\ValueObject\Address;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\CompanyStatus;
use Domain\Company\ValueObject\TaxIdentifier;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    public function test_creates_company_with_valid_data(): void
    {
        $company = Company::create(
            companyName: 'Acme Corp',
            legalName: 'Acme Corporation Inc.',
            taxId: TaxIdentifier::fromString('123-456-789'),
            address: $this->createAddress(),
            currency: Currency::PHP
        );

        $this->assertInstanceOf(CompanyId::class, $company->id());
        $this->assertEquals('Acme Corp', $company->companyName());
        $this->assertEquals('Acme Corporation Inc.', $company->legalName());
        $this->assertEquals('123-456-789', $company->taxId()->toString());
        $this->assertEquals(Currency::PHP, $company->currency());
        $this->assertEquals(CompanyStatus::PENDING, $company->status());
    }

    public function test_new_company_starts_as_pending(): void
    {
        $company = $this->createCompany();

        $this->assertTrue($company->status()->isPending());
        $this->assertFalse($company->status()->canOperate());
    }

    public function test_company_can_be_activated_by_admin(): void
    {
        $company = $this->createCompany();
        $adminId = UserId::generate();

        $company->activate($adminId);

        $this->assertEquals(CompanyStatus::ACTIVE, $company->status());
        $this->assertTrue($company->status()->canOperate());
    }

    public function test_only_pending_company_can_be_activated(): void
    {
        $company = $this->createCompany();
        $adminId = UserId::generate();
        $company->activate($adminId);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only pending companies can be activated');

        $company->activate($adminId);
    }

    public function test_company_can_be_suspended(): void
    {
        $company = $this->createCompany();
        $adminId = UserId::generate();
        $company->activate($adminId);

        $company->suspend($adminId, 'Violation of terms');

        $this->assertEquals(CompanyStatus::SUSPENDED, $company->status());
        $this->assertFalse($company->status()->canOperate());
    }

    public function test_only_active_company_can_be_suspended(): void
    {
        $company = $this->createCompany();
        $adminId = UserId::generate();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only active companies can be suspended');

        $company->suspend($adminId, 'Reason');
    }

    public function test_suspended_company_can_be_reactivated(): void
    {
        $company = $this->createCompany();
        $adminId = UserId::generate();
        $company->activate($adminId);
        $company->suspend($adminId, 'Reason');

        $company->reactivate($adminId);

        $this->assertEquals(CompanyStatus::ACTIVE, $company->status());
    }

    public function test_only_suspended_company_can_be_reactivated(): void
    {
        $company = $this->createCompany();
        $adminId = UserId::generate();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only suspended companies can be reactivated');

        $company->reactivate($adminId);
    }

    public function test_company_name_can_be_updated(): void
    {
        $company = $this->createCompany();

        $company->updateName('New Company Name', 'New Legal Name Inc.');

        $this->assertEquals('New Company Name', $company->companyName());
        $this->assertEquals('New Legal Name Inc.', $company->legalName());
    }

    public function test_company_address_can_be_updated(): void
    {
        $company = $this->createCompany();
        $newAddress = Address::create(
            street: '456 New Street',
            city: 'Cebu',
            state: 'Cebu',
            postalCode: '6000',
            country: 'Philippines'
        );

        $company->updateAddress($newAddress);

        $this->assertTrue($newAddress->equals($company->address()));
    }

    public function test_company_has_created_at_timestamp(): void
    {
        $before = new DateTimeImmutable();
        $company = $this->createCompany();
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $company->createdAt());
        $this->assertLessThanOrEqual($after, $company->createdAt());
    }

    public function test_company_tracks_updated_at(): void
    {
        $company = $this->createCompany();
        $originalUpdatedAt = $company->updatedAt();

        usleep(1000); // Small delay
        $company->updateName('Updated Name', 'Updated Legal');

        $this->assertGreaterThan($originalUpdatedAt, $company->updatedAt());
    }

    public function test_releases_domain_events(): void
    {
        $company = $this->createCompany();

        $events = $company->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('company.created', $events[0]->eventName());
    }

    public function test_activation_records_event(): void
    {
        $company = $this->createCompany();
        $company->releaseEvents(); // Clear creation event

        $company->activate(UserId::generate());
        $events = $company->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('company.activated', $events[0]->eventName());
    }

    private function createCompany(): Company
    {
        return Company::create(
            companyName: 'Test Company',
            legalName: 'Test Company Inc.',
            taxId: TaxIdentifier::fromString('123-456-789'),
            address: $this->createAddress(),
            currency: Currency::PHP
        );
    }

    private function createAddress(): Address
    {
        return Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );
    }
}
