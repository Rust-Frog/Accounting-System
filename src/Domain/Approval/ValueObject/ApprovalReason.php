<?php

declare(strict_types=1);

namespace Domain\Approval\ValueObject;

use Domain\Shared\ValueObject\Money;
use Domain\Transaction\ValueObject\EdgeCaseFlag;

/**
 * Value object representing the reason an approval is required.
 */
final readonly class ApprovalReason
{
    /**
     * @param array<string, mixed> $details
     */
    private function __construct(
        private ApprovalType $type,
        private string $description,
        private array $details
    ) {
    }

    public static function negativeEquity(string $accountName, int $projectedBalanceCents): self
    {
        return new self(
            ApprovalType::NEGATIVE_EQUITY,
            sprintf('Transaction would result in negative %s balance', $accountName),
            [
                'account_name' => $accountName,
                'projected_balance_cents' => $projectedBalanceCents,
            ]
        );
    }

    public static function highValue(int $amountCents, int $thresholdCents): self
    {
        return new self(
            ApprovalType::HIGH_VALUE,
            sprintf(
                'Transaction amount (%d cents) exceeds approval threshold (%d cents)',
                $amountCents,
                $thresholdCents
            ),
            [
                'amount_cents' => $amountCents,
                'threshold_cents' => $thresholdCents,
            ]
        );
    }

    public static function backdated(\DateTimeImmutable $transactionDate, int $daysBack): self
    {
        return new self(
            ApprovalType::BACKDATED_TRANSACTION,
            sprintf('Transaction backdated %d days to %s', $daysBack, $transactionDate->format('Y-m-d')),
            [
                'transaction_date' => $transactionDate->format('Y-m-d'),
                'days_back' => $daysBack,
            ]
        );
    }

    public static function voidTransaction(string $transactionNumber): self
    {
        return self::createTransactionRequest(
            ApprovalType::VOID_TRANSACTION,
            'void',
            $transactionNumber,
            'transaction_number'
        );
    }

    public static function transactionPosting(string $transactionId): self
    {
        return self::createTransactionRequest(
            ApprovalType::TRANSACTION_POSTING,
            'post',
            $transactionId,
            'transaction_id'
        );
    }

    public static function periodClose(string $startDate, string $endDate, int $netIncomeCents): self
    {
        return new self(
            ApprovalType::PERIOD_CLOSE,
            sprintf('Request to close period %s to %s', $startDate, $endDate),
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'net_income_cents' => $netIncomeCents,
            ]
        );
    }

    private static function createTransactionRequest(
        ApprovalType $type,
        string $actionVerb,
        string $id,
        string $idKey
    ): self {
        return new self(
            $type,
            sprintf('Request to %s transaction %s', $actionVerb, $id),
            [$idKey => $id]
        );
    }

    /**
     * Create an ApprovalReason from edge case detection flags.
     *
     * @param array<EdgeCaseFlag> $flags
     */
    public static function fromEdgeCaseFlags(array $flags): self
    {
        $descriptions = array_map(fn(EdgeCaseFlag $f) => $f->description(), $flags);
        $types = array_map(fn(EdgeCaseFlag $f) => $f->type(), $flags);

        // Determine the most appropriate approval type based on flags
        $approvalType = self::determineApprovalTypeFromFlags($types);

        return new self(
            $approvalType,
            sprintf('Edge case flags: %s', implode('; ', $descriptions)),
            [
                'flag_types' => $types,
                'flag_count' => count($flags),
                'flags' => array_map(fn(EdgeCaseFlag $f) => $f->toArray(), $flags),
            ],
        );
    }

    /**
     * @param array<string> $flagTypes
     */
    private static function determineApprovalTypeFromFlags(array $flagTypes): ApprovalType
    {
        // Priority order for determining approval type
        if (in_array('negative_balance', $flagTypes, true)) {
            return ApprovalType::NEGATIVE_EQUITY;
        }
        if (in_array('asset_writedown', $flagTypes, true)) {
            return ApprovalType::ASSET_WRITEDOWN;
        }
        if (in_array('large_amount', $flagTypes, true)) {
            return ApprovalType::HIGH_VALUE;
        }
        if (in_array('future_dated', $flagTypes, true)) {
            return ApprovalType::FUTURE_DATED;
        }
        if (in_array('backdated', $flagTypes, true)) {
            return ApprovalType::BACKDATED_TRANSACTION;
        }
        if (in_array('contra_revenue', $flagTypes, true) ||
            in_array('contra_expense', $flagTypes, true)) {
            return ApprovalType::CONTRA_ENTRY;
        }

        // Default for any other edge case
        return ApprovalType::EDGE_CASE;
    }

    public function type(): ApprovalType
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'description' => $this->description,
            'details' => $this->details,
        ];
    }
}
