<?php

declare(strict_types=1);

namespace Domain\Approval\ValueObject;

use Domain\Shared\ValueObject\Money;

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
