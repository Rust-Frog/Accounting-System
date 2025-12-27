<?php

declare(strict_types=1);

namespace Domain\Reporting\ValueObject;

use DateTimeImmutable;
use Domain\Shared\Exception\InvalidArgumentException;

/**
 * Value object representing a report period.
 * Immutable, validated on construction.
 */
final readonly class ReportPeriod
{
    private function __construct(
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private PeriodType $type
    ) {
        if ($startDate > $endDate) {
            throw new InvalidArgumentException('Start date must be before or equal to end date');
        }
    }

    public static function month(int $year, int $month): self
    {
        $start = new DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');

        return new self($start, $end, PeriodType::MONTHLY);
    }

    public static function quarter(int $year, int $quarter): self
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('Quarter must be between 1 and 4');
        }

        $startMonth = (($quarter - 1) * 3) + 1;
        $start = new DateTimeImmutable(sprintf('%d-%02d-01', $year, $startMonth));
        $end = $start->modify('+2 months')->modify('last day of this month');

        return new self($start, $end, PeriodType::QUARTERLY);
    }

    public static function year(int $year): self
    {
        $start = new DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new DateTimeImmutable(sprintf('%d-12-31', $year));

        return new self($start, $end, PeriodType::YEARLY);
    }

    public static function custom(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self($start, $end, PeriodType::CUSTOM);
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function type(): PeriodType
    {
        return $this->type;
    }

    /**
     * Check if a date falls within this period.
     */
    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    /**
     * Check if this period overlaps with another.
     */
    public function overlaps(self $other): bool
    {
        return $this->startDate <= $other->endDate && $this->endDate >= $other->startDate;
    }

    /**
     * Get the number of days in this period.
     */
    public function getDays(): int
    {
        $interval = $this->startDate->diff($this->endDate);

        return $interval->days + 1; // +1 to include both start and end
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'type' => $this->type->value,
            'days' => (string) $this->getDays(),
        ];
    }
}
