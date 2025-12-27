<?php

declare(strict_types=1);

namespace Domain\Approval\Service;

use Domain\Approval\ValueObject\ApprovalReason;
use Domain\Approval\ValueObject\ApprovalType;

/**
 * Value object representing whether approval is required.
 */
final readonly class ApprovalRequirement
{
    private function __construct(
        private bool $required,
        private ?ApprovalType $type,
        private ?ApprovalReason $reason
    ) {
    }

    public static function notRequired(): self
    {
        return new self(false, null, null);
    }

    public static function required(ApprovalType $type, ApprovalReason $reason): self
    {
        return new self(true, $type, $reason);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function type(): ?ApprovalType
    {
        return $this->type;
    }

    public function reason(): ?ApprovalReason
    {
        return $this->reason;
    }
}
