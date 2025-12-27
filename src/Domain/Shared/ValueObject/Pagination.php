<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Pagination
{
    public function __construct(
        public readonly int $limit,
        public readonly int $offset
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than 0');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be greater than or equal to 0');
        }
    }
}
