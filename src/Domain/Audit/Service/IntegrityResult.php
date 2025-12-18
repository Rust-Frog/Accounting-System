<?php

declare(strict_types=1);

namespace Domain\Audit\Service;

/**
 * Result of audit chain integrity verification.
 */
final class IntegrityResult
{
    private function __construct(
        private readonly bool $valid,
        private readonly ?string $brokenAtEntryId,
        private readonly ?string $expectedHash,
        private readonly ?string $actualHash,
        private readonly int $entriesVerified
    ) {
    }

    /**
     * Create a successful verification result.
     */
    public static function verified(int $entriesVerified): self
    {
        return new self(
            valid: true,
            brokenAtEntryId: null,
            expectedHash: null,
            actualHash: null,
            entriesVerified: $entriesVerified
        );
    }

    /**
     * Create a failed verification result indicating where the chain broke.
     */
    public static function broken(
        string $brokenAtEntryId,
        string $expectedHash,
        string $actualHash,
        int $entriesVerified
    ): self {
        return new self(
            valid: false,
            brokenAtEntryId: $brokenAtEntryId,
            expectedHash: $expectedHash,
            actualHash: $actualHash,
            entriesVerified: $entriesVerified
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function brokenAtEntryId(): ?string
    {
        return $this->brokenAtEntryId;
    }

    public function expectedHash(): ?string
    {
        return $this->expectedHash;
    }

    public function actualHash(): ?string
    {
        return $this->actualHash;
    }

    public function entriesVerified(): int
    {
        return $this->entriesVerified;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'entries_verified' => $this->entriesVerified,
            'broken_at_entry_id' => $this->brokenAtEntryId,
            'expected_hash' => $this->expectedHash,
            'actual_hash' => $this->actualHash,
        ];
    }
}
