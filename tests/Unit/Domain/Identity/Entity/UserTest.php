<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Entity\User;
use Domain\Identity\ValueObject\RegistrationStatus;
use Domain\Identity\ValueObject\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\AuthenticationException;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\Exception\InvalidArgumentException;
use Domain\Shared\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_creates_tenant_user_with_valid_data(): void
    {
        $companyId = CompanyId::generate();
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: $companyId
        );

        $this->assertEquals('john.doe', $user->username());
        $this->assertEquals('john@example.com', $user->email()->toString());
        $this->assertEquals(Role::TENANT, $user->role());
        $this->assertEquals(RegistrationStatus::PENDING, $user->registrationStatus());
        $this->assertTrue($user->isActive());
        $this->assertInstanceOf(UserId::class, $user->id());
    }

    public function test_creates_admin_user_without_company(): void
    {
        $user = User::register(
            username: 'admin.user',
            email: Email::fromString('admin@example.com'),
            password: 'Password123',
            role: Role::ADMIN,
            companyId: null
        );

        $this->assertEquals(Role::ADMIN, $user->role());
        $this->assertNull($user->companyId());
    }

    public function test_hashes_password_on_creation(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::ADMIN,
            companyId: null
        );

        $this->assertNotEquals('Password123', $user->passwordHash());
        $this->assertTrue(password_verify('Password123', $user->passwordHash()));
    }

    public function test_rejects_password_shorter_than_8_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');

        User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Pass1',
            role: Role::ADMIN,
            companyId: null
        );
    }

    public function test_rejects_password_without_uppercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain uppercase letter');

        User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'password123',
            role: Role::ADMIN,
            companyId: null
        );
    }

    public function test_rejects_password_without_lowercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain lowercase letter');

        User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'PASSWORD123',
            role: Role::ADMIN,
            companyId: null
        );
    }

    public function test_rejects_password_without_digit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must contain digit');

        User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Passworddd',
            role: Role::ADMIN,
            companyId: null
        );
    }

    public function test_admin_cannot_have_company(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admins cannot belong to a company');

        User::register(
            username: 'admin.user',
            email: Email::fromString('admin@example.com'),
            password: 'Password123',
            role: Role::ADMIN,
            companyId: CompanyId::generate()
        );
    }

    public function test_tenant_must_have_company(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenants must belong to a company');

        User::register(
            username: 'tenant.user',
            email: Email::fromString('tenant@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: null
        );
    }

    public function test_approved_user_can_authenticate(): void
    {
        $user = $this->createApprovedTenantUser();

        $result = $user->authenticate('Password123');

        $this->assertTrue($result);
    }

    public function test_authentication_fails_with_wrong_password(): void
    {
        $user = $this->createApprovedTenantUser();

        $result = $user->authenticate('WrongPassword123');

        $this->assertFalse($result);
    }

    public function test_pending_user_cannot_authenticate(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User registration pending approval');

        $user->authenticate('Password123');
    }

    public function test_declined_user_cannot_authenticate(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );
        $adminId = UserId::generate();
        $user->decline($adminId);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User registration was declined');

        $user->authenticate('Password123');
    }

    public function test_deactivated_user_cannot_authenticate(): void
    {
        $user = $this->createApprovedTenantUser();
        $user->deactivate();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User account is deactivated');

        $user->authenticate('Password123');
    }

    public function test_user_can_be_approved(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );
        $adminId = UserId::generate();

        $user->approve($adminId);

        $this->assertEquals(RegistrationStatus::APPROVED, $user->registrationStatus());
    }

    public function test_user_cannot_self_approve(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Users cannot approve themselves');

        $user->approve($user->id());
    }

    public function test_only_pending_user_can_be_approved(): void
    {
        $user = $this->createApprovedTenantUser();
        $adminId = UserId::generate();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only pending registrations can be approved');

        $user->approve($adminId);
    }

    public function test_user_can_be_declined(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );
        $adminId = UserId::generate();

        $user->decline($adminId);

        $this->assertEquals(RegistrationStatus::DECLINED, $user->registrationStatus());
    }

    public function test_records_last_login_on_successful_authentication(): void
    {
        $user = $this->createApprovedTenantUser();
        $beforeLogin = new DateTimeImmutable();

        $user->authenticate('Password123');

        $this->assertNotNull($user->lastLoginAt());
        $this->assertGreaterThanOrEqual($beforeLogin, $user->lastLoginAt());
    }

    public function test_releases_domain_events(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $events = $user->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('user.registered', $events[0]->eventName());
    }

    public function test_release_events_clears_event_list(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $user->releaseEvents();
        $secondRelease = $user->releaseEvents();

        $this->assertEmpty($secondRelease);
    }

    private function createApprovedTenantUser(): User
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );
        $adminId = UserId::generate();
        $user->approve($adminId);
        $user->releaseEvents(); // Clear registration event

        return $user;
    }
}
