<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Ledger\Service\BalanceCalculationService;
use Domain\Transaction\Service\EdgeCaseDetector\BalanceImpactDetector;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class BalanceImpactDetectorTest extends TestCase
{
    private BalanceImpactDetector $detector;
    private LedgerRepositoryInterface $ledgerRepository;
    private BalanceCalculationService $balanceCalculator;
    private EdgeCaseThresholds $thresholds;
    private CompanyId $companyId;

    protected function setUp(): void
    {
        $this->ledgerRepository = $this->createMock(LedgerRepositoryInterface::class);
        $this->balanceCalculator = new BalanceCalculationService();
        $this->detector = new BalanceImpactDetector($this->ledgerRepository, $this->balanceCalculator);
        $this->thresholds = EdgeCaseThresholds::defaults();
        $this->companyId = CompanyId::generate();
    }

    public function test_detects_negative_balance_for_asset_account(): void
    {
        $cashAccount = $this->createAccount(1000, 'Cash');

        // Current balance: $1,000 (100,000 cents)
        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(100_000);

        // Withdrawing $1,500 would make it -$500
        $lines = [
            [
                'account' => $cashAccount,
                'debit_cents' => 0,
                'credit_cents' => 150_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('negative_balance', $result->flags()[0]->type());
    }

    public function test_allows_transaction_maintaining_positive_balance(): void
    {
        $cashAccount = $this->createAccount(1000, 'Cash');

        // Current balance: $1,000
        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(100_000);

        // Withdrawing $500 keeps it at $500
        $lines = [
            [
                'account' => $cashAccount,
                'debit_cents' => 0,
                'credit_cents' => 50_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $negativeFlags = array_filter($result->flags(), fn($f) => $f->type() === 'negative_balance');
        $this->assertEmpty($negativeFlags);
    }

    public function test_detects_negative_equity_balance(): void
    {
        $equityAccount = $this->createAccount(3100, 'Retained Earnings');

        // Current balance: $500
        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(50_000);

        // Owner draws $1,000 making equity -$500
        $lines = [
            [
                'account' => $equityAccount,
                'debit_cents' => 100_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        // Should flag for review - equity going negative
        $this->assertTrue($result->hasFlags());
        $this->assertSame('negative_balance', $result->flags()[0]->type());
    }

    public function test_respects_disabled_negative_balance_check(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 0, // Disabled
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        $cashAccount = $this->createAccount(1000, 'Cash');

        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(100_000);

        $lines = [
            [
                'account' => $cashAccount,
                'debit_cents' => 0,
                'credit_cents' => 150_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_allows_debit_increasing_asset_balance(): void
    {
        $cashAccount = $this->createAccount(1000, 'Cash');

        // Current balance: $1,000
        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(100_000);

        // Receiving $500 - debit increases asset
        $lines = [
            [
                'account' => $cashAccount,
                'debit_cents' => 50_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_allows_credit_increasing_liability_balance(): void
    {
        $apAccount = $this->createAccount(2100, 'Accounts Payable');

        // Current balance: $1,000
        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(100_000);

        // Taking on more debt - credit increases liability
        $lines = [
            [
                'account' => $apAccount,
                'debit_cents' => 0,
                'credit_cents' => 50_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_detects_liability_going_negative(): void
    {
        $apAccount = $this->createAccount(2100, 'Accounts Payable');

        // Current balance: $500
        $this->ledgerRepository->method('getBalanceCents')
            ->willReturn(50_000);

        // Paying off $1,000 would make it -$500
        $lines = [
            [
                'account' => $apAccount,
                'debit_cents' => 100_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('negative_balance', $result->flags()[0]->type());
    }

    private function createAccount(int $code, string $name): Account
    {
        return Account::create(
            AccountCode::fromInt($code),
            $name,
            $this->companyId,
        );
    }
}
