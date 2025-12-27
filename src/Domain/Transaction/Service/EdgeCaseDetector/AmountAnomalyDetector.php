<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects amount-related edge cases:
 * - Large transaction exceeding threshold (Rule #4)
 * - Round number amounts (Rule #5)
 * - Just below approval threshold (Rule #6)
 */
final class AmountAnomalyDetector
{
    /**
     * @param array<array{debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(array $lines, EdgeCaseThresholds $thresholds): EdgeCaseDetectionResult
    {
        $flags = [];

        $totalDebitsCents = 0;
        foreach ($lines as $line) {
            $totalDebitsCents += $line['debit_cents'] ?? 0;
        }

        $threshold = $thresholds->largeTransactionThresholdCents();

        // Rule #4: Large transaction
        if ($threshold > 0 && $totalDebitsCents > $threshold) {
            $flags[] = EdgeCaseFlag::largeAmount($totalDebitsCents, $threshold);
        }

        // Rule #6: Just below threshold (90-99%)
        if ($threshold > 0 && $totalDebitsCents < $threshold) {
            $floor = $thresholds->belowThresholdFloorCents();
            if ($totalDebitsCents >= $floor) {
                $flags[] = EdgeCaseFlag::belowThreshold($totalDebitsCents, $threshold);
            }
        }

        // Rule #5: Round number (optional)
        if ($thresholds->flagRoundNumbers() && $this->isRoundNumber($totalDebitsCents)) {
            $flags[] = EdgeCaseFlag::roundNumber($totalDebitsCents);
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }

    /**
     * Check if amount is suspiciously round (divisible by $1000 and > $5000).
     */
    private function isRoundNumber(int $amountCents): bool
    {
        // Must be at least $5,000
        if ($amountCents < 500_000) {
            return false;
        }

        // Divisible by $1,000 (100,000 cents)
        return $amountCents % 100_000 === 0;
    }
}
