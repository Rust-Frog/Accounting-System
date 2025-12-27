<?php

declare(strict_types=1);

namespace Domain\Identity\Service;

use Domain\Identity\Entity\User;
use Domain\Identity\Entity\Session;

/**
 * Port for authentication operations.
 * Implementation should be in Infrastructure layer.
 */
interface AuthenticationServiceInterface
{
    /**
     * Authenticate user with credentials.
     *
     * @throws \Domain\Shared\Exception\AuthenticationException
     */
    public function authenticate(string $username, string $password, string $ipAddress, string $userAgent): Session;

    /**
     * Validate an existing session.
     */
    public function validateSession(string $sessionToken): ?User;

    /**
     * Terminate a session (logout).
     */
    public function terminateSession(string $sessionToken): void;

    /**
     * Terminate all sessions for a user.
     */
    public function terminateAllUserSessions(User $user): void;
}
