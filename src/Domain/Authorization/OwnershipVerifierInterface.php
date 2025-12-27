<?php

declare(strict_types=1);

namespace Domain\Authorization;

use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;

/**
 * Verifies resource ownership for authorization.
 * Ensures users can only access resources belonging to their company.
 */
interface OwnershipVerifierInterface
{
    /**
     * Verify that a resource belongs to the specified company.
     * 
     * @param string $resourceType Type of resource (transaction, account, etc.)
     * @param string $resourceId The resource's ID
     * @param CompanyId $expectedCompanyId The company that should own the resource
     * @return OwnershipResult Result containing ownership status and details
     */
    public function verify(
        string $resourceType,
        string $resourceId,
        CompanyId $expectedCompanyId
    ): OwnershipResult;

    /**
     * Verify that a user belongs to the specified company.
     */
    public function verifyUserCompany(
        UserId $userId,
        CompanyId $expectedCompanyId
    ): OwnershipResult;
}
