<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use Domain\Identity\Service\PasswordServiceInterface;

/**
 * BCrypt password hashing service implementation.
 */
final class BcryptPasswordHashingService implements PasswordServiceInterface
{
    private const DEFAULT_COST = 12;

    private int $cost;

    public function __construct(int $cost = self::DEFAULT_COST)
    {
        $this->cost = $cost;
    }

    /**
     * Hash a password using BCrypt.
     */
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    /**
     * Verify a password against a hash.
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed (e.g., cost changed).
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }
}
