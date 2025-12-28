<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Service\EdgeCaseDetector\AccountTypeAnomalyDetector;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class AccountTypeAnomalyDetectorTest extends TestCase
{
    private AccountTypeAnomalyDetector $detector;
    private EdgeCaseThresholds $thresholds;
    private CompanyId $companyId;

    protected function setUp(): void
    {
        $this->detector = new AccountTypeAnomalyDetector();
        $this->thresholds = EdgeCaseThresholds::defaults();
        $this->companyId = CompanyId::generate();
    }

    public function test_detects_contra_revenue_debit_to_revenue_account(): void
    {
        $revenueAccount = $this->createAccount(4100, 'Sales Revenue', AccountType::REVENUE);

        $lines = [
            [
                'account' => $revenueAccount,
                'debit_cents' => 100_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('contra_revenue', $result->flags()[0]->type());
    }

    public function test_allows_normal_credit_to_revenue(): void
    {
        $revenueAccount = $this->createAccount(4100, 'Sales Revenue', AccountType::REVENUE);

        $lines = [
            [
                'account' => $revenueAccount,
                'debit_cents' => 0,
                'credit_cents' => 100_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $contraFlags = array_filter($result->flags(), fn($f) => $f->type() === 'contra_revenue');
        $this->assertEmpty($contraFlags);
    }

    public function test_detects_contra_expense_credit_to_expense_account(): void
    {
        $expenseAccount = $this->createAccount(5200, 'Office Supplies', AccountType::EXPENSE);

        $lines = [
            [
                'account' => $expenseAccount,
                'debit_cents' => 0,
                'credit_cents' => 50_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('contra_expense', $result->flags()[0]->type());
    }

    public function test_allows_credit_to_asset_without_flag(): void
    {
        // Asset writedown detection was intentionally disabled
        // Credits to assets are normal (e.g., paying for things from cash)
        $assetAccount = $this->createAccount(1500, 'Equipment', AccountType::ASSET);

        $lines = [
            [
                'account' => $assetAccount,
                'debit_cents' => 0,
                'credit_cents' => 200_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $writedownFlags = array_filter($result->flags(), fn($f) => $f->type() === 'asset_writedown');
        $this->assertEmpty($writedownFlags);
    }

    public function test_detects_equity_adjustment(): void
    {
        $equityAccount = $this->createAccount(3100, 'Retained Earnings', AccountType::EQUITY);

        $lines = [
            [
                'account' => $equityAccount,
                'debit_cents' => 300_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('equity_adjustment', $result->flags()[0]->type());
    }

    public function test_allows_liability_reduction_without_flag(): void
    {
        // Liability reduction detection was intentionally disabled
        // Debits to liabilities are normal (e.g., paying off debts)
        $liabilityAccount = $this->createAccount(2100, 'Accounts Payable', AccountType::LIABILITY);

        $lines = [
            [
                'account' => $liabilityAccount,
                'debit_cents' => 150_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $liabilityFlags = array_filter($result->flags(), fn($f) => $f->type() === 'liability_reduction');
        $this->assertEmpty($liabilityFlags);
    }

    public function test_respects_disabled_contra_entry_detection(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 0,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        $revenueAccount = $this->createAccount(4100, 'Sales Revenue', AccountType::REVENUE);

        $lines = [
            [
                'account' => $revenueAccount,
                'debit_cents' => 100_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $thresholds);

        // Should only have liability_reduction flag if any (not contra flags)
        $contraFlags = array_filter($result->flags(), fn($f) => in_array($f->type(), ['contra_revenue', 'contra_expense', 'asset_writedown']));
        $this->assertEmpty($contraFlags);
    }

    private function createAccount(int $code, string $name, AccountType $type): Account
    {
        return Account::create(
            AccountCode::fromInt($code),
            $name,
            $this->companyId,
        );
    }
}
