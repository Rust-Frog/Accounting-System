<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Ledger\Service\BalanceCalculationService;
use Domain\Transaction\Repository\ThresholdRepositoryInterface;
use Domain\Transaction\Service\EdgeCaseDetectionService;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class EdgeCaseDetectionServiceTest extends TestCase
{
    private EdgeCaseDetectionService $service;
    private ThresholdRepositoryInterface $thresholdRepository;
    private AccountRepositoryInterface $accountRepository;
    private LedgerRepositoryInterface $ledgerRepository;
    private CompanyId $companyId;

    protected function setUp(): void
    {
        $this->thresholdRepository = $this->createMock(ThresholdRepositoryInterface::class);
        $this->thresholdRepository->method('getForCompany')
            ->willReturn(EdgeCaseThresholds::defaults());

        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->ledgerRepository = $this->createMock(LedgerRepositoryInterface::class);
        $this->ledgerRepository->method('getBalanceCents')->willReturn(1_000_000);

        $this->service = new EdgeCaseDetectionService(
            $this->thresholdRepository,
            $this->accountRepository,
            $this->ledgerRepository,
            new BalanceCalculationService(),
        );

        $this->companyId = CompanyId::generate();
    }

    public function test_detects_future_dated_transaction(): void
    {
        $assetAccount = $this->createAccount(1000, 'Cash');

        $this->accountRepository->method('findById')
            ->willReturn($assetAccount);

        $lines = [
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 50_000, 'credit_cents' => 0],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('+1 week'),
            'Future payment',
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());

        $flagTypes = array_map(fn($f) => $f->type(), $result->flags());
        $this->assertContains('future_dated', $flagTypes);
    }

    public function test_detects_contra_revenue_entry(): void
    {
        $revenueAccount = $this->createAccount(4100, 'Sales Revenue');

        $this->accountRepository->method('findById')
            ->willReturn($revenueAccount);

        // Debit to revenue = contra entry
        $lines = [
            ['account_id' => $revenueAccount->id()->toString(), 'debit_cents' => 100_000, 'credit_cents' => 0],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('today'),
            'Sales return',
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());

        $flagTypes = array_map(fn($f) => $f->type(), $result->flags());
        $this->assertContains('contra_revenue', $flagTypes);
    }

    public function test_detects_multiple_edge_cases(): void
    {
        $revenueAccount = $this->createAccount(4100, 'Sales Revenue');

        $this->accountRepository->method('findById')
            ->willReturn($revenueAccount);

        // Future date + debit to revenue (2 flags)
        $lines = [
            ['account_id' => $revenueAccount->id()->toString(), 'debit_cents' => 100_000, 'credit_cents' => 0],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('+1 week'),
            'Test',
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());
        $this->assertGreaterThanOrEqual(2, count($result->flags()));

        $flagTypes = array_map(fn($f) => $f->type(), $result->flags());
        $this->assertContains('future_dated', $flagTypes);
        $this->assertContains('contra_revenue', $flagTypes);
    }

    public function test_returns_clean_for_normal_revenue_transaction(): void
    {
        $assetAccount = $this->createAccount(1000, 'Cash');
        $revenueAccount = $this->createAccount(4100, 'Sales Revenue');

        $this->accountRepository->method('findById')
            ->willReturnCallback(function (AccountId $id) use ($assetAccount, $revenueAccount) {
                return $id->toString() === $assetAccount->id()->toString()
                    ? $assetAccount
                    : $revenueAccount;
            });

        // Normal revenue transaction: Debit Asset, Credit Revenue
        $lines = [
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 50_000, 'credit_cents' => 0],
            ['account_id' => $revenueAccount->id()->toString(), 'debit_cents' => 0, 'credit_cents' => 50_000],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('today'), // Use 'today' to match service's comparison
            'Payment received for services rendered',
            $this->companyId,
        );

        // Normal revenue entry: check which flags (if any) require approval
        $approvalFlags = array_filter($result->flags(), fn($f) => $f->requiresApproval());
        $approvalTypes = array_map(fn($f) => $f->type(), $approvalFlags);

        // Should have no approval-requiring flags for normal transaction
        $this->assertEmpty($approvalTypes, 'Unexpected approval flags: ' . implode(', ', $approvalTypes));
    }

    public function test_detects_missing_description(): void
    {
        $assetAccount = $this->createAccount(1000, 'Cash');

        $this->accountRepository->method('findById')
            ->willReturn($assetAccount);

        $lines = [
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 50_000, 'credit_cents' => 0],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('today'),
            'hi', // Too short
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());

        $flagTypes = array_map(fn($f) => $f->type(), $result->flags());
        $this->assertContains('missing_description', $flagTypes);
    }

    public function test_respects_custom_thresholds(): void
    {
        $customThresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 100_000, // $1,000 threshold
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        $assetAccount = $this->createAccount(1000, 'Cash');

        $this->accountRepository->method('findById')
            ->willReturn($assetAccount);

        // $2,000 transaction exceeds $1,000 threshold
        $lines = [
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 200_000, 'credit_cents' => 0],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('today'),
            'Large deposit into account',
            $this->companyId,
            $customThresholds,
        );

        $this->assertTrue($result->requiresApproval());

        $flagTypes = array_map(fn($f) => $f->type(), $result->flags());
        $this->assertContains('large_amount', $flagTypes);
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
