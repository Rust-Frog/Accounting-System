<?php

declare(strict_types=1);

namespace Domain\Ledger\ValueObject;

use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;

/**
 * Immutable summary of account balances by type.
 * Used for financial reporting and equation validation.
 */
final readonly class BalanceSummary
{
    public function __construct(
        private int $totalAssetsCents,
        private int $totalLiabilitiesCents,
        private int $totalEquityCents,
        private int $totalRevenueCents,
        private int $totalExpensesCents,
        private Currency $currency
    ) {
    }

    public static function empty(Currency $currency): self
    {
        return new self(0, 0, 0, 0, 0, $currency);
    }

    public function totalAssets(): Money
    {
        return Money::fromCents($this->totalAssetsCents, $this->currency);
    }

    public function totalLiabilities(): Money
    {
        return Money::fromCents($this->totalLiabilitiesCents, $this->currency);
    }

    public function totalEquity(): Money
    {
        return Money::fromCents($this->totalEquityCents, $this->currency);
    }

    public function totalRevenue(): Money
    {
        return Money::fromCents($this->totalRevenueCents, $this->currency);
    }

    public function totalExpenses(): Money
    {
        return Money::fromCents($this->totalExpensesCents, $this->currency);
    }

    /**
     * Net income = Revenue - Expenses
     */
    public function netIncomeCents(): int
    {
        return $this->totalRevenueCents - $this->totalExpensesCents;
    }

    public function netIncome(): Money
    {
        // Net income can be negative
        $cents = $this->netIncomeCents();
        if ($cents < 0) {
            return Money::fromCents(0, $this->currency); // Return 0, track loss separately
        }
        return Money::fromCents($cents, $this->currency);
    }

    /**
     * BR-LP-005: Validate accounting equation.
     * Assets = Liabilities + Equity + (Revenue - Expenses)
     */
    public function isBalanced(): bool
    {
        $leftSide = $this->totalAssetsCents;
        $rightSide = $this->totalLiabilitiesCents
            + $this->totalEquityCents
            + $this->netIncomeCents();

        // Allow 1 cent tolerance due to rounding
        return abs($leftSide - $rightSide) <= 1;
    }

    /**
     * Get the imbalance amount (for debugging).
     */
    public function imbalanceCents(): int
    {
        $leftSide = $this->totalAssetsCents;
        $rightSide = $this->totalLiabilitiesCents
            + $this->totalEquityCents
            + $this->netIncomeCents();

        return $leftSide - $rightSide;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_assets_cents' => $this->totalAssetsCents,
            'total_liabilities_cents' => $this->totalLiabilitiesCents,
            'total_equity_cents' => $this->totalEquityCents,
            'total_revenue_cents' => $this->totalRevenueCents,
            'total_expenses_cents' => $this->totalExpensesCents,
            'net_income_cents' => $this->netIncomeCents(),
            'is_balanced' => $this->isBalanced(),
            'imbalance_cents' => $this->imbalanceCents(),
        ];
    }
}
