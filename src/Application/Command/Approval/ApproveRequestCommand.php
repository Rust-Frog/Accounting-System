<?php

declare(strict_types=1);

namespace Application\Command\Approval;

use Application\Command\CommandInterface;

/**
 * Command to approve a pending request.
 */
final readonly class ApproveRequestCommand implements CommandInterface
{
    public function __construct(
        public string $approvalId,
        public string $approverId,
        public ?string $comment = null,
    ) {
    }
}
