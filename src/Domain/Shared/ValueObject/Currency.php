<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject;

enum Currency: string
{
    case PHP = 'PHP';
    case USD = 'USD';
    case EUR = 'EUR';
}
