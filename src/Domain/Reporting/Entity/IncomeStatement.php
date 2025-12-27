<?php

declare(strict_types=1);

namespace Domain\Reporting\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Income Statement (Profit & Loss) report entity.
 * Shows revenue, expenses, and net income for a period.
 */
final readonly class IncomeStatement
{
    /**
     * @param array<array<string, mixed>> $revenueAccounts
     * @param array<array<string, mixed>> $expenseAccounts
     */
    public function __construct(
        private ReportId $id,
        private CompanyId $companyId,
        private ReportPeriod $period,
        private DateTimeImmutable $generatedAt,
        private array $revenueAccounts,
        private int $totalRevenueCents,
        private array $expenseAccounts,
        private int $totalExpensesCents
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

    /**
     * @return array<array<string, mixed>>
     */
    public function revenueAccounts(): array
    {
        return $this->revenueAccounts;
    }

    public function totalRevenueCents(): int
    {
        return $this->totalRevenueCents;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function expenseAccounts(): array
    {
        return $this->expenseAccounts;
    }

    public function totalExpensesCents(): int
    {
        return $this->totalExpensesCents;
    }

    /**
     * BR-FR-006: Net Income = Revenue - Expenses
     */
    public function netIncomeCents(): int
    {
        return $this->totalRevenueCents - $this->totalExpensesCents;
    }

    public function isProfit(): bool
    {
        return $this->netIncomeCents() > 0;
    }

    public function isLoss(): bool
    {
        return $this->netIncomeCents() < 0;
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
            'revenue_accounts' => $this->revenueAccounts,
            'total_revenue_cents' => $this->totalRevenueCents,
            'expense_accounts' => $this->expenseAccounts,
            'total_expenses_cents' => $this->totalExpensesCents,
            'net_income_cents' => $this->netIncomeCents(),
            'is_profit' => $this->isProfit(),
        ];
    }
}
