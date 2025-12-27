<?php

declare(strict_types=1);

namespace Domain\Company\Service;

use Domain\Company\ValueObject\CompanyId;

/**
 * Result of activation check.
 */
final class ActivationCheckResult
{
    /**
     * @param array<string> $missingRequirements
     */
    private function __construct(
        private readonly bool $canActivate,
        private readonly array $missingRequirements
    ) {
    }

    public static function success(): self
    {
        return new self(true, []);
    }

    /**
     * @param array<string> $missingRequirements
     */
    public static function failure(array $missingRequirements): self
    {
        return new self(false, $missingRequirements);
    }

    public function canActivate(): bool
    {
        return $this->canActivate;
    }

    /**
     * @return array<string>
     */
    public function missingRequirements(): array
    {
        return $this->missingRequirements;
    }
}
