<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Mysql\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use Infrastructure\Persistence\Mysql\Repository\MysqlThresholdRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class MysqlThresholdRepositoryTest extends TestCase
{
    public function test_returns_defaults_when_no_settings_exist(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('execute')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = new MysqlThresholdRepository($pdo);
        $companyId = CompanyId::generate();

        $thresholds = $repository->getForCompany($companyId);

        $this->assertInstanceOf(EdgeCaseThresholds::class, $thresholds);
        $this->assertSame(1_000_000, $thresholds->largeTransactionThresholdCents());
    }

    public function test_returns_configured_thresholds_from_database(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'large_transaction_threshold_cents' => 5_000_000,
            'backdated_days_threshold' => 60,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 0,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 1,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 180,
        ]);
        $stmt->method('execute')->willReturn(true);
        $pdo->method('prepare')->willReturn($stmt);

        $repository = new MysqlThresholdRepository($pdo);
        $companyId = CompanyId::generate();

        $thresholds = $repository->getForCompany($companyId);

        $this->assertSame(5_000_000, $thresholds->largeTransactionThresholdCents());
        $this->assertSame(60, $thresholds->backdatedDaysThreshold());
        $this->assertFalse($thresholds->requireApprovalContraEntry());
        $this->assertTrue($thresholds->flagRoundNumbers());
    }
}
