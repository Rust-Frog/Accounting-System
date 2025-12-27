<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects period-end transactions (window dressing risk):
 * - Last 3 days of month (Rule #15)
 * - Last 3 days of quarter (Rule #16)
 * - Last 3 days of year (most significant)
 */
final class PeriodEndDetector
{
    private const DAYS_BEFORE_END = 3;

    public function detect(
        DateTimeImmutable $transactionDate,
        EdgeCaseThresholds $thresholds,
    ): EdgeCaseDetectionResult {
        if (!$thresholds->flagPeriodEndEntries()) {
            return EdgeCaseDetectionResult::clean();
        }

        $periodType = $this->detectPeriodEnd($transactionDate);

        if ($periodType === null) {
            return EdgeCaseDetectionResult::clean();
        }

        return EdgeCaseDetectionResult::withFlags([
            EdgeCaseFlag::periodEnd($transactionDate->format('Y-m-d'), $periodType),
        ]);
    }

    /**
     * Check if date is in last 3 days of month/quarter/year.
     * Returns the most significant period type (year > quarter > month).
     */
    private function detectPeriodEnd(DateTimeImmutable $date): ?string
    {
        $day = (int) $date->format('j');
        $month = (int) $date->format('n');
        $daysInMonth = (int) $date->format('t');

        $daysUntilMonthEnd = $daysInMonth - $day;

        // Not near any period end
        if ($daysUntilMonthEnd >= self::DAYS_BEFORE_END) {
            return null;
        }

        // Check year-end (December) - highest priority
        if ($month === 12) {
            return 'year';
        }

        // Check quarter-end (March, June, September)
        if (in_array($month, [3, 6, 9], true)) {
            return 'quarter';
        }

        // Regular month-end
        return 'month';
    }
}
