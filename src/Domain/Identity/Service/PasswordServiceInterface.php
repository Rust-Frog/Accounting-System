<?php

declare(strict_types=1);

namespace Domain\Identity\Service;

/**
 * Port for password operations.
 * Implementation should be in Infrastructure layer.
 */
interface PasswordServiceInterface
{
    /**
     * Hash a plain text password.
     */
    public function hash(string $plainPassword): string;

    /**
     * Verify a password against a hash.
     */
    public function verify(string $plainPassword, string $hashedPassword): bool;

    /**
     * Check if password needs rehashing (e.g., cost changed).
     */
    public function needsRehash(string $hashedPassword): bool;
}
