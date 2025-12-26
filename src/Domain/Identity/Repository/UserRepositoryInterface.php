<?php

declare(strict_types=1);

namespace Domain\Identity\Repository;

use Domain\Identity\Entity\User;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Email;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $userId): ?User;

    public function findByUsername(string $username): ?User;

    public function findByEmail(Email $email): ?User;

    public function existsByUsername(string $username): bool;

    public function existsByEmail(Email $email): bool;

    /**
     * @return array<User>
     */
    public function findPendingUsers(): array;

    public function hasAnyAdmin(): bool;

    /**
     * @return array<User>
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * @return array<User>
     */
    public function findByRole(string $role): array;
}
