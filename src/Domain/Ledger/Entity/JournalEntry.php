<?php

declare(strict_types=1);

namespace Domain\Ledger\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\HashChain\ChainLink;
use Domain\Shared\ValueObject\HashChain\ContentHash;
use Domain\Transaction\ValueObject\TransactionId;
use Domain\Transaction\ValueObject\TransactionStatus;
use InvalidArgumentException;

/**
 * Immutable Ledger Entry.
 *
 * Provides a strictly append-only, cryptographically linked record of financial impact.
 * Separates the mutable "document" (Transaction) from the immutable "record" (JournalEntry).
 */
final class JournalEntry
{
    private function __construct(
        public readonly string $id,
        public readonly CompanyId $companyId,
        public readonly TransactionId $transactionId,
        public readonly string $entryType, // 'POSTING' or 'REVERSAL'
        public readonly array $bookings, // Serialized debit/credit snapshots
        public readonly DateTimeImmutable $occurredAt,
        public readonly ContentHash $contentHash,
        public readonly ?ContentHash $previousHash,
        public readonly ?ChainLink $chainLink
    ) {
    }

    /**
     * Reconstitute a JournalEntry from persisted data.
     * Used for hydration from database without recomputing hashes.
     */
    public static function reconstitute(
        string $id,
        CompanyId $companyId,
        TransactionId $transactionId,
        string $entryType,
        array $bookings,
        DateTimeImmutable $occurredAt,
        ContentHash $contentHash,
        ?ContentHash $previousHash,
        ?ChainLink $chainLink
    ): self {
        return new self(
            $id,
            $companyId,
            $transactionId,
            $entryType,
            $bookings,
            $occurredAt,
            $contentHash,
            $previousHash,
            $chainLink
        );
    }

    public static function create(
        CompanyId $companyId,
        TransactionId $transactionId,
        string $entryType,
        array $bookings,
        DateTimeImmutable $occurredAt,
        ?ContentHash $previousHash = null
    ): self {
        $id = \Domain\Shared\ValueObject\Uuid::generate()->toString();

        if (!in_array($entryType, ['POSTING', 'REVERSAL'], true)) {
            throw new InvalidArgumentException("Invalid entry type: {$entryType}");
        }

        // 1. Compute Content Hash (from immutable business data)
        // We do NOT include previousHash here to separate content integrity from chain integrity
        $contentHash = ContentHash::fromArray([
            'id' => $id,
            'company_id' => $companyId->toString(),
            'transaction_id' => $transactionId->toString(),
            'entry_type' => $entryType,
            'bookings' => $bookings, // Must be canonicalized
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s.u'),
        ]);

        // 2. Create Chain Link (if previous hash exists)
        $chainLink = null;
        if ($previousHash !== null) {
            $chainLink = new ChainLink($previousHash, $contentHash, $occurredAt);
        }

        return new self(
            $id,
            $companyId,
            $transactionId,
            $entryType,
            $bookings,
            $occurredAt,
            $contentHash,
            $previousHash,
            $chainLink
        );
    }

    public function getChainHash(): ?ContentHash
    {
        return $this->chainLink?->computeHash();
    }

    public function verifyChain(ContentHash $expectedPreviousHash): bool
    {
        if ($this->previousHash === null) {
            // Genesis block or detached entry - strictly speaking, can't verify connection if null
            // But if we expected null (genesis), it's valid.
            // If we expected a hash, and got null, it's invalid.
            // However, ContentHash cannot be null in expectation usually.
            // For simplicity:
            return false;
        }

        return $this->previousHash->equals($expectedPreviousHash);
    }
}
