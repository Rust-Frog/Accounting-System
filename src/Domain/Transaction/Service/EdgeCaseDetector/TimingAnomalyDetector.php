<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects timing-related edge cases:
 * - Future-dated entries (Rule #1)
 * - Backdated entries beyond threshold (Rule #2)
 * - Period-end entries when flagging enabled (Rule #3)
 */
final class TimingAnomalyDetector
{
    public function detect(
        DateTimeImmutable $transactionDate,
        DateTimeImmutable $today,
        EdgeCaseThresholds $thresholds,
    ): EdgeCaseDetectionResult {
        $flags = [];

        // Rule #1: Future-dated
        if ($transactionDate > $today) {
            $flags[] = EdgeCaseFlag::futureDated(
                $transactionDate->format('Y-m-d'),
                $today->format('Y-m-d'),
            );
        }

        // Rule #2: Backdated beyond threshold
        if ($transactionDate < $today) {
            $daysBack = (int) $today->diff($transactionDate)->days;

            if ($daysBack > $thresholds->backdatedDaysThreshold()) {
                $flags[] = EdgeCaseFlag::backdated(
                    $transactionDate->format('Y-m-d'),
                    $daysBack,
                );
            }
        }

        // Rule #3: Period-end entries (optional)
        if ($thresholds->flagPeriodEndEntries()) {
            $periodType = $this->detectPeriodEnd($transactionDate);
            if ($periodType !== null) {
                $flags[] = EdgeCaseFlag::periodEnd(
                    $transactionDate->format('Y-m-d'),
                    $periodType,
                );
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }

    /**
     * Check if date is in last 3 days of month/quarter/year.
     */
    private function detectPeriodEnd(DateTimeImmutable $date): ?string
    {
        $day = (int) $date->format('j');
        $month = (int) $date->format('n');
        $lastDayOfMonth = (int) $date->format('t');

        $daysUntilMonthEnd = $lastDayOfMonth - $day;

        // Last 3 days of year
        if ($month === 12 && $daysUntilMonthEnd <= 2) {
            return 'year';
        }

        // Last 3 days of quarter (March, June, September, December)
        if (in_array($month, [3, 6, 9, 12], true) && $daysUntilMonthEnd <= 2) {
            return 'quarter';
        }

        // Last 3 days of any month
        if ($daysUntilMonthEnd <= 2) {
            return 'month';
        }

        return null;
    }
}
