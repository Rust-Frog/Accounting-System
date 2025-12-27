<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\HashChain;

use DateTimeImmutable;

/**
 * A link in the immutable hash chain.
 */
final class ChainLink
{
    public function __construct(
        private readonly ContentHash $previousHash,
        private readonly ContentHash $contentHash,
        private readonly DateTimeImmutable $timestamp
    ) {}

    public function previousHash(): ContentHash
    {
        return $this->previousHash;
    }

    public function contentHash(): ContentHash
    {
        return $this->contentHash;
    }

    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function verify(ContentHash $previousHash): bool
    {
        return $this->previousHash->equals($previousHash);
    }

    public function computeHash(): ContentHash
    {
        return ContentHash::fromContent(
            $this->previousHash->toString() .
            $this->contentHash->toString() .
            $this->timestamp->format('Y-m-d H:i:s.u')
        );
    }
}
