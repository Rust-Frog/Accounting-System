<?php

declare(strict_types=1);

namespace Domain\Ledger\Service;

use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use Domain\Transaction\ValueObject\LineType;

/**
 * Domain service for calculating balance changes.
 * Implements BR-LP-001.
 */
final class BalanceCalculationService
{
    /**
     * Calculate the balance change for a transaction line.
     *
     * BR-LP-001: Balance change calculation.
     * - Same side as normal balance = increase (+)
     * - Opposite side = decrease (-)
     *
     * @param NormalBalance $normalBalance The account's normal balance (DEBIT or CREDIT)
     * @param LineType $lineType The transaction line type (DEBIT or CREDIT)
     * @param int $amountCents The amount in cents
     * @return int The change in cents (positive = increase, negative = decrease)
     */
    public function calculateChange(
        NormalBalance $normalBalance,
        LineType $lineType,
        int $amountCents
    ): int {
        $normalIsDebit = $normalBalance === NormalBalance::DEBIT;
        $lineIsDebit = $lineType === LineType::DEBIT;

        // Same side = increase, opposite = decrease
        if ($normalIsDebit === $lineIsDebit) {
            return $amountCents;
        }

        return -$amountCents;
    }

    /**
     * Project the new balance after applying a change.
     */
    public function projectBalance(int $currentBalanceCents, int $changeCents): int
    {
        return $currentBalanceCents + $changeCents;
    }
}
