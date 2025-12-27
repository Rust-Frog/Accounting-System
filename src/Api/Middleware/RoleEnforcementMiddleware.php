<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Domain\Audit\Service\AuditChainServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Hardened middleware to enforce role-based access control.
 * Strictly separates Admin and Tenant capabilities.
 */
final class RoleEnforcementMiddleware
{
    public function __construct(
        private readonly ?AuditChainServiceInterface $auditService = null
    ) {
    }

    /**
     * Routes that ONLY admins can access.
     * @var array<string>
     */
    private const ADMIN_ONLY_PATTERNS = [
        // Company management
        'POST /api/v1/companies',
        'PUT /api/v1/companies/*',
        'DELETE /api/v1/companies/*',
        
        // User management
        'GET /api/v1/users',
        'PUT /api/v1/users/*',
        'POST /api/v1/users/*/approve',
        'POST /api/v1/users/*/deactivate',
        'DELETE /api/v1/users/*',
        
        // Transaction critical operations
        'POST /api/v1/companies/*/transactions/*/void',
        
        // System audit access
        'GET /api/v1/audit',
        'GET /api/v1/audit/*',
        'GET /api/v1/system/*',
        
        // Account management
        'DELETE /api/v1/companies/*/accounts/*',
        'POST /api/v1/companies/*/accounts/*/deactivate',
    ];

    /**
     * Routes that tenants are explicitly FORBIDDEN from accessing.
     * @var array<string>
     */
    private const TENANT_FORBIDDEN_PATTERNS = [
        'GET /api/v1/companies',  // Cannot list all companies
    ];

    /**
     * Process the request and enforce role permissions.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        // Skip role check if not authenticated (auth middleware handles this)
        if ($userId === null) {
            return $next($request);
        }

        // Get user role from request (set by auth middleware)
        $user = $request->getAttribute('user');
        $role = $user?->role()?->value ?? 'tenant';

        // Build route key for matching
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $routeKey = "$method $path";

        // Check admin-only routes
        if ($this->matchesPatterns($routeKey, self::ADMIN_ONLY_PATTERNS) && $role !== 'admin') {
            $this->logAccessDenied($userId, $routeKey, $role, 'admin_required');
            return JsonResponse::error(
                'Admin access required for this operation',
                403,
                null,
                'ADMIN_REQUIRED'
            );
        }

        // Check tenant-forbidden routes
        if ($role === 'tenant' && $this->matchesPatterns($routeKey, self::TENANT_FORBIDDEN_PATTERNS)) {
            $this->logAccessDenied($userId, $routeKey, $role, 'tenant_forbidden');
            return JsonResponse::error(
                'This operation is not available for tenant accounts',
                403,
                null,
                'TENANT_FORBIDDEN'
            );
        }

        return $next($request);
    }

    /**
     * Check if route matches any pattern in the list.
     *
     * @param array<string> $patterns
     */
    private function matchesPatterns(string $routeKey, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($routeKey, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match route against pattern with wildcard support.
     */
    private function matchesPattern(string $routeKey, string $pattern): bool
    {
        // First escape all regex special characters in the pattern
        $escaped = preg_quote($pattern, '/');

        // Then convert our escaped wildcard (\*) back to regex pattern for matching any path segment
        $regex = '/^' . str_replace('\*', '[^\/]+', $escaped) . '$/';

        return preg_match($regex, $routeKey) === 1;
    }

    /**
     * Log access denial for security auditing.
     */
    private function logAccessDenied(
        string $userId,
        string $routeKey,
        string $role,
        string $reason
    ): void {
        $message = sprintf(
            '[SECURITY] Access denied: user=%s role=%s route=%s reason=%s time=%s',
            $userId,
            $role,
            $routeKey,
            $reason,
            date('Y-m-d H:i:s')
        );

        // Log to audit chain for security tracking
        if ($this->auditService !== null) {
            $this->auditService->logSecurityEvent('access_denied', [
                'user_id' => $userId,
                'role' => $role,
                'route' => $routeKey,
                'reason' => $reason,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]);
        }

        // Keep error_log as fallback for development
        error_log($message);
    }
}
