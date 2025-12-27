<?php

declare(strict_types=1);

namespace Domain\Company\ValueObject;

use Domain\Shared\Exception\InvalidArgumentException;
use Domain\Shared\ValueObject\DateTime\DateValue;
use JsonSerializable;

/**
 * Fiscal year configuration.
 * Determines when a company's accounting year starts.
 */
final class FiscalYear implements JsonSerializable
{
    private function __construct(
        private readonly int $startMonth,
        private readonly int $startDay
    ) {
    }

    /**
     * Create a calendar year fiscal year (Jan 1).
     */
    public static function calendar(): self
    {
        return new self(1, 1);
    }

    /**
     * Create a fiscal year starting from a specific month and day.
     */
    public static function fromMonthAndDay(int $month, int $day): self
    {
        self::validateMonth($month);
        self::validateDay($month, $day);

        return new self($month, $day);
    }

    private static function validateMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                sprintf('Month must be between 1 and 12, got %d', $month)
            );
        }
    }

    private static function validateDay(int $month, int $day): void
    {
        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException(
                sprintf('Day must be between 1 and 31, got %d', $day)
            );
        }

        $maxDays = self::getMaxDaysInMonth($month);
        if ($day > $maxDays) {
            throw new InvalidArgumentException(
                sprintf('Day %d is invalid for month %d (max: %d)', $day, $month, $maxDays)
            );
        }
    }

    public function startMonth(): int
    {
        return $this->startMonth;
    }

    public function startDay(): int
    {
        return $this->startDay;
    }

    /**
     * Get the start date of the fiscal year for a given calendar year.
     */
    public function getStartDate(int $year): DateValue
    {
        return DateValue::fromString(
            sprintf('%04d-%02d-%02d', $year, $this->startMonth, $this->startDay)
        );
    }

    /**
     * Get the end date of the fiscal year for a given calendar year.
     */
    public function getEndDate(int $year): DateValue
    {
        $endYear = $this->startMonth > 1 ? $year + 1 : $year;
        $endMonth = $this->startMonth === 1 ? 12 : $this->startMonth - 1;

        // Get last day of the month before the next fiscal year starts
        $maxDay = self::getMaxDaysInMonth($endMonth, $endYear);

        return DateValue::fromString(
            sprintf('%04d-%02d-%02d', $endYear, $endMonth, $maxDay)
        );
    }

    /**
     * Check if a date falls within a specific fiscal year.
     */
    public function containsDate(DateValue $date, int $fiscalYear): bool
    {
        $start = $this->getStartDate($fiscalYear);
        $end = $this->getEndDate($fiscalYear);

        return !$date->isBefore($start) && !$date->isAfter($end);
    }

    public function equals(self $other): bool
    {
        return $this->startMonth === $other->startMonth
            && $this->startDay === $other->startDay;
    }

    /**
     * @return array<string, int>
     */
    public function jsonSerialize(): array
    {
        return [
            'start_month' => $this->startMonth,
            'start_day' => $this->startDay,
        ];
    }

    private static function getMaxDaysInMonth(int $month, ?int $year = null): int
    {
        // Use non-leap year if no year specified
        $year = $year ?? 2001;
        $timestamp = mktime(0, 0, 0, $month, 1, $year);

        // mktime should never fail with valid month/year, but handle it
        if ($timestamp === false) {
            return 31; // Safe fallback
        }

        return (int) date('t', $timestamp);
    }
}
