<?php

declare(strict_types=1);

namespace Application\Command\Approval;

use Application\Command\CommandInterface;

/**
 * Command to reject a pending request.
 */
final readonly class RejectRequestCommand implements CommandInterface
{
    public function __construct(
        public string $approvalId,
        public string $rejectedBy,
        public string $reason,
    ) {
    }
}
