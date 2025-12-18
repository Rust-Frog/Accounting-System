<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Middleware to scope tenant access to their own company.
 * Tenants can only access data within their assigned company.
 */
final class CompanyScopingMiddleware
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Process the request and enforce company scoping for tenants.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $next($request);
        }

        if ($this->isAdmin($user)) {
            return $next($request);
        }

        $path = $request->getUri()->getPath();
        $companyIdFromRoute = $this->extractCompanyId($path);

        if ($companyIdFromRoute !== null) {
            $errorResponse = $this->validateTenantAccess($user, $companyIdFromRoute);
            if ($errorResponse !== null) {
                return $errorResponse;
            }
        }

        $request = $this->enrichRequest($request, $user);

        return $next($request);
    }

    private function isAdmin(mixed $user): bool
    {
        return $user !== null && $user->role()->value === 'admin';
    }

    private function validateTenantAccess(mixed $user, string $routeCompanyId): ?ResponseInterface
    {
        $userCompanyId = $user->companyId();

        if ($userCompanyId === null) {
            return JsonResponse::error(
                'User is not assigned to any company',
                403
            );
        }

        if ($userCompanyId->toString() !== $routeCompanyId) {
            return JsonResponse::error(
                'Access denied: You can only access your own company data',
                403
            );
        }

        return null;
    }

    private function enrichRequest(ServerRequestInterface $request, mixed $user): ServerRequestInterface
    {
        if ($user !== null && $user->companyId() !== null) {
            return $request->withAttribute(
                'company_id',
                $user->companyId()->toString()
            );
        }
        return $request;
    }

    /**
     * Extract company ID from route path.
     * Matches: /api/v1/companies/{companyId}/...
     */
    private function extractCompanyId(string $path): ?string
    {
        if (preg_match('#/api/v1/companies/([a-f0-9-]{36})#', $path, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
