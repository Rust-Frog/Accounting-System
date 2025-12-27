<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\HashChain;

use JsonSerializable;

/**
 * Represents a Merkle proof - the path from a leaf to the root.
 * Used to efficiently verify inclusion of a single item in a large set.
 */
final class MerkleProof implements JsonSerializable
{
    /**
     * @param array<array{hash: ContentHash, position: string}> $path
     */
    public function __construct(
        private readonly int $leafIndex,
        private readonly array $path
    ) {
    }

    public function leafIndex(): int
    {
        return $this->leafIndex;
    }

    /**
     * @return array<array{hash: ContentHash, position: string}>
     */
    public function path(): array
    {
        return $this->path;
    }

    /**
     * Verify that a leaf is included in the tree with the given root.
     */
    public function verify(ContentHash $leaf, ContentHash $expectedRoot): bool
    {
        $currentHash = $leaf;

        foreach ($this->path as $step) {
            if ($step['position'] === 'left') {
                $currentHash = ContentHash::fromContent(
                    $step['hash']->toString() . $currentHash->toString()
                );
            } else {
                $currentHash = ContentHash::fromContent(
                    $currentHash->toString() . $step['hash']->toString()
                );
            }
        }

        return $currentHash->equals($expectedRoot);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'leaf_index' => $this->leafIndex,
            'path' => array_map(fn($step) => [
                'hash' => $step['hash']->toString(),
                'position' => $step['position'],
            ], $this->path),
        ];
    }
}
