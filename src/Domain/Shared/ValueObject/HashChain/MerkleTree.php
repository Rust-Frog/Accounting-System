<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\HashChain;

use Domain\Shared\Exception\InvalidArgumentException;
use JsonSerializable;

/**
 * Merkle Tree implementation for efficient verification of large datasets.
 * Used for cross-tenant integrity verification without exposing data.
 */
final class MerkleTree implements JsonSerializable
{
    /**
     * @param array<ContentHash> $leaves
     * @param array<array<ContentHash>> $levels All tree levels from leaves to root
     */
    private function __construct(
        private readonly array $leaves,
        private readonly array $levels
    ) {
    }

    /**
     * Build a Merkle tree from an array of leaf hashes.
     *
     * @param array<ContentHash> $leaves
     */
    public static function fromLeaves(array $leaves): self
    {
        if (empty($leaves)) {
            throw new InvalidArgumentException('Cannot create Merkle tree from empty leaves');
        }

        $levels = [$leaves];
        $currentLevel = $leaves;

        while (count($currentLevel) > 1) {
            $currentLevel = self::buildNextLevel($currentLevel);
            $levels[] = $currentLevel;
        }

        return new self($leaves, $levels);
    }

    /**
     * Build the next level of the tree by pairing and hashing nodes.
     *
     * @param array<ContentHash> $currentLevel
     * @return array<ContentHash>
     */
    private static function buildNextLevel(array $currentLevel): array
    {
        $nextLevel = [];
        $count = count($currentLevel);

        for ($i = 0; $i < $count; $i += 2) {
            $left = $currentLevel[$i];
            $right = $currentLevel[$i + 1] ?? $currentLevel[$i]; // Duplicate if odd

            $nextLevel[] = ContentHash::fromContent(
                $left->toString() . $right->toString()
            );
        }

        return $nextLevel;
    }

    /**
     * Get the Merkle root hash.
     */
    public function root(): ContentHash
    {
        $topLevel = $this->levels[count($this->levels) - 1];
        return $topLevel[0];
    }

    /**
     * Get number of leaves in the tree.
     */
    public function leafCount(): int
    {
        return count($this->leaves);
    }

    /**
     * Generate a proof of inclusion for a specific leaf.
     */
    public function generateProof(int $leafIndex): MerkleProof
    {
        $this->validateLeafIndex($leafIndex);

        $path = [];
        $index = $leafIndex;

        for ($level = 0; $level < count($this->levels) - 1; $level++) {
            $step = $this->computeProofStep($this->levels[$level], $index);
            $path[] = $step;
            $index = intdiv($index, 2);
        }

        return new MerkleProof($leafIndex, $path);
    }

    /**
     * Validate that the leaf index is within bounds.
     */
    private function validateLeafIndex(int $leafIndex): void
    {
        if ($leafIndex < 0 || $leafIndex >= count($this->leaves)) {
            throw new InvalidArgumentException('Leaf index out of bounds');
        }
    }

    /**
     * Compute a single step in the proof path.
     *
     * @param array<ContentHash> $levelNodes
     * @return array{hash: ContentHash, position: string}
     */
    private function computeProofStep(array $levelNodes, int $index): array
    {
        $isRightNode = ($index % 2) === 1;
        $siblingIndex = $isRightNode ? $index - 1 : min($index + 1, count($levelNodes) - 1);
        $position = $isRightNode ? 'left' : 'right';

        return [
            'hash' => $levelNodes[$siblingIndex],
            'position' => $position,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'root' => $this->root()->toString(),
            'leaf_count' => $this->leafCount(),
        ];
    }
}
