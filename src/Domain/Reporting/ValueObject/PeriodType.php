<?php

declare(strict_types=1);

namespace Domain\Reporting\ValueObject;

enum PeriodType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';
    case CUSTOM = 'custom';
}
