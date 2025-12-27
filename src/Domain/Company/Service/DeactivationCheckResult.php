<?php

declare(strict_types=1);

namespace Domain\Company\Service;

use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;

/**
 * Result of deactivation check.
 */
final class DeactivationCheckResult
{
    /**
     * @param array<string> $blockers
     */
    private function __construct(
        private readonly bool $canDeactivate,
        private readonly array $blockers
    ) {
    }

    public static function success(): self
    {
        return new self(true, []);
    }

    /**
     * @param array<string> $blockers
     */
    public static function failure(array $blockers): self
    {
        return new self(false, $blockers);
    }

    public function canDeactivate(): bool
    {
        return $this->canDeactivate;
    }

    /**
     * @return array<string>
     */
    public function blockers(): array
    {
        return $this->blockers;
    }
}
