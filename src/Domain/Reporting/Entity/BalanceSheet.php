<?php

declare(strict_types=1);

namespace Domain\Reporting\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Balance Sheet report entity.
 * Shows assets, liabilities, and equity at a point in time.
 * BR-FR-001: Must always balance (Assets = Liabilities + Equity)
 */
final readonly class BalanceSheet
{
    /**
     * @param array<array<string, mixed>> $currentAssets
     * @param array<array<string, mixed>> $fixedAssets
     * @param array<array<string, mixed>> $currentLiabilities
     * @param array<array<string, mixed>> $longTermLiabilities
     * @param array<array<string, mixed>> $equityAccounts
     */
    public function __construct(
        private ReportId $id,
        private CompanyId $companyId,
        private ReportPeriod $period,
        private DateTimeImmutable $generatedAt,
        private array $currentAssets,
        private int $totalCurrentAssetsCents,
        private array $fixedAssets,
        private int $totalFixedAssetsCents,
        private array $currentLiabilities,
        private int $totalCurrentLiabilitiesCents,
        private array $longTermLiabilities,
        private int $totalLongTermLiabilitiesCents,
        private array $equityAccounts,
        private int $totalEquityCents,
        private int $retainedEarningsCents
    ) {
    }

    public function id(): ReportId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function period(): ReportPeriod
    {
        return $this->period;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function totalAssetsCents(): int
    {
        return $this->totalCurrentAssetsCents + $this->totalFixedAssetsCents;
    }

    public function totalLiabilitiesCents(): int
    {
        return $this->totalCurrentLiabilitiesCents + $this->totalLongTermLiabilitiesCents;
    }

    public function totalEquityCents(): int
    {
        return $this->totalEquityCents + $this->retainedEarningsCents;
    }

    /**
     * BR-FR-001: Assets = Liabilities + Equity
     */
    public function isBalanced(): bool
    {
        $assets = $this->totalAssetsCents();
        $liabilitiesPlusEquity = $this->totalLiabilitiesCents() + $this->totalEquityCents();

        // Allow 1 cent tolerance
        return abs($assets - $liabilitiesPlusEquity) <= 1;
    }

    public function differenceCents(): int
    {
        return $this->totalAssetsCents() - ($this->totalLiabilitiesCents() + $this->totalEquityCents());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'company_id' => $this->companyId->toString(),
            'period' => $this->period->toArray(),
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s'),
            'assets' => [
                'current' => $this->currentAssets,
                'fixed' => $this->fixedAssets,
                'total_current_cents' => $this->totalCurrentAssetsCents,
                'total_fixed_cents' => $this->totalFixedAssetsCents,
                'total_cents' => $this->totalAssetsCents(),
            ],
            'liabilities' => [
                'current' => $this->currentLiabilities,
                'long_term' => $this->longTermLiabilities,
                'total_current_cents' => $this->totalCurrentLiabilitiesCents,
                'total_long_term_cents' => $this->totalLongTermLiabilitiesCents,
                'total_cents' => $this->totalLiabilitiesCents(),
            ],
            'equity' => [
                'accounts' => $this->equityAccounts,
                'retained_earnings_cents' => $this->retainedEarningsCents,
                'total_cents' => $this->totalEquityCents(),
            ],
            'is_balanced' => $this->isBalanced(),
            'difference_cents' => $this->differenceCents(),
        ];
    }
}
