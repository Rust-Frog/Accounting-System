<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Domain\Identity\Service\AuthenticationServiceInterface;
use Infrastructure\Service\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication middleware supporting both JWT and session tokens.
 */
final class AuthenticationMiddleware
{
    /** @var array<string> */
    private array $excludedPaths;

    public function __construct(
        private readonly AuthenticationServiceInterface $authService,
        private readonly ?JwtService $jwtService = null,
        array $excludedPaths = []
    ) {
        $this->excludedPaths = $excludedPaths;
    }

    /**
     * Process the request and validate authentication.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Skip authentication for excluded paths
        $path = $request->getUri()->getPath();

        if ($this->isExcluded($path)) {
            return $next($request);
        }

        // Extract token from Authorization header
        $token = $this->extractToken($request);
        if ($token === null) {
            return JsonResponse::error('Authentication required', 401);
        }

        // Try JWT validation first if service is available
        if ($this->jwtService !== null && $this->isJwt($token)) {
            return $this->authenticateWithJwt($request, $next, $token);
        }

        // Fall back to session-based authentication
        return $this->authenticateWithSession($request, $next, $token);
    }

    /**
     * Authenticate using JWT token.
     */
    private function authenticateWithJwt(
        ServerRequestInterface $request,
        callable $next,
        string $token
    ): ResponseInterface {
        $userId = $this->jwtService->getUserIdFromToken($token);
        if ($userId === null) {
            return JsonResponse::error('Invalid or expired token', 401);
        }

        // Add user ID to request (lightweight - no DB lookup)
        $request = $request->withAttribute('user_id', $userId->toString());
        $request = $request->withAttribute('auth_type', 'jwt');

        return $next($request);
    }

    /**
     * Authenticate using session token.
     */
    private function authenticateWithSession(
        ServerRequestInterface $request,
        callable $next,
        string $token
    ): ResponseInterface {
        try {
            $user = $this->authService->validateSession($token);
            if ($user === null) {
                return JsonResponse::error('Invalid or expired session', 401);
            }

            // Add authenticated user to request
            $request = $request->withAttribute('user', $user);
            $request = $request->withAttribute('user_id', $user->id()->toString());
            $request = $request->withAttribute('auth_type', 'session');

            return $next($request);
        } catch (\Throwable) {
            return JsonResponse::error('Authentication failed', 401);
        }
    }

    /**
     * Check if token appears to be a JWT (has 3 dot-separated parts).
     */
    private function isJwt(string $token): bool
    {
        return substr_count($token, '.') === 2;
    }

    /**
     * Extract bearer token from Authorization header.
     */
    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    /**
     * Check if path should skip authentication.
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $pattern) {
            // Special handling for root path - exact match only
            if ($pattern === '/') {
                if ($path === '/') {
                    return true;
                }
                continue;
            }

            if (str_starts_with($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
