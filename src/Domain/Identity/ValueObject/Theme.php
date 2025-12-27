<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

/**
 * Theme enum for UI appearance.
 */
enum Theme: string
{
    case LIGHT = 'light';
    case DARK = 'dark';
    case SYSTEM = 'system';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'light' => self::LIGHT,
            'dark' => self::DARK,
            'system' => self::SYSTEM,
            default => self::LIGHT,
        };
    }
}
