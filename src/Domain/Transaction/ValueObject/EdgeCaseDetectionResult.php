<?php

declare(strict_types=1);

namespace Domain\Transaction\ValueObject;

/**
 * Aggregates all edge case flags detected for a transaction.
 * Determines if transaction requires approval routing.
 */
final readonly class EdgeCaseDetectionResult
{
    /**
     * @param EdgeCaseFlag[] $flags
     */
    private function __construct(
        private array $flags,
    ) {
    }

    public static function clean(): self
    {
        return new self([]);
    }

    /**
     * @param EdgeCaseFlag[] $flags
     */
    public static function withFlags(array $flags): self
    {
        return new self($flags);
    }

    public function isClean(): bool
    {
        return empty($this->flags);
    }

    public function hasFlags(): bool
    {
        return !empty($this->flags);
    }

    public function requiresApproval(): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag->requiresApproval()) {
                return true;
            }
        }

        return false;
    }

    public function hasReviewOnlyFlags(): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag->isReviewOnly()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return EdgeCaseFlag[]
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * @return EdgeCaseFlag[]
     */
    public function approvalRequiredFlags(): array
    {
        return array_values(array_filter($this->flags, fn(EdgeCaseFlag $flag) => $flag->requiresApproval()));
    }

    /**
     * @return EdgeCaseFlag[]
     */
    public function reviewOnlyFlags(): array
    {
        return array_values(array_filter($this->flags, fn(EdgeCaseFlag $flag) => $flag->isReviewOnly()));
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->flags, $other->flags));
    }

    /**
     * Maps edge case flags to approval types for routing.
     * Priority: negative_balance > high_value > backdated > contra_entry > transaction
     */
    public function suggestedApprovalType(): ?string
    {
        if (!$this->requiresApproval()) {
            return null;
        }

        $flagTypes = array_map(fn(EdgeCaseFlag $f) => $f->type(), $this->approvalRequiredFlags());

        // Priority order for approval type selection
        if (in_array('negative_balance', $flagTypes, true)) {
            return 'negative_equity';
        }

        if (in_array('large_amount', $flagTypes, true)) {
            return 'high_value';
        }

        if (in_array('backdated', $flagTypes, true)) {
            return 'backdated_transaction';
        }

        if (in_array('equity_adjustment', $flagTypes, true)) {
            return 'transaction'; // Uses general transaction approval
        }

        if (in_array('contra_revenue', $flagTypes, true) || in_array('contra_expense', $flagTypes, true)) {
            return 'transaction';
        }

        if (in_array('asset_writedown', $flagTypes, true)) {
            return 'transaction';
        }

        if (in_array('future_dated', $flagTypes, true)) {
            return 'transaction';
        }

        return 'transaction';
    }

    public function toArray(): array
    {
        return [
            'has_flags' => $this->hasFlags(),
            'requires_approval' => $this->requiresApproval(),
            'flags' => array_map(fn(EdgeCaseFlag $f) => $f->toArray(), $this->flags),
            'suggested_approval_type' => $this->suggestedApprovalType(),
        ];
    }
}
