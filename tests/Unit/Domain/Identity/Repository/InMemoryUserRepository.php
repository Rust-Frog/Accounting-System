<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Repository;

use Domain\Identity\Entity\User;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Email;

/**
 * In-memory implementation of UserRepositoryInterface for testing.
 * Extracted from UserRepositoryInterfaceTest to reduce test complexity.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->toString()] = $user;
    }

    public function findById(UserId $userId): ?User
    {
        return $this->users[$userId->toString()] ?? null;
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->username() === $username) {
                return $user;
            }
        }
        return null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }
        return null;
    }

    public function existsByUsername(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /**
     * @return array<User>
     */
    public function findPendingUsers(): array
    {
        return array_values(array_filter(
            $this->users,
            fn(User $user) => $user->registrationStatus()->isPending()
        ));
    }

    public function hasAnyAdmin(): bool
    {
        foreach ($this->users as $user) {
            if ($user->role()->isAdmin()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<User>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        return array_slice(array_values($this->users), $offset, $limit);
    }

    /**
     * @return array<User>
     */
    public function findByRole(string $role): array
    {
        return array_values(array_filter(
            $this->users,
            fn(User $user) => $user->role()->value === $role
        ));
    }
}
