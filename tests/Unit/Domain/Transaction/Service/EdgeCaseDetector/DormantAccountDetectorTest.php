<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\Service\EdgeCaseDetector\DormantAccountDetector;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class DormantAccountDetectorTest extends TestCase
{
    private DormantAccountDetector $detector;
    private TransactionRepositoryInterface $transactionRepository;
    private CompanyId $companyId;
    private EdgeCaseThresholds $thresholds;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->detector = new DormantAccountDetector($this->transactionRepository);
        $this->companyId = CompanyId::generate();
        $this->thresholds = EdgeCaseThresholds::defaults(); // 90 days threshold
    }

    public function test_detects_dormant_account(): void
    {
        $account = $this->createAccount(1000, 'Cash');

        // Last activity was 100 days ago (> 90 day threshold)
        $this->transactionRepository->method('getLastActivityDate')
            ->willReturn(new DateTimeImmutable('-100 days'));

        $lines = [
            [
                'account' => $account,
                'debit_cents' => 50_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('dormant_account', $result->flags()[0]->type());
    }

    public function test_allows_active_account(): void
    {
        $account = $this->createAccount(1000, 'Cash');

        // Last activity was 30 days ago (< 90 day threshold)
        $this->transactionRepository->method('getLastActivityDate')
            ->willReturn(new DateTimeImmutable('-30 days'));

        $lines = [
            [
                'account' => $account,
                'debit_cents' => 50_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_allows_new_account_with_no_history(): void
    {
        $account = $this->createAccount(1000, 'Cash');

        // No previous transactions (new account)
        $this->transactionRepository->method('getLastActivityDate')
            ->willReturn(null);

        $lines = [
            [
                'account' => $account,
                'debit_cents' => 50_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        // New accounts should not be flagged as dormant
        $this->assertFalse($result->hasFlags());
    }

    public function test_detects_multiple_dormant_accounts(): void
    {
        $account1 = $this->createAccount(1000, 'Cash');
        $account2 = $this->createAccount(1200, 'Equipment');

        // Both accounts are dormant
        $this->transactionRepository->method('getLastActivityDate')
            ->willReturn(new DateTimeImmutable('-120 days'));

        $lines = [
            ['account' => $account1, 'debit_cents' => 50_000, 'credit_cents' => 0],
            ['account' => $account2, 'debit_cents' => 0, 'credit_cents' => 50_000],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertCount(2, $result->flags());
    }

    public function test_respects_custom_threshold(): void
    {
        $customThresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 180, // 6 months
        ]);

        $account = $this->createAccount(1000, 'Cash');

        // Last activity was 100 days ago (< 180 day threshold)
        $this->transactionRepository->method('getLastActivityDate')
            ->willReturn(new DateTimeImmutable('-100 days'));

        $lines = [
            [
                'account' => $account,
                'debit_cents' => 50_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->companyId, $customThresholds);

        // Should NOT flag because 100 < 180
        $this->assertFalse($result->hasFlags());
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
