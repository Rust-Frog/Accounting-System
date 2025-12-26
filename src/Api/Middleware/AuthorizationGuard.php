<?php

declare(strict_types=1);

namespace Api\Middleware;

use Domain\Authorization\OwnershipVerifierInterface;
use Domain\Company\ValueObject\CompanyId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authorization guard for protecting resources.
 * Verifies ownership before allowing access to resources.
 */
final class AuthorizationGuard
{
    public function __construct(
        private readonly ?OwnershipVerifierInterface $ownershipVerifier = null,
        private readonly ?\Domain\Audit\Service\SystemActivityService $activityService = null,
    ) {
    }

    /**
     * Verify that a resource belongs to the request's company.
     * Logs authorization failures for security auditing.
     * 
     * @param ServerRequestInterface $request The current request
     * @param string $resourceType Type of resource (transaction, account, etc.)
     * @param string $resourceId The resource's ID
     * @return bool True if authorized, false otherwise
     */
    public function verifyResourceOwnership(
        ServerRequestInterface $request,
        string $resourceType,
        string $resourceId
    ): bool {
        if ($this->ownershipVerifier === null) {
            // No verifier configured, allow access (backwards compatibility)
            return true;
        }

        $companyId = $request->getAttribute('companyId');
        $user = $request->getAttribute('user');

        // Admin bypass
        if ($user !== null && $user->role()->value === 'admin') {
            return true;
        }
        
        if ($companyId === null) {
            $this->logAuthorizationFailure($request, $resourceType, $resourceId, 'No company context');
            return false;
        }

        $result = $this->ownershipVerifier->verify(
            $resourceType,
            $resourceId,
            CompanyId::fromString($companyId)
        );

        if ($result->isNotOwner()) {
            $this->logAuthorizationFailure(
                $request,
                $resourceType,
                $resourceId,
                $result->reason() ?? 'Ownership verification failed',
                $result->actualOwnerId()
            );
            return false;
        }

        return true;
    }

    /**
     * Verify that current user belongs to the company.
     */
    public function verifyUserCompanyAccess(ServerRequestInterface $request): bool
    {
        if ($this->ownershipVerifier === null) {
            return true;
        }

        $companyId = $request->getAttribute('companyId');
        $user = $request->getAttribute('user');
        
        if ($companyId === null || $user === null) {
            return false;
        }

        // Admin bypass
        if ($user->role()->value === 'admin') {
            return true;
        }

        $result = $this->ownershipVerifier->verifyUserCompany(
            \Domain\Identity\ValueObject\UserId::fromString($userId),
            CompanyId::fromString($companyId)
        );

        if ($result->isNotOwner()) {
            $this->logAuthorizationFailure(
                $request,
                'company_access',
                $companyId,
                $result->reason() ?? 'User company access denied'
            );
            return false;
        }

        return true;
    }

    /**
     * Log authorization failures for security auditing.
     */
    private function logAuthorizationFailure(
        ServerRequestInterface $request,
        string $resourceType,
        string $resourceId,
        string $reason,
        ?string $actualOwnerId = null
    ): void {
        if ($this->activityService === null) {
            return;
        }

        $userId = $request->getAttribute('user_id');
        $companyId = $request->getAttribute('companyId');
        $username = $request->getAttribute('username');
        $serverParams = $request->getServerParams();

        $this->activityService->log(
            activityType: 'security.authorization_denied',
            entityType: $resourceType,
            entityId: $resourceId,
            description: "Authorization denied: {$reason}",
            actorUserId: $userId ? \Domain\Identity\ValueObject\UserId::fromString($userId) : null,
            actorUsername: $username,
            actorIpAddress: $serverParams['REMOTE_ADDR'] ?? null,
            severity: 'warning',
            metadata: [
                'reason' => $reason,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'requested_company_id' => $companyId,
                'actual_owner_id' => $actualOwnerId,
                'request_method' => $request->getMethod(),
                'request_uri' => (string) $request->getUri()->getPath(),
            ]
        );
    }
}
