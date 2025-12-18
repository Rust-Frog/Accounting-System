<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Entity\User;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\Service\AuthenticationServiceInterface;
use Domain\Identity\ValueObject\Role;
use Domain\Shared\ValueObject\Email;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication controller for user registration, login, and session management.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        // Validate required fields
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return JsonResponse::error("Missing required field: $field", 422);
            }
        }

        // Check if email already exists
        $email = Email::fromString($body['email']);
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            return JsonResponse::error('Email already registered', 409);
        }

        // Create user
        try {
            $companyId = isset($body['company_id']) 
                ? CompanyId::fromString($body['company_id']) 
                : null;

            // CRITICAL: Prevent privilege escalation. Public registration is always TENANT.
            // Admins must be created via Setup or Admin Console.
            $role = Role::TENANT;

            $user = User::register(
                $body['username'],
                $email,
                $body['password'],
                $role,
                $companyId
            );

            $this->userRepository->save($user);

            return JsonResponse::created([
                'id' => $user->id()->toString(),
                'username' => $user->username(),
                'email' => $user->email()->toString(),
                'role' => $user->role()->value,
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/auth/login
     * 
     * Accepts username or email for identification.
     * Requires password + OTP for users with 2FA enabled.
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $identifier = $body['username'] ?? $body['email'] ?? null;
        $password = $body['password'] ?? null;
        
        if (empty($identifier) || empty($password)) {
            return JsonResponse::error('Username/email and password are required', 422);
        }

        $user = $this->findUserByIdentifier($identifier);
        
        if ($user === null || !$user->verifyPassword($password)) {
            // Use generic error message for security (prevent enumeration)
            return JsonResponse::error('Invalid credentials', 401);
        }

        if ($user->isOtpEnabled()) {
            if (empty($body['otp_code'])) {
                return JsonResponse::error('OTP code is required', 422);
            }

            if (!$this->verifyOtp($user, $body['otp_code'])) {
                return JsonResponse::error('Invalid OTP code', 401);
            }
        }

        // Generate session/JWT
        try {
            $session = $this->authService->authenticate(
                $user->username(),
                $password,
                $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                $request->getHeaderLine('User-Agent') ?: 'unknown'
            );

            return JsonResponse::success([
                'token' => $session->token(),
                'expires_at' => $session->expiresAt()->format('Y-m-d\TH:i:s\Z'),
                'user_id' => $session->userId()->toString(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Authentication failed', 401);
        }
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return $this->userRepository->findByEmail(Email::fromString($identifier));
        }
        return $this->userRepository->findByUsername($identifier);
    }

    private function verifyOtp(User $user, string $code): bool
    {
        $totpService = new \Infrastructure\Service\TotpService();
        return $totpService->verify($user->otpSecret(), $code);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        // Extract token from header for logout
        $header = $request->getHeaderLine('Authorization');
        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return JsonResponse::error('No active session', 401);
        }

        $token = substr($header, 7);

        try {
            $this->authService->terminateSession($token);
            return JsonResponse::noContent();
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to logout', 500);
        }
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            return JsonResponse::error('Not authenticated', 401);
        }

        $user = $this->userRepository->findById(
            \Domain\Identity\ValueObject\UserId::fromString($userId)
        );

        if ($user === null) {
            return JsonResponse::error('User not found', 404);
        }

        return JsonResponse::success([
            'id' => $user->id()->toString(),
            'username' => $user->username(),
            'email' => $user->email()->toString(),
            'role' => $user->role()->value,
            'company_id' => $user->companyId()?->toString(),
            'is_active' => $user->isActive(),
            'created_at' => $user->createdAt()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
