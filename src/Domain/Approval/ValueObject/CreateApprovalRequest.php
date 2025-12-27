<?php

declare(strict_types=1);

namespace Domain\Approval\ValueObject;

use Domain\Approval\ValueObject\ApprovalReason;
use Domain\Approval\ValueObject\ApprovalType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;

final class CreateApprovalRequest
{
    public function __construct(
        public readonly CompanyId $companyId,
        public readonly ApprovalType $approvalType,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly ApprovalReason $reason,
        public readonly UserId $requestedBy,
        public readonly int $amountCents,
        public readonly int $priority
    ) {
    }
}
