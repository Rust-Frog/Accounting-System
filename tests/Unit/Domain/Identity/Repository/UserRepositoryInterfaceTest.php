<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Entity\User;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class UserRepositoryInterfaceTest extends TestCase
{
    public function test_interface_defines_save_method(): void
    {
        $repository = $this->createInMemoryRepository();

        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $repository->save($user);
        $found = $repository->findById($user->id());

        $this->assertSame($user, $found);
    }

    public function test_interface_defines_find_by_username(): void
    {
        $repository = $this->createInMemoryRepository();

        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $repository->save($user);
        $found = $repository->findByUsername('john.doe');

        $this->assertSame($user, $found);
        $this->assertNull($repository->findByUsername('unknown'));
    }

    public function test_interface_defines_find_by_email(): void
    {
        $repository = $this->createInMemoryRepository();

        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $repository->save($user);
        $found = $repository->findByEmail(Email::fromString('john@example.com'));

        $this->assertSame($user, $found);
        $this->assertNull($repository->findByEmail(Email::fromString('unknown@example.com')));
    }

    public function test_interface_defines_exists_methods(): void
    {
        $repository = $this->createInMemoryRepository();

        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $repository->save($user);

        $this->assertTrue($repository->existsByUsername('john.doe'));
        $this->assertFalse($repository->existsByUsername('unknown'));
        $this->assertTrue($repository->existsByEmail(Email::fromString('john@example.com')));
        $this->assertFalse($repository->existsByEmail(Email::fromString('unknown@example.com')));
    }

    public function test_interface_defines_find_pending_users(): void
    {
        $repository = $this->createInMemoryRepository();

        $pendingUser = User::register(
            username: 'pending.user',
            email: Email::fromString('pending@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );

        $approvedUser = User::register(
            username: 'approved.user',
            email: Email::fromString('approved@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::generate()
        );
        $approvedUser->approve(UserId::generate());

        $repository->save($pendingUser);
        $repository->save($approvedUser);

        $pendingUsers = $repository->findPendingUsers();

        $this->assertCount(1, $pendingUsers);
        $this->assertContains($pendingUser, $pendingUsers);
    }

    private function createInMemoryRepository(): UserRepositoryInterface
    {
        return new InMemoryUserRepository();
    }
}

