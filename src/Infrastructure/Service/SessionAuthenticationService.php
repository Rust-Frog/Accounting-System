<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use DateTimeImmutable;
use Domain\Identity\Entity\Session;
use Domain\Identity\Entity\User;
use Domain\Identity\Service\AuthenticationServiceInterface;
use Domain\Identity\Service\PasswordServiceInterface;
use Domain\Identity\ValueObject\SessionId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\AuthenticationException;
use Infrastructure\Persistence\Mysql\Connection\PdoConnectionFactory;
use Domain\Identity\Repository\UserRepositoryInterface;
use PDO;
use RuntimeException;

/**
 * Session-based authentication service implementation.
 */
final class SessionAuthenticationService implements AuthenticationServiceInterface
{
    private const TOKEN_LENGTH = 64;
    private const DEFAULT_SESSION_HOURS = 24;

    private int $sessionHours = self::DEFAULT_SESSION_HOURS;

    public function __construct(
        private readonly \Predis\ClientInterface $redis,
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordServiceInterface $passwordService
    ) {
    }

    /**
     * Authenticate user with credentials.
     *
     * @throws AuthenticationException
     */
    public function authenticate(string $username, string $password, string $ipAddress, string $userAgent): Session
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user === null || !$user->isActive()) {
             throw new AuthenticationException('Invalid credentials');
        }

        if (!$this->passwordService->verify($password, $user->passwordHash())) {
            throw new AuthenticationException('Invalid credentials');
        }

        // Create new session
        return $this->createSession($user, $ipAddress, $userAgent);
    }

    /**
     * Create a new session for a user.
     * Stores session data in Redis with TTL.
     */
    private function createSession(User $user, string $ipAddress, string $userAgent): Session
    {
        $sessionId = SessionId::generate();
        $token = $this->generateToken();
        $hashedToken = hash('sha256', $token);
        
        $expiresAt = new DateTimeImmutable("+{$this->sessionHours} hours");
        $createdAt = new DateTimeImmutable();

        $sessionData = [
            'id' => $sessionId->toString(),
            'user_id' => $user->id()->toString(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ];

        // Store session in Redis with Expiry
        $key = 'session:' . $hashedToken;
        $ttl = $this->sessionHours * 3600;
        
        $this->redis->setex($key, $ttl, json_encode($sessionData));

        // Track user's sessions (for logout all)
        // Add to Set and refresh Set expiry
        $userSessionsKey = 'user_sessions:' . $user->id()->toString();
        $this->redis->sadd($userSessionsKey, [$hashedToken]);
        $this->redis->expire($userSessionsKey, $ttl);

        return new Session(
            $sessionId,
            $user->id(),
            $ipAddress,
            $userAgent,
            true, // isActive
            $expiresAt,
            $createdAt, // lastActivityAt
            $createdAt,
            $token // Return unhashed token to client
        );
    }

    /**
     * Validate a session token and return the User if valid.
     */
    public function validateSession(string $sessionToken): ?User
    {
        $hashedToken = hash('sha256', $sessionToken);
        $key = 'session:' . $hashedToken;

        $json = $this->redis->get($key);

        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['user_id'])) {
            return null;
        }

        return $this->userRepository->findById(UserId::fromString($data['user_id']));
    }

    /**
     * Invalidate a session (logout).
     */
    public function terminateSession(string $sessionToken): void
    {
        $hashedToken = hash('sha256', $sessionToken);
        $key = 'session:' . $hashedToken;
        
        // Retrieve userId to remove from User Set
        $json = $this->redis->get($key);
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['user_id'])) {
                $this->redis->srem('user_sessions:' . $data['user_id'], $hashedToken);
            }
        }

        $this->redis->del($key);
    }

    public function terminateAllUserSessions(User $user): void
    {
        $userSessionsKey = 'user_sessions:' . $user->id()->toString();
        $members = $this->redis->smembers($userSessionsKey);

        foreach ($members as $hashedToken) {
            $this->redis->del('session:' . $hashedToken);
        }

        $this->redis->del($userSessionsKey);
    }

    /**
     * Clean up expired sessions.
     * Redis handles this automatically via TTL.
     */
    public function cleanupExpiredSessions(): int
    {
        // No-op for Redis
        return 0;
    }

    /**
     * Extend a session's expiration.
     */
    public function extendSession(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $key = 'session:' . $hashedToken;
        $ttl = $this->sessionHours * 3600;

        // Reset TTL
        return (bool) $this->redis->expire($key, $ttl);
    }

    /**
     * Generate a cryptographically secure random token.
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
    }
}
