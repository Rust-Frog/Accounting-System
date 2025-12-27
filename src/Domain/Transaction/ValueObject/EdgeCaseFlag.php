<?php

declare(strict_types=1);

namespace Domain\Transaction\ValueObject;

/**
 * Represents a detected edge case flag on a transaction.
 * Used for audit logging and approval routing decisions.
 */
final readonly class EdgeCaseFlag
{
    private function __construct(
        private string $type,
        private string $description,
        private bool $requiresApproval,
        private array $context,
    ) {
    }

    // === Factory Methods for Each Edge Case Type ===

    public static function futureDated(string $transactionDate, string $today): self
    {
        return new self(
            type: 'future_dated',
            description: "Transaction dated {$transactionDate} is in the future (today: {$today})",
            requiresApproval: true,
            context: ['transaction_date' => $transactionDate, 'today' => $today],
        );
    }

    public static function backdated(string $transactionDate, int $daysBack): self
    {
        return new self(
            type: 'backdated',
            description: "Transaction dated {$transactionDate} is {$daysBack} days in the past",
            requiresApproval: true,
            context: ['transaction_date' => $transactionDate, 'days_back' => $daysBack],
        );
    }

    public static function largeAmount(int $amountCents, int $thresholdCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);
        $thresholdFormatted = number_format($thresholdCents / 100, 2);

        return new self(
            type: 'large_amount',
            description: "Transaction amount \${$amountFormatted} exceeds threshold \${$thresholdFormatted}",
            requiresApproval: true,
            context: ['amount_cents' => $amountCents, 'threshold_cents' => $thresholdCents],
        );
    }

    public static function belowThreshold(int $amountCents, int $thresholdCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);
        $thresholdFormatted = number_format($thresholdCents / 100, 2);
        $percentage = round(($amountCents / $thresholdCents) * 100, 1);

        return new self(
            type: 'below_threshold',
            description: "Transaction amount \${$amountFormatted} is {$percentage}% of threshold \${$thresholdFormatted}",
            requiresApproval: false,
            context: ['amount_cents' => $amountCents, 'threshold_cents' => $thresholdCents, 'percentage' => $percentage],
        );
    }

    public static function contraRevenue(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'contra_revenue',
            description: "Debiting revenue account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function contraExpense(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'contra_expense',
            description: "Crediting expense account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function assetWritedown(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'asset_writedown',
            description: "Crediting asset account '{$accountName}' for \${$amountFormatted} (write-down/disposal)",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function liabilityReduction(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'liability_reduction',
            description: "Debiting liability account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: false,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function equityAdjustment(string $accountName, int $amountCents, string $lineType): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);
        $action = $lineType === 'debit' ? 'Debiting' : 'Crediting';

        return new self(
            type: 'equity_adjustment',
            description: "{$action} equity account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents, 'line_type' => $lineType],
        );
    }

    public static function negativeBalance(string $accountName, int $currentBalanceCents, int $projectedBalanceCents): self
    {
        $currentFormatted = number_format($currentBalanceCents / 100, 2);
        $projectedFormatted = number_format($projectedBalanceCents / 100, 2);

        return new self(
            type: 'negative_balance',
            description: "Account '{$accountName}' would go negative: \${$currentFormatted} -> \${$projectedFormatted}",
            requiresApproval: true,
            context: [
                'account_name' => $accountName,
                'current_balance_cents' => $currentBalanceCents,
                'projected_balance_cents' => $projectedBalanceCents,
            ],
        );
    }

    public static function roundNumber(int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'round_number',
            description: "Transaction amount \${$amountFormatted} is suspiciously round",
            requiresApproval: false,
            context: ['amount_cents' => $amountCents],
        );
    }

    public static function periodEnd(string $date, string $periodType): self
    {
        return new self(
            type: 'period_end',
            description: "Transaction on {$date} is near {$periodType}-end (window dressing risk)",
            requiresApproval: false,
            context: ['date' => $date, 'period_type' => $periodType],
        );
    }

    public static function dormantAccount(string $accountName, int $daysSinceLastActivity): self
    {
        return new self(
            type: 'dormant_account',
            description: "Account '{$accountName}' has had no activity for {$daysSinceLastActivity} days",
            requiresApproval: false,
            context: ['account_name' => $accountName, 'days_since_last_activity' => $daysSinceLastActivity],
        );
    }

    public static function missingDescription(): self
    {
        return new self(
            type: 'missing_description',
            description: 'Transaction has empty or minimal description',
            requiresApproval: false,
            context: [],
        );
    }

    // === Accessors ===

    public function type(): string
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function requiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function isReviewOnly(): bool
    {
        return !$this->requiresApproval;
    }

    public function context(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
            'requires_approval' => $this->requiresApproval,
            'context' => $this->context,
        ];
    }
}
