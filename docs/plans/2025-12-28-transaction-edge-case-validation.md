# Transaction Edge Case Validation - Enhanced Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement 19 edge case detection rules that flag rare but critical accounting scenarios for approval routing, without breaking existing hard-block validation.

**Architecture:** New `EdgeCaseDetectionService` runs AFTER existing `TransactionValidationService` (hard blocks). Edge cases return flags that route transactions to approval workflow instead of blocking. Company-configurable thresholds stored in `company_settings` table.

**Tech Stack:** PHP 8.2, Domain-Driven Design, MySQL, existing Approval system

---

## Critical Integration Points (DO NOT BREAK)

| Existing Component | Location | Integration Strategy |
|-------------------|----------|---------------------|
| Hard Block Validation | `src/Domain/Transaction/Service/TransactionValidationService.php` | Edge case runs AFTER this passes |
| Approval System | `src/Domain/Approval/Entity/Approval.php` | Route flagged transactions here |
| Transaction Creation | `src/Application/Handler/Transaction/CreateTransactionHandler.php` | Inject EdgeCaseDetectionService |
| Balance Calculation | `src/Domain/Ledger/Service/BalanceCalculationService.php` | Query for negative balance detection |
| Account Types | `src/Domain/ChartOfAccounts/ValueObject/AccountType.php` | Use for contra-entry detection |

---

## Phase 3A: Infrastructure Foundation

**Purpose:** Build the foundation - settings storage, database schema, and service shell - before any detection logic.

---

### Task 3A.1: Database Migration for Company Threshold Settings

**Files:**
- Create: `docker/mysql/migrations/002_edge_case_thresholds.sql`

**Step 1: Write the migration SQL**

```sql
-- Migration: Add edge case detection threshold columns to company_settings
-- Date: 2025-12-28
-- Purpose: Store configurable thresholds for transaction edge case validation

ALTER TABLE company_settings
    ADD COLUMN large_transaction_threshold_cents BIGINT NOT NULL DEFAULT 1000000 COMMENT '10000.00 default',
    ADD COLUMN backdated_days_threshold INT NOT NULL DEFAULT 30,
    ADD COLUMN future_dated_allowed TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN require_approval_contra_entry TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN require_approval_equity_adjustment TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN require_approval_negative_balance TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN flag_round_numbers TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Optional audit flag',
    ADD COLUMN flag_period_end_entries TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Optional audit flag',
    ADD COLUMN dormant_account_days_threshold INT NOT NULL DEFAULT 90;

-- Index for efficient threshold lookups
CREATE INDEX idx_company_settings_thresholds ON company_settings(company_id, large_transaction_threshold_cents);
```

**Step 2: Apply migration to running container**

Run: `docker exec accounting-mysql mysql -uroot -proot accounting < docker/mysql/migrations/002_edge_case_thresholds.sql`
Expected: Query OK, 0 rows affected

**Step 3: Verify columns exist**

Run: `docker exec accounting-mysql mysql -uroot -proot accounting -e "DESCRIBE company_settings;"`
Expected: See new columns listed

**Step 4: Commit**

```bash
git add docker/mysql/migrations/002_edge_case_thresholds.sql
git commit -m "feat(db): add edge case threshold columns to company_settings"
```

---

### Task 3A.2: Update Fresh Schema for New Installs

**Files:**
- Modify: `docker/mysql/00-fresh-schema.sql:113-124`

**Step 1: Locate company_settings table definition**

Current definition ends at line ~124. Add columns BEFORE the closing parenthesis.

**Step 2: Add threshold columns to schema**

Find this block (around line 113-124):
```sql
CREATE TABLE company_settings (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL UNIQUE,
    fiscal_year_start_month TINYINT NOT NULL DEFAULT 1,
    fiscal_year_start_day TINYINT NOT NULL DEFAULT 1,
    settings_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_company_settings_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Replace with:
```sql
CREATE TABLE company_settings (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL UNIQUE,
    fiscal_year_start_month TINYINT NOT NULL DEFAULT 1,
    fiscal_year_start_day TINYINT NOT NULL DEFAULT 1,
    settings_json JSON NULL,
    -- Edge case detection thresholds
    large_transaction_threshold_cents BIGINT NOT NULL DEFAULT 1000000,
    backdated_days_threshold INT NOT NULL DEFAULT 30,
    future_dated_allowed TINYINT(1) NOT NULL DEFAULT 1,
    require_approval_contra_entry TINYINT(1) NOT NULL DEFAULT 1,
    require_approval_equity_adjustment TINYINT(1) NOT NULL DEFAULT 1,
    require_approval_negative_balance TINYINT(1) NOT NULL DEFAULT 1,
    flag_round_numbers TINYINT(1) NOT NULL DEFAULT 0,
    flag_period_end_entries TINYINT(1) NOT NULL DEFAULT 0,
    dormant_account_days_threshold INT NOT NULL DEFAULT 90,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_company_settings_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Step 3: Commit**

```bash
git add docker/mysql/00-fresh-schema.sql
git commit -m "feat(db): add edge case thresholds to fresh schema"
```

---

### Task 3A.3: Create EdgeCaseThresholds Value Object

**Files:**
- Create: `src/Domain/Transaction/ValueObject/EdgeCaseThresholds.php`
- Test: `tests/Unit/Domain/Transaction/ValueObject/EdgeCaseThresholdsTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class EdgeCaseThresholdsTest extends TestCase
{
    public function test_creates_with_defaults(): void
    {
        $thresholds = EdgeCaseThresholds::defaults();

        $this->assertSame(1_000_000, $thresholds->largeTransactionThresholdCents());
        $this->assertSame(30, $thresholds->backdatedDaysThreshold());
        $this->assertTrue($thresholds->futureDatedAllowed());
        $this->assertTrue($thresholds->requireApprovalContraEntry());
        $this->assertTrue($thresholds->requireApprovalEquityAdjustment());
        $this->assertTrue($thresholds->requireApprovalNegativeBalance());
        $this->assertFalse($thresholds->flagRoundNumbers());
        $this->assertFalse($thresholds->flagPeriodEndEntries());
        $this->assertSame(90, $thresholds->dormantAccountDaysThreshold());
    }

    public function test_creates_from_database_row(): void
    {
        $row = [
            'large_transaction_threshold_cents' => 5_000_000,
            'backdated_days_threshold' => 60,
            'future_dated_allowed' => 0,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 0,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 1,
            'flag_period_end_entries' => 1,
            'dormant_account_days_threshold' => 180,
        ];

        $thresholds = EdgeCaseThresholds::fromDatabaseRow($row);

        $this->assertSame(5_000_000, $thresholds->largeTransactionThresholdCents());
        $this->assertSame(60, $thresholds->backdatedDaysThreshold());
        $this->assertFalse($thresholds->futureDatedAllowed());
        $this->assertTrue($thresholds->requireApprovalContraEntry());
        $this->assertFalse($thresholds->requireApprovalEquityAdjustment());
        $this->assertTrue($thresholds->requireApprovalNegativeBalance());
        $this->assertTrue($thresholds->flagRoundNumbers());
        $this->assertTrue($thresholds->flagPeriodEndEntries());
        $this->assertSame(180, $thresholds->dormantAccountDaysThreshold());
    }

    public function test_calculates_below_threshold_range(): void
    {
        $thresholds = EdgeCaseThresholds::defaults();

        // 90% of 1,000,000 = 900,000
        $this->assertSame(900_000, $thresholds->belowThresholdFloorCents());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/EdgeCaseThresholdsTest.php -v`
Expected: FAIL with "Class 'EdgeCaseThresholds' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\ValueObject;

/**
 * Immutable value object holding company-specific edge case detection thresholds.
 * Used by EdgeCaseDetectionService to determine which rules to apply.
 */
final readonly class EdgeCaseThresholds
{
    private function __construct(
        private int $largeTransactionThresholdCents,
        private int $backdatedDaysThreshold,
        private bool $futureDatedAllowed,
        private bool $requireApprovalContraEntry,
        private bool $requireApprovalEquityAdjustment,
        private bool $requireApprovalNegativeBalance,
        private bool $flagRoundNumbers,
        private bool $flagPeriodEndEntries,
        private int $dormantAccountDaysThreshold,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            largeTransactionThresholdCents: 1_000_000, // $10,000.00
            backdatedDaysThreshold: 30,
            futureDatedAllowed: true,
            requireApprovalContraEntry: true,
            requireApprovalEquityAdjustment: true,
            requireApprovalNegativeBalance: true,
            flagRoundNumbers: false,
            flagPeriodEndEntries: false,
            dormantAccountDaysThreshold: 90,
        );
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            largeTransactionThresholdCents: (int) ($row['large_transaction_threshold_cents'] ?? 1_000_000),
            backdatedDaysThreshold: (int) ($row['backdated_days_threshold'] ?? 30),
            futureDatedAllowed: (bool) ($row['future_dated_allowed'] ?? true),
            requireApprovalContraEntry: (bool) ($row['require_approval_contra_entry'] ?? true),
            requireApprovalEquityAdjustment: (bool) ($row['require_approval_equity_adjustment'] ?? true),
            requireApprovalNegativeBalance: (bool) ($row['require_approval_negative_balance'] ?? true),
            flagRoundNumbers: (bool) ($row['flag_round_numbers'] ?? false),
            flagPeriodEndEntries: (bool) ($row['flag_period_end_entries'] ?? false),
            dormantAccountDaysThreshold: (int) ($row['dormant_account_days_threshold'] ?? 90),
        );
    }

    public function largeTransactionThresholdCents(): int
    {
        return $this->largeTransactionThresholdCents;
    }

    public function backdatedDaysThreshold(): int
    {
        return $this->backdatedDaysThreshold;
    }

    public function futureDatedAllowed(): bool
    {
        return $this->futureDatedAllowed;
    }

    public function requireApprovalContraEntry(): bool
    {
        return $this->requireApprovalContraEntry;
    }

    public function requireApprovalEquityAdjustment(): bool
    {
        return $this->requireApprovalEquityAdjustment;
    }

    public function requireApprovalNegativeBalance(): bool
    {
        return $this->requireApprovalNegativeBalance;
    }

    public function flagRoundNumbers(): bool
    {
        return $this->flagRoundNumbers;
    }

    public function flagPeriodEndEntries(): bool
    {
        return $this->flagPeriodEndEntries;
    }

    public function dormantAccountDaysThreshold(): int
    {
        return $this->dormantAccountDaysThreshold;
    }

    /**
     * Returns 90% of large transaction threshold for "just below threshold" detection.
     */
    public function belowThresholdFloorCents(): int
    {
        return (int) ($this->largeTransactionThresholdCents * 0.9);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/EdgeCaseThresholdsTest.php -v`
Expected: OK (3 tests, 21 assertions)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/ValueObject/EdgeCaseThresholds.php tests/Unit/Domain/Transaction/ValueObject/EdgeCaseThresholdsTest.php
git commit -m "feat(domain): add EdgeCaseThresholds value object"
```

---

### Task 3A.4: Create EdgeCaseFlag Value Object

**Files:**
- Create: `src/Domain/Transaction/ValueObject/EdgeCaseFlag.php`
- Test: `tests/Unit/Domain/Transaction/ValueObject/EdgeCaseFlagTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use App\Domain\Transaction\ValueObject\EdgeCaseFlag;
use PHPUnit\Framework\TestCase;

final class EdgeCaseFlagTest extends TestCase
{
    public function test_creates_future_dated_flag(): void
    {
        $flag = EdgeCaseFlag::futureDated('2025-01-15', '2024-12-27');

        $this->assertSame('future_dated', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('2025-01-15', $flag->description());
    }

    public function test_creates_backdated_flag(): void
    {
        $flag = EdgeCaseFlag::backdated('2024-11-01', 56);

        $this->assertSame('backdated', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('56 days', $flag->description());
    }

    public function test_creates_large_amount_flag(): void
    {
        $flag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);

        $this->assertSame('large_amount', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('50,000.00', $flag->description());
    }

    public function test_creates_contra_revenue_flag(): void
    {
        $flag = EdgeCaseFlag::contraRevenue('Sales Revenue', 100_000);

        $this->assertSame('contra_revenue', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('Sales Revenue', $flag->description());
    }

    public function test_creates_contra_expense_flag(): void
    {
        $flag = EdgeCaseFlag::contraExpense('Office Supplies', 50_000);

        $this->assertSame('contra_expense', $flag->type());
        $this->assertTrue($flag->requiresApproval());
    }

    public function test_creates_asset_writedown_flag(): void
    {
        $flag = EdgeCaseFlag::assetWritedown('Equipment', 200_000);

        $this->assertSame('asset_writedown', $flag->type());
        $this->assertTrue($flag->requiresApproval());
    }

    public function test_creates_equity_adjustment_flag(): void
    {
        $flag = EdgeCaseFlag::equityAdjustment('Retained Earnings', 300_000, 'debit');

        $this->assertSame('equity_adjustment', $flag->type());
        $this->assertTrue($flag->requiresApproval());
    }

    public function test_creates_negative_balance_flag(): void
    {
        $flag = EdgeCaseFlag::negativeBalance('Cash', 10_000, -5_000);

        $this->assertSame('negative_balance', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('-50.00', $flag->description());
    }

    public function test_creates_round_number_flag_as_review_only(): void
    {
        $flag = EdgeCaseFlag::roundNumber(10_000_000);

        $this->assertSame('round_number', $flag->type());
        $this->assertFalse($flag->requiresApproval());
        $this->assertTrue($flag->isReviewOnly());
    }

    public function test_creates_period_end_flag_as_review_only(): void
    {
        $flag = EdgeCaseFlag::periodEnd('2024-12-31', 'year');

        $this->assertSame('period_end', $flag->type());
        $this->assertFalse($flag->requiresApproval());
        $this->assertTrue($flag->isReviewOnly());
    }

    public function test_serializes_to_array(): void
    {
        $flag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);
        $array = $flag->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('requires_approval', $array);
        $this->assertArrayHasKey('context', $array);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/EdgeCaseFlagTest.php -v`
Expected: FAIL with "Class 'EdgeCaseFlag' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\ValueObject;

/**
 * Represents a detected edge case flag on a transaction.
 * Used for audit logging and approval routing decisions.
 */
final readonly class EdgeCaseFlag
{
    private function __construct(
        private string $type,
        private string $description,
        private bool $requiresApproval,
        private array $context,
    ) {
    }

    // === Factory Methods for Each Edge Case Type ===

    public static function futureDated(string $transactionDate, string $today): self
    {
        return new self(
            type: 'future_dated',
            description: "Transaction dated {$transactionDate} is in the future (today: {$today})",
            requiresApproval: true,
            context: ['transaction_date' => $transactionDate, 'today' => $today],
        );
    }

    public static function backdated(string $transactionDate, int $daysBack): self
    {
        return new self(
            type: 'backdated',
            description: "Transaction dated {$transactionDate} is {$daysBack} days in the past",
            requiresApproval: true,
            context: ['transaction_date' => $transactionDate, 'days_back' => $daysBack],
        );
    }

    public static function largeAmount(int $amountCents, int $thresholdCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);
        $thresholdFormatted = number_format($thresholdCents / 100, 2);

        return new self(
            type: 'large_amount',
            description: "Transaction amount \${$amountFormatted} exceeds threshold \${$thresholdFormatted}",
            requiresApproval: true,
            context: ['amount_cents' => $amountCents, 'threshold_cents' => $thresholdCents],
        );
    }

    public static function belowThreshold(int $amountCents, int $thresholdCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);
        $thresholdFormatted = number_format($thresholdCents / 100, 2);
        $percentage = round(($amountCents / $thresholdCents) * 100, 1);

        return new self(
            type: 'below_threshold',
            description: "Transaction amount \${$amountFormatted} is {$percentage}% of threshold \${$thresholdFormatted}",
            requiresApproval: false,
            context: ['amount_cents' => $amountCents, 'threshold_cents' => $thresholdCents, 'percentage' => $percentage],
        );
    }

    public static function contraRevenue(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'contra_revenue',
            description: "Debiting revenue account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function contraExpense(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'contra_expense',
            description: "Crediting expense account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function assetWritedown(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'asset_writedown',
            description: "Crediting asset account '{$accountName}' for \${$amountFormatted} (write-down/disposal)",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function liabilityReduction(string $accountName, int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'liability_reduction',
            description: "Debiting liability account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: false,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents],
        );
    }

    public static function equityAdjustment(string $accountName, int $amountCents, string $lineType): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);
        $action = $lineType === 'debit' ? 'Debiting' : 'Crediting';

        return new self(
            type: 'equity_adjustment',
            description: "{$action} equity account '{$accountName}' for \${$amountFormatted}",
            requiresApproval: true,
            context: ['account_name' => $accountName, 'amount_cents' => $amountCents, 'line_type' => $lineType],
        );
    }

    public static function negativeBalance(string $accountName, int $currentBalanceCents, int $projectedBalanceCents): self
    {
        $currentFormatted = number_format($currentBalanceCents / 100, 2);
        $projectedFormatted = number_format($projectedBalanceCents / 100, 2);

        return new self(
            type: 'negative_balance',
            description: "Account '{$accountName}' would go negative: \${$currentFormatted} -> \${$projectedFormatted}",
            requiresApproval: true,
            context: [
                'account_name' => $accountName,
                'current_balance_cents' => $currentBalanceCents,
                'projected_balance_cents' => $projectedBalanceCents,
            ],
        );
    }

    public static function roundNumber(int $amountCents): self
    {
        $amountFormatted = number_format($amountCents / 100, 2);

        return new self(
            type: 'round_number',
            description: "Transaction amount \${$amountFormatted} is suspiciously round",
            requiresApproval: false,
            context: ['amount_cents' => $amountCents],
        );
    }

    public static function periodEnd(string $date, string $periodType): self
    {
        return new self(
            type: 'period_end',
            description: "Transaction on {$date} is near {$periodType}-end (window dressing risk)",
            requiresApproval: false,
            context: ['date' => $date, 'period_type' => $periodType],
        );
    }

    public static function dormantAccount(string $accountName, int $daysSinceLastActivity): self
    {
        return new self(
            type: 'dormant_account',
            description: "Account '{$accountName}' has had no activity for {$daysSinceLastActivity} days",
            requiresApproval: false,
            context: ['account_name' => $accountName, 'days_since_last_activity' => $daysSinceLastActivity],
        );
    }

    public static function missingDescription(): self
    {
        return new self(
            type: 'missing_description',
            description: 'Transaction has empty or minimal description',
            requiresApproval: false,
            context: [],
        );
    }

    // === Accessors ===

    public function type(): string
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function requiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function isReviewOnly(): bool
    {
        return !$this->requiresApproval;
    }

    public function context(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
            'requires_approval' => $this->requiresApproval,
            'context' => $this->context,
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/EdgeCaseFlagTest.php -v`
Expected: OK (11 tests, 26 assertions)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/ValueObject/EdgeCaseFlag.php tests/Unit/Domain/Transaction/ValueObject/EdgeCaseFlagTest.php
git commit -m "feat(domain): add EdgeCaseFlag value object for detection results"
```

---

### Task 3A.5: Create EdgeCaseDetectionResult Value Object

**Files:**
- Create: `src/Domain/Transaction/ValueObject/EdgeCaseDetectionResult.php`
- Test: `tests/Unit/Domain/Transaction/ValueObject/EdgeCaseDetectionResultTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseFlag;
use PHPUnit\Framework\TestCase;

final class EdgeCaseDetectionResultTest extends TestCase
{
    public function test_clean_result_has_no_flags(): void
    {
        $result = EdgeCaseDetectionResult::clean();

        $this->assertTrue($result->isClean());
        $this->assertFalse($result->hasFlags());
        $this->assertFalse($result->requiresApproval());
        $this->assertEmpty($result->flags());
    }

    public function test_result_with_approval_required_flag(): void
    {
        $flag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);
        $result = EdgeCaseDetectionResult::withFlags([$flag]);

        $this->assertFalse($result->isClean());
        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertCount(1, $result->flags());
    }

    public function test_result_with_review_only_flag(): void
    {
        $flag = EdgeCaseFlag::roundNumber(10_000_000);
        $result = EdgeCaseDetectionResult::withFlags([$flag]);

        $this->assertTrue($result->hasFlags());
        $this->assertFalse($result->requiresApproval());
        $this->assertTrue($result->hasReviewOnlyFlags());
    }

    public function test_result_with_mixed_flags(): void
    {
        $approvalFlag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);
        $reviewFlag = EdgeCaseFlag::roundNumber(5_000_000);
        $result = EdgeCaseDetectionResult::withFlags([$approvalFlag, $reviewFlag]);

        $this->assertTrue($result->requiresApproval());
        $this->assertTrue($result->hasReviewOnlyFlags());
        $this->assertCount(2, $result->flags());
        $this->assertCount(1, $result->approvalRequiredFlags());
        $this->assertCount(1, $result->reviewOnlyFlags());
    }

    public function test_merge_combines_results(): void
    {
        $result1 = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::largeAmount(5_000_000, 1_000_000)]);
        $result2 = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::backdated('2024-11-01', 56)]);

        $merged = $result1->merge($result2);

        $this->assertCount(2, $merged->flags());
        $this->assertTrue($merged->requiresApproval());
    }

    public function test_suggested_approval_type_for_high_value(): void
    {
        $result = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::largeAmount(5_000_000, 1_000_000)]);

        $this->assertSame('high_value', $result->suggestedApprovalType());
    }

    public function test_suggested_approval_type_for_negative_balance(): void
    {
        $result = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::negativeBalance('Cash', 1000, -500)]);

        $this->assertSame('negative_equity', $result->suggestedApprovalType());
    }

    public function test_serializes_to_array(): void
    {
        $result = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::largeAmount(5_000_000, 1_000_000)]);
        $array = $result->toArray();

        $this->assertArrayHasKey('has_flags', $array);
        $this->assertArrayHasKey('requires_approval', $array);
        $this->assertArrayHasKey('flags', $array);
        $this->assertArrayHasKey('suggested_approval_type', $array);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/EdgeCaseDetectionResultTest.php -v`
Expected: FAIL with "Class 'EdgeCaseDetectionResult' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\ValueObject;

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
        return array_filter($this->flags, fn(EdgeCaseFlag $flag) => $flag->requiresApproval());
    }

    /**
     * @return EdgeCaseFlag[]
     */
    public function reviewOnlyFlags(): array
    {
        return array_filter($this->flags, fn(EdgeCaseFlag $flag) => $flag->isReviewOnly());
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
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/EdgeCaseDetectionResultTest.php -v`
Expected: OK (9 tests, 22 assertions)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/ValueObject/EdgeCaseDetectionResult.php tests/Unit/Domain/Transaction/ValueObject/EdgeCaseDetectionResultTest.php
git commit -m "feat(domain): add EdgeCaseDetectionResult for aggregating flags"
```

---

### Task 3A.6: Create EdgeCaseDetectionServiceInterface

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetectionServiceInterface.php`

**Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service;

use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use DateTimeImmutable;

/**
 * Detects edge cases in transaction data that require approval routing.
 *
 * Edge cases are NOT hard blocks - they allow the transaction to proceed
 * but route it through the approval workflow for human review.
 *
 * This service runs AFTER TransactionValidationService (hard blocks) passes.
 */
interface EdgeCaseDetectionServiceInterface
{
    /**
     * Detect all applicable edge cases for a transaction.
     *
     * @param array $lines Transaction line data with account_id, debit_cents, credit_cents
     * @param DateTimeImmutable $transactionDate The transaction date
     * @param string $description Transaction description
     * @param CompanyId $companyId Company ID for threshold lookups
     * @param EdgeCaseThresholds|null $thresholds Override thresholds (for testing)
     * @return EdgeCaseDetectionResult Aggregated detection results
     */
    public function detect(
        array $lines,
        DateTimeImmutable $transactionDate,
        string $description,
        CompanyId $companyId,
        ?EdgeCaseThresholds $thresholds = null,
    ): EdgeCaseDetectionResult;
}
```

**Step 2: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetectionServiceInterface.php
git commit -m "feat(domain): add EdgeCaseDetectionServiceInterface"
```

---

### Task 3A.7: Create ThresholdRepositoryInterface

**Files:**
- Create: `src/Domain/Transaction/Repository/ThresholdRepositoryInterface.php`

**Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Repository;

use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Repository for fetching company-specific edge case thresholds.
 */
interface ThresholdRepositoryInterface
{
    /**
     * Get thresholds for a company, returns defaults if not configured.
     */
    public function getForCompany(CompanyId $companyId): EdgeCaseThresholds;
}
```

**Step 2: Commit**

```bash
git add src/Domain/Transaction/Repository/ThresholdRepositoryInterface.php
git commit -m "feat(domain): add ThresholdRepositoryInterface"
```

---

### Task 3A.8: Implement MysqlThresholdRepository

**Files:**
- Create: `src/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepository.php`
- Test: `tests/Unit/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepositoryTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Mysql\Repository;

use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use App\Infrastructure\Persistence\Mysql\Repository\MysqlThresholdRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class MysqlThresholdRepositoryTest extends TestCase
{
    public function test_returns_defaults_when_no_settings_exist(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
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
        $stmt = $this->createMock(\PDOStatement::class);
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
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepositoryTest.php -v`
Expected: FAIL with "Class 'MysqlThresholdRepository' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mysql\Repository;

use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Transaction\Repository\ThresholdRepositoryInterface;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PDO;

final readonly class MysqlThresholdRepository implements ThresholdRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function getForCompany(CompanyId $companyId): EdgeCaseThresholds
    {
        $sql = <<<SQL
            SELECT
                large_transaction_threshold_cents,
                backdated_days_threshold,
                future_dated_allowed,
                require_approval_contra_entry,
                require_approval_equity_adjustment,
                require_approval_negative_balance,
                flag_round_numbers,
                flag_period_end_entries,
                dormant_account_days_threshold
            FROM company_settings
            WHERE company_id = :company_id
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['company_id' => $companyId->toString()]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return EdgeCaseThresholds::defaults();
        }

        return EdgeCaseThresholds::fromDatabaseRow($row);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepositoryTest.php -v`
Expected: OK (2 tests, 6 assertions)

**Step 5: Commit**

```bash
git add src/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepository.php tests/Unit/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepositoryTest.php
git commit -m "feat(infra): implement MysqlThresholdRepository"
```

---

## Phase 3B: High-Priority Edge Case Detectors

**Purpose:** Implement the 5 most critical detection rules that should ship first.

---

### Task 3B.1: Implement Timing Anomaly Detectors (Future/Backdated)

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetector.php`
- Test: `tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetectorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\Transaction\Service\EdgeCaseDetector\TimingAnomalyDetector;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimingAnomalyDetectorTest extends TestCase
{
    private TimingAnomalyDetector $detector;
    private EdgeCaseThresholds $thresholds;

    protected function setUp(): void
    {
        $this->detector = new TimingAnomalyDetector();
        $this->thresholds = EdgeCaseThresholds::defaults();
    }

    public function test_detects_future_dated_transaction(): void
    {
        $today = new DateTimeImmutable('2024-12-27');
        $futureDate = new DateTimeImmutable('2025-01-15');

        $result = $this->detector->detect($futureDate, $today, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('future_dated', $result->flags()[0]->type());
    }

    public function test_allows_today_date(): void
    {
        $today = new DateTimeImmutable('2024-12-27');

        $result = $this->detector->detect($today, $today, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_detects_backdated_beyond_threshold(): void
    {
        $today = new DateTimeImmutable('2024-12-27');
        $backdated = new DateTimeImmutable('2024-11-01'); // 56 days ago

        $result = $this->detector->detect($backdated, $today, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('backdated', $result->flags()[0]->type());
    }

    public function test_allows_backdated_within_threshold(): void
    {
        $today = new DateTimeImmutable('2024-12-27');
        $recentPast = new DateTimeImmutable('2024-12-10'); // 17 days ago

        $result = $this->detector->detect($recentPast, $today, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_respects_custom_backdate_threshold(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'backdated_days_threshold' => 10, // Stricter threshold
            'large_transaction_threshold_cents' => 1_000_000,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        $today = new DateTimeImmutable('2024-12-27');
        $fifteenDaysAgo = new DateTimeImmutable('2024-12-12');

        $result = $this->detector->detect($fifteenDaysAgo, $today, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('backdated', $result->flags()[0]->type());
    }

    public function test_detects_period_end_when_enabled(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'backdated_days_threshold' => 30,
            'large_transaction_threshold_cents' => 1_000_000,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 1, // Enabled
            'dormant_account_days_threshold' => 90,
        ]);

        $yearEnd = new DateTimeImmutable('2024-12-31');

        $result = $this->detector->detect($yearEnd, $yearEnd, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('period_end', $result->flags()[0]->type());
        $this->assertFalse($result->requiresApproval()); // Review only
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetectorTest.php -v`
Expected: FAIL with "Class 'TimingAnomalyDetector' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseFlag;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use DateTimeImmutable;

/**
 * Detects timing-related edge cases:
 * - Future-dated entries (Rule #1)
 * - Backdated entries beyond threshold (Rule #2)
 * - Period-end entries when flagging enabled (Rule #3)
 */
final class TimingAnomalyDetector
{
    public function detect(
        DateTimeImmutable $transactionDate,
        DateTimeImmutable $today,
        EdgeCaseThresholds $thresholds,
    ): EdgeCaseDetectionResult {
        $flags = [];

        // Rule #1: Future-dated
        if ($transactionDate > $today) {
            $flags[] = EdgeCaseFlag::futureDated(
                $transactionDate->format('Y-m-d'),
                $today->format('Y-m-d'),
            );
        }

        // Rule #2: Backdated beyond threshold
        if ($transactionDate < $today) {
            $daysBack = (int) $today->diff($transactionDate)->days;

            if ($daysBack > $thresholds->backdatedDaysThreshold()) {
                $flags[] = EdgeCaseFlag::backdated(
                    $transactionDate->format('Y-m-d'),
                    $daysBack,
                );
            }
        }

        // Rule #3: Period-end entries (optional)
        if ($thresholds->flagPeriodEndEntries()) {
            $periodType = $this->detectPeriodEnd($transactionDate);
            if ($periodType !== null) {
                $flags[] = EdgeCaseFlag::periodEnd(
                    $transactionDate->format('Y-m-d'),
                    $periodType,
                );
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }

    /**
     * Check if date is in last 3 days of month/quarter/year.
     */
    private function detectPeriodEnd(DateTimeImmutable $date): ?string
    {
        $day = (int) $date->format('j');
        $month = (int) $date->format('n');
        $lastDayOfMonth = (int) $date->format('t');

        $daysUntilMonthEnd = $lastDayOfMonth - $day;

        // Last 3 days of year
        if ($month === 12 && $daysUntilMonthEnd <= 2) {
            return 'year';
        }

        // Last 3 days of quarter (March, June, September, December)
        if (in_array($month, [3, 6, 9, 12], true) && $daysUntilMonthEnd <= 2) {
            return 'quarter';
        }

        // Last 3 days of any month
        if ($daysUntilMonthEnd <= 2) {
            return 'month';
        }

        return null;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetectorTest.php -v`
Expected: OK (6 tests)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetector.php tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetectorTest.php
git commit -m "feat(domain): add TimingAnomalyDetector for date edge cases"
```

---

### Task 3B.2: Implement Amount Anomaly Detector

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetector.php`
- Test: `tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetectorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\Transaction\Service\EdgeCaseDetector\AmountAnomalyDetector;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class AmountAnomalyDetectorTest extends TestCase
{
    private AmountAnomalyDetector $detector;
    private EdgeCaseThresholds $thresholds;

    protected function setUp(): void
    {
        $this->detector = new AmountAnomalyDetector();
        $this->thresholds = EdgeCaseThresholds::defaults(); // 1,000,000 cents = $10,000
    }

    public function test_detects_large_transaction(): void
    {
        // Total amount = $15,000 (exceeds $10,000 threshold)
        $lines = [
            ['debit_cents' => 1_500_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_500_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('large_amount', $result->flags()[0]->type());
    }

    public function test_allows_transaction_below_threshold(): void
    {
        // Total amount = $5,000 (below $10,000 threshold)
        $lines = [
            ['debit_cents' => 500_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 500_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        // Should not flag as large_amount
        $largeFlagsCount = count(array_filter(
            $result->flags(),
            fn($f) => $f->type() === 'large_amount'
        ));
        $this->assertSame(0, $largeFlagsCount);
    }

    public function test_detects_just_below_threshold(): void
    {
        // Total amount = $9,999 (90-99% of threshold)
        $lines = [
            ['debit_cents' => 999_900, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 999_900],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('below_threshold', $result->flags()[0]->type());
        $this->assertFalse($result->requiresApproval()); // Review only
    }

    public function test_detects_round_number_when_enabled(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 1, // Enabled
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        // Exactly $10,000.00 - suspiciously round
        $lines = [
            ['debit_cents' => 1_000_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_000_000],
        ];

        $result = $this->detector->detect($lines, $thresholds);

        $roundFlags = array_filter($result->flags(), fn($f) => $f->type() === 'round_number');
        $this->assertNotEmpty($roundFlags);
    }

    public function test_ignores_round_numbers_when_disabled(): void
    {
        // Default thresholds have flag_round_numbers = false
        $lines = [
            ['debit_cents' => 1_000_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_000_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $roundFlags = array_filter($result->flags(), fn($f) => $f->type() === 'round_number');
        $this->assertEmpty($roundFlags);
    }

    public function test_calculates_total_from_debits(): void
    {
        // Multiple debit lines totaling $12,000
        $lines = [
            ['debit_cents' => 600_000, 'credit_cents' => 0],
            ['debit_cents' => 600_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_200_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->requiresApproval());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetectorTest.php -v`
Expected: FAIL with "Class 'AmountAnomalyDetector' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseFlag;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects amount-related edge cases:
 * - Large transaction exceeding threshold (Rule #4)
 * - Round number amounts (Rule #5)
 * - Just below approval threshold (Rule #6)
 */
final class AmountAnomalyDetector
{
    /**
     * @param array<array{debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(array $lines, EdgeCaseThresholds $thresholds): EdgeCaseDetectionResult
    {
        $flags = [];

        $totalDebitsCents = 0;
        foreach ($lines as $line) {
            $totalDebitsCents += $line['debit_cents'] ?? 0;
        }

        $threshold = $thresholds->largeTransactionThresholdCents();

        // Rule #4: Large transaction
        if ($threshold > 0 && $totalDebitsCents > $threshold) {
            $flags[] = EdgeCaseFlag::largeAmount($totalDebitsCents, $threshold);
        }

        // Rule #6: Just below threshold (90-99%)
        if ($threshold > 0 && $totalDebitsCents < $threshold) {
            $floor = $thresholds->belowThresholdFloorCents();
            if ($totalDebitsCents >= $floor) {
                $flags[] = EdgeCaseFlag::belowThreshold($totalDebitsCents, $threshold);
            }
        }

        // Rule #5: Round number (optional)
        if ($thresholds->flagRoundNumbers() && $this->isRoundNumber($totalDebitsCents)) {
            $flags[] = EdgeCaseFlag::roundNumber($totalDebitsCents);
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }

    /**
     * Check if amount is suspiciously round (divisible by $1000 and > $5000).
     */
    private function isRoundNumber(int $amountCents): bool
    {
        // Must be at least $5,000
        if ($amountCents < 500_000) {
            return false;
        }

        // Divisible by $1,000 (100,000 cents)
        return $amountCents % 100_000 === 0;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetectorTest.php -v`
Expected: OK (6 tests)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetector.php tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetectorTest.php
git commit -m "feat(domain): add AmountAnomalyDetector for value edge cases"
```

---

### Task 3B.3: Implement Account Type Anomaly Detector (Contra Entries)

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetector.php`
- Test: `tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetectorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\ChartOfAccounts\Entity\Account;
use App\Domain\ChartOfAccounts\ValueObject\AccountCode;
use App\Domain\ChartOfAccounts\ValueObject\AccountId;
use App\Domain\ChartOfAccounts\ValueObject\AccountType;
use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transaction\Service\EdgeCaseDetector\AccountTypeAnomalyDetector;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
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

    public function test_detects_asset_writedown_credit_to_asset(): void
    {
        $assetAccount = $this->createAccount(1500, 'Equipment', AccountType::ASSET);

        $lines = [
            [
                'account' => $assetAccount,
                'debit_cents' => 0,
                'credit_cents' => 200_000,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('asset_writedown', $result->flags()[0]->type());
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

    public function test_detects_liability_reduction(): void
    {
        $liabilityAccount = $this->createAccount(2100, 'Accounts Payable', AccountType::LIABILITY);

        $lines = [
            [
                'account' => $liabilityAccount,
                'debit_cents' => 150_000,
                'credit_cents' => 0,
            ],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('liability_reduction', $result->flags()[0]->type());
        $this->assertFalse($result->requiresApproval()); // Review only
    }

    public function test_respects_disabled_contra_entry_detection(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 0, // Disabled
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

        $this->assertFalse($result->hasFlags());
    }

    private function createAccount(int $code, string $name, AccountType $type): Account
    {
        return Account::create(
            AccountId::generate(),
            $this->companyId,
            AccountCode::fromInt($code),
            $name,
            $type,
            Money::zero('USD'),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetectorTest.php -v`
Expected: FAIL with "Class 'AccountTypeAnomalyDetector' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\ChartOfAccounts\Entity\Account;
use App\Domain\ChartOfAccounts\ValueObject\AccountType;
use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseFlag;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects account type anomalies (contra entries):
 * - Debit to Revenue account (Rule #7)
 * - Credit to Expense account (Rule #8)
 * - Credit to Asset account (Rule #9)
 * - Debit to Liability account (Rule #10)
 * - Any entry to Equity account (Rule #11)
 */
final class AccountTypeAnomalyDetector
{
    /**
     * @param array<array{account: Account, debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(array $lines, EdgeCaseThresholds $thresholds): EdgeCaseDetectionResult
    {
        $flags = [];

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = $line['account'];
            $debitCents = $line['debit_cents'] ?? 0;
            $creditCents = $line['credit_cents'] ?? 0;
            $accountType = $account->accountType();
            $accountName = $account->name();

            // Rule #7: Contra Revenue (debit to revenue)
            if ($thresholds->requireApprovalContraEntry()) {
                if ($accountType === AccountType::REVENUE && $debitCents > 0) {
                    $flags[] = EdgeCaseFlag::contraRevenue($accountName, $debitCents);
                }

                // Rule #8: Contra Expense (credit to expense)
                if ($accountType === AccountType::EXPENSE && $creditCents > 0) {
                    $flags[] = EdgeCaseFlag::contraExpense($accountName, $creditCents);
                }

                // Rule #9: Asset Write-down (credit to asset)
                if ($accountType === AccountType::ASSET && $creditCents > 0) {
                    $flags[] = EdgeCaseFlag::assetWritedown($accountName, $creditCents);
                }
            }

            // Rule #10: Liability Reduction (debit to liability) - review only
            if ($accountType === AccountType::LIABILITY && $debitCents > 0) {
                $flags[] = EdgeCaseFlag::liabilityReduction($accountName, $debitCents);
            }

            // Rule #11: Equity Adjustment (any entry to equity)
            if ($thresholds->requireApprovalEquityAdjustment()) {
                if ($accountType === AccountType::EQUITY) {
                    $lineType = $debitCents > 0 ? 'debit' : 'credit';
                    $amount = $debitCents > 0 ? $debitCents : $creditCents;
                    if ($amount > 0) {
                        $flags[] = EdgeCaseFlag::equityAdjustment($accountName, $amount, $lineType);
                    }
                }
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetectorTest.php -v`
Expected: OK (7 tests)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetector.php tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetectorTest.php
git commit -m "feat(domain): add AccountTypeAnomalyDetector for contra entries"
```

---

### Task 3B.4: Implement Documentation Anomaly Detector

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetector.php`
- Test: `tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetectorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\Transaction\Service\EdgeCaseDetector\DocumentationAnomalyDetector;
use PHPUnit\Framework\TestCase;

final class DocumentationAnomalyDetectorTest extends TestCase
{
    private DocumentationAnomalyDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DocumentationAnomalyDetector();
    }

    public function test_detects_empty_description(): void
    {
        $result = $this->detector->detect('');

        $this->assertTrue($result->hasFlags());
        $this->assertSame('missing_description', $result->flags()[0]->type());
    }

    public function test_detects_whitespace_only_description(): void
    {
        $result = $this->detector->detect('   ');

        $this->assertTrue($result->hasFlags());
        $this->assertSame('missing_description', $result->flags()[0]->type());
    }

    public function test_detects_minimal_description(): void
    {
        $result = $this->detector->detect('test');

        $this->assertTrue($result->hasFlags());
        $this->assertSame('missing_description', $result->flags()[0]->type());
    }

    public function test_allows_adequate_description(): void
    {
        $result = $this->detector->detect('Payment for office supplies from Staples');

        $this->assertFalse($result->hasFlags());
    }

    public function test_allows_exactly_minimum_length(): void
    {
        $result = $this->detector->detect('12345'); // 5 chars

        $this->assertFalse($result->hasFlags());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetectorTest.php -v`
Expected: FAIL with "Class 'DocumentationAnomalyDetector' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseFlag;

/**
 * Detects documentation anomalies:
 * - Missing or minimal description (Rule #18)
 */
final class DocumentationAnomalyDetector
{
    private const MIN_DESCRIPTION_LENGTH = 5;

    public function detect(string $description): EdgeCaseDetectionResult
    {
        $trimmed = trim($description);

        if (mb_strlen($trimmed) < self::MIN_DESCRIPTION_LENGTH) {
            return EdgeCaseDetectionResult::withFlags([
                EdgeCaseFlag::missingDescription(),
            ]);
        }

        return EdgeCaseDetectionResult::clean();
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetectorTest.php -v`
Expected: OK (5 tests)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetector.php tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetectorTest.php
git commit -m "feat(domain): add DocumentationAnomalyDetector"
```

---

## Phase 3C: Medium-Priority Edge Case Detectors

**Purpose:** Implement balance impact detection (negative balance, unusual changes).

---

### Task 3C.1: Implement Balance Impact Detector

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetector.php`
- Test: `tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetectorTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\ChartOfAccounts\Entity\Account;
use App\Domain\ChartOfAccounts\ValueObject\AccountCode;
use App\Domain\ChartOfAccounts\ValueObject\AccountId;
use App\Domain\ChartOfAccounts\ValueObject\AccountType;
use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Ledger\Repository\LedgerRepositoryInterface;
use App\Domain\Ledger\Service\BalanceCalculationService;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transaction\Service\EdgeCaseDetector\BalanceImpactDetector;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
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
        $cashAccount = $this->createAccount(1000, 'Cash', AccountType::ASSET);

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
        $cashAccount = $this->createAccount(1000, 'Cash', AccountType::ASSET);

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

    public function test_allows_negative_equity_with_flag(): void
    {
        $equityAccount = $this->createAccount(3100, 'Retained Earnings', AccountType::EQUITY);

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

        // Should still flag for review even though equity CAN go negative
        $this->assertTrue($result->hasFlags());
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

        $cashAccount = $this->createAccount(1000, 'Cash', AccountType::ASSET);

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

    private function createAccount(int $code, string $name, AccountType $type): Account
    {
        return Account::create(
            AccountId::generate(),
            $this->companyId,
            AccountCode::fromInt($code),
            $name,
            $type,
            Money::zero('USD'),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetectorTest.php -v`
Expected: FAIL with "Class 'BalanceImpactDetector' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service\EdgeCaseDetector;

use App\Domain\ChartOfAccounts\Entity\Account;
use App\Domain\ChartOfAccounts\ValueObject\AccountType;
use App\Domain\ChartOfAccounts\ValueObject\NormalBalance;
use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Ledger\Repository\LedgerRepositoryInterface;
use App\Domain\Ledger\Service\BalanceCalculationService;
use App\Domain\Ledger\ValueObject\LineType;
use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseFlag;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects balance impact anomalies:
 * - Transaction would cause negative balance (Rule #12)
 * - Transaction would cause negative equity (Rule #13)
 * - Unusual balance change > 50% (Rule #14)
 */
final readonly class BalanceImpactDetector
{
    public function __construct(
        private LedgerRepositoryInterface $ledgerRepository,
        private BalanceCalculationService $balanceCalculator,
    ) {
    }

    /**
     * @param array<array{account: Account, debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(
        array $lines,
        CompanyId $companyId,
        EdgeCaseThresholds $thresholds,
    ): EdgeCaseDetectionResult {
        if (!$thresholds->requireApprovalNegativeBalance()) {
            return EdgeCaseDetectionResult::clean();
        }

        $flags = [];

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = $line['account'];
            $debitCents = $line['debit_cents'] ?? 0;
            $creditCents = $line['credit_cents'] ?? 0;

            // Get current balance
            $currentBalanceCents = $this->ledgerRepository->getBalanceCents(
                $companyId,
                $account->id(),
            );

            // Calculate projected balance change
            $normalBalance = $account->accountType()->normalBalance();
            $lineType = $debitCents > 0 ? LineType::DEBIT : LineType::CREDIT;
            $amount = $debitCents > 0 ? $debitCents : $creditCents;

            $changeCents = $this->balanceCalculator->calculateChange(
                $normalBalance,
                $lineType,
                $amount,
            );

            $projectedBalanceCents = $currentBalanceCents + $changeCents;

            // Rule #12 & #13: Negative balance detection
            if ($projectedBalanceCents < 0) {
                $flags[] = EdgeCaseFlag::negativeBalance(
                    $account->name(),
                    $currentBalanceCents,
                    $projectedBalanceCents,
                );
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetectorTest.php -v`
Expected: OK (4 tests)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetector.php tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetectorTest.php
git commit -m "feat(domain): add BalanceImpactDetector for negative balance detection"
```

---

## Phase 3D: Integration - Orchestrating Service

**Purpose:** Wire all detectors together and integrate with existing transaction flow.

---

### Task 3D.1: Implement EdgeCaseDetectionService

**Files:**
- Create: `src/Domain/Transaction/Service/EdgeCaseDetectionService.php`
- Test: `tests/Unit/Domain/Transaction/Service/EdgeCaseDetectionServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service;

use App\Domain\ChartOfAccounts\Entity\Account;
use App\Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use App\Domain\ChartOfAccounts\ValueObject\AccountCode;
use App\Domain\ChartOfAccounts\ValueObject\AccountId;
use App\Domain\ChartOfAccounts\ValueObject\AccountType;
use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Ledger\Repository\LedgerRepositoryInterface;
use App\Domain\Ledger\Service\BalanceCalculationService;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transaction\Repository\ThresholdRepositoryInterface;
use App\Domain\Transaction\Service\EdgeCaseDetectionService;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use DateTimeImmutable;
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

    public function test_detects_multiple_edge_cases(): void
    {
        $revenueAccount = $this->createAccount(4100, 'Sales Revenue', AccountType::REVENUE);

        $this->accountRepository->method('findById')
            ->willReturn($revenueAccount);

        // Future date + debit to revenue (2 flags)
        $lines = [
            ['account_id' => $revenueAccount->id()->toString(), 'debit_cents' => 100_000, 'credit_cents' => 0],
            ['account_id' => $revenueAccount->id()->toString(), 'debit_cents' => 0, 'credit_cents' => 100_000],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable('+1 week'),
            'Test',
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());

        $flagTypes = array_map(fn($f) => $f->type(), $result->flags());
        $this->assertContains('future_dated', $flagTypes);
        $this->assertContains('contra_revenue', $flagTypes);
    }

    public function test_returns_clean_for_normal_transaction(): void
    {
        $assetAccount = $this->createAccount(1000, 'Cash', AccountType::ASSET);
        $expenseAccount = $this->createAccount(5100, 'Rent', AccountType::EXPENSE);

        $this->accountRepository->method('findById')
            ->willReturnCallback(function ($id) use ($assetAccount, $expenseAccount) {
                return $id->toString() === $assetAccount->id()->toString()
                    ? $assetAccount
                    : $expenseAccount;
            });

        // Normal transaction: Debit Expense, Credit Asset
        $lines = [
            ['account_id' => $expenseAccount->id()->toString(), 'debit_cents' => 50_000, 'credit_cents' => 0],
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 0, 'credit_cents' => 50_000],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable(),
            'Payment for rent',
            $this->companyId,
        );

        $this->assertFalse($result->requiresApproval());
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

        $assetAccount = $this->createAccount(1000, 'Cash', AccountType::ASSET);

        $this->accountRepository->method('findById')
            ->willReturn($assetAccount);

        // $2,000 transaction exceeds $1,000 threshold
        $lines = [
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 200_000, 'credit_cents' => 0],
            ['account_id' => $assetAccount->id()->toString(), 'debit_cents' => 0, 'credit_cents' => 200_000],
        ];

        $result = $this->service->detect(
            $lines,
            new DateTimeImmutable(),
            'Large deposit',
            $this->companyId,
            $customThresholds,
        );

        $this->assertTrue($result->requiresApproval());
        $this->assertSame('large_amount', $result->flags()[0]->type());
    }

    private function createAccount(int $code, string $name, AccountType $type): Account
    {
        return Account::create(
            AccountId::generate(),
            $this->companyId,
            AccountCode::fromInt($code),
            $name,
            $type,
            Money::zero('USD'),
        );
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetectionServiceTest.php -v`
Expected: FAIL with "Class 'EdgeCaseDetectionService' not found"

**Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Service;

use App\Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use App\Domain\ChartOfAccounts\ValueObject\AccountId;
use App\Domain\Company\ValueObject\CompanyId;
use App\Domain\Ledger\Repository\LedgerRepositoryInterface;
use App\Domain\Ledger\Service\BalanceCalculationService;
use App\Domain\Transaction\Repository\ThresholdRepositoryInterface;
use App\Domain\Transaction\Service\EdgeCaseDetector\AccountTypeAnomalyDetector;
use App\Domain\Transaction\Service\EdgeCaseDetector\AmountAnomalyDetector;
use App\Domain\Transaction\Service\EdgeCaseDetector\BalanceImpactDetector;
use App\Domain\Transaction\Service\EdgeCaseDetector\DocumentationAnomalyDetector;
use App\Domain\Transaction\Service\EdgeCaseDetector\TimingAnomalyDetector;
use App\Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use App\Domain\Transaction\ValueObject\EdgeCaseThresholds;
use DateTimeImmutable;

/**
 * Orchestrates all edge case detectors.
 * Runs AFTER TransactionValidationService (hard blocks) passes.
 * Returns aggregated flags for approval routing decision.
 */
final readonly class EdgeCaseDetectionService implements EdgeCaseDetectionServiceInterface
{
    private TimingAnomalyDetector $timingDetector;
    private AmountAnomalyDetector $amountDetector;
    private AccountTypeAnomalyDetector $accountTypeDetector;
    private DocumentationAnomalyDetector $documentationDetector;
    private BalanceImpactDetector $balanceImpactDetector;

    public function __construct(
        private ThresholdRepositoryInterface $thresholdRepository,
        private AccountRepositoryInterface $accountRepository,
        private LedgerRepositoryInterface $ledgerRepository,
        private BalanceCalculationService $balanceCalculator,
    ) {
        $this->timingDetector = new TimingAnomalyDetector();
        $this->amountDetector = new AmountAnomalyDetector();
        $this->accountTypeDetector = new AccountTypeAnomalyDetector();
        $this->documentationDetector = new DocumentationAnomalyDetector();
        $this->balanceImpactDetector = new BalanceImpactDetector(
            $this->ledgerRepository,
            $this->balanceCalculator,
        );
    }

    public function detect(
        array $lines,
        DateTimeImmutable $transactionDate,
        string $description,
        CompanyId $companyId,
        ?EdgeCaseThresholds $thresholds = null,
    ): EdgeCaseDetectionResult {
        $thresholds ??= $this->thresholdRepository->getForCompany($companyId);
        $today = new DateTimeImmutable('today');

        // Hydrate account entities for lines that need them
        $hydratedLines = $this->hydrateAccountsForLines($lines);

        // Run all detectors
        $results = [
            $this->timingDetector->detect($transactionDate, $today, $thresholds),
            $this->amountDetector->detect($lines, $thresholds),
            $this->accountTypeDetector->detect($hydratedLines, $thresholds),
            $this->documentationDetector->detect($description),
            $this->balanceImpactDetector->detect($hydratedLines, $companyId, $thresholds),
        ];

        // Merge all results
        $merged = EdgeCaseDetectionResult::clean();
        foreach ($results as $result) {
            $merged = $merged->merge($result);
        }

        return $merged;
    }

    /**
     * @param array<array{account_id: string, debit_cents: int, credit_cents: int}> $lines
     * @return array<array{account: \App\Domain\ChartOfAccounts\Entity\Account, debit_cents: int, credit_cents: int}>
     */
    private function hydrateAccountsForLines(array $lines): array
    {
        $hydrated = [];

        foreach ($lines as $line) {
            $accountId = AccountId::fromString($line['account_id']);
            $account = $this->accountRepository->findById($accountId);

            if ($account !== null) {
                $hydrated[] = [
                    'account' => $account,
                    'debit_cents' => $line['debit_cents'] ?? 0,
                    'credit_cents' => $line['credit_cents'] ?? 0,
                ];
            }
        }

        return $hydrated;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetectionServiceTest.php -v`
Expected: OK (3 tests)

**Step 5: Commit**

```bash
git add src/Domain/Transaction/Service/EdgeCaseDetectionService.php tests/Unit/Domain/Transaction/Service/EdgeCaseDetectionServiceTest.php
git commit -m "feat(domain): implement EdgeCaseDetectionService orchestrator"
```

---

### Task 3D.2: Integrate with CreateTransactionHandler

**Files:**
- Modify: `src/Application/Handler/Transaction/CreateTransactionHandler.php`

**CRITICAL: This is where edge case detection plugs into existing flow**

**Step 1: Read current handler**

Read the file to understand current structure before modifying.

**Step 2: Add EdgeCaseDetectionService injection**

Add to constructor:
```php
private readonly EdgeCaseDetectionServiceInterface $edgeCaseDetectionService,
```

**Step 3: Add detection after hard-block validation passes**

Find the section after `$validationResult = $this->transactionValidationService->validate(...)` and add:

```php
// Edge case detection (runs after hard blocks pass)
$edgeCaseResult = $this->edgeCaseDetectionService->detect(
    $command->lines(),
    $command->transactionDate(),
    $command->description(),
    $command->companyId(),
);

// If approval required, create pending transaction with approval request
if ($edgeCaseResult->requiresApproval()) {
    return $this->createPendingWithApproval($command, $edgeCaseResult);
}
```

**Step 4: Implement createPendingWithApproval method**

```php
private function createPendingWithApproval(
    CreateTransactionCommand $command,
    EdgeCaseDetectionResult $edgeCaseResult,
): Transaction {
    // Create transaction in PENDING status (not DRAFT)
    $transaction = Transaction::create(
        id: TransactionId::generate(),
        companyId: $command->companyId(),
        transactionDate: $command->transactionDate(),
        description: $command->description(),
        referenceNumber: $command->referenceNumber(),
        currency: $command->currency(),
        createdBy: $command->userId(),
    );

    // Add lines
    foreach ($command->lines() as $lineData) {
        $transaction->addLine(/* ... */);
    }

    // Create approval request
    $approvalType = ApprovalType::from($edgeCaseResult->suggestedApprovalType());
    $reason = ApprovalReason::fromEdgeCaseFlags($edgeCaseResult->flags());

    $approval = Approval::request(new CreateApprovalRequest(
        companyId: $command->companyId(),
        approvalType: $approvalType,
        entityType: 'transaction',
        entityId: $transaction->id()->toString(),
        reason: $reason,
        requestedBy: $command->userId(),
        amountCents: $this->calculateTotalAmount($command->lines()),
        priority: $approvalType->defaultPriority(),
    ));

    $this->transactionRepository->save($transaction);
    $this->approvalRepository->save($approval);

    return $transaction;
}
```

**Step 5: Update DI container registration**

In `src/Infrastructure/DependencyInjection/services.php`, add:

```php
EdgeCaseDetectionServiceInterface::class => function (ContainerInterface $c) {
    return new EdgeCaseDetectionService(
        $c->get(ThresholdRepositoryInterface::class),
        $c->get(AccountRepositoryInterface::class),
        $c->get(LedgerRepositoryInterface::class),
        $c->get(BalanceCalculationService::class),
    );
},

ThresholdRepositoryInterface::class => function (ContainerInterface $c) {
    return new MysqlThresholdRepository($c->get(PDO::class));
},
```

**Step 6: Commit**

```bash
git add src/Application/Handler/Transaction/CreateTransactionHandler.php src/Infrastructure/DependencyInjection/services.php
git commit -m "feat(app): integrate EdgeCaseDetectionService into transaction creation"
```

---

### Task 3D.3: Add New Approval Types

**Files:**
- Modify: `src/Domain/Approval/ValueObject/ApprovalType.php`
- Modify: `src/Domain/Approval/ValueObject/ApprovalReason.php`

**Step 1: Add new approval type cases**

Add to ApprovalType enum:
```php
case CONTRA_ENTRY = 'contra_entry';
case ASSET_WRITEDOWN = 'asset_writedown';
case FUTURE_DATED = 'future_dated';
```

**Step 2: Update defaultPriority() and expirationHours() methods**

```php
public function defaultPriority(): int
{
    return match ($this) {
        self::VOID_TRANSACTION => 1,
        self::NEGATIVE_EQUITY, self::HIGH_VALUE, self::ASSET_WRITEDOWN => 2,
        self::TRANSACTION, self::BACKDATED_TRANSACTION, self::FUTURE_DATED,
        self::CONTRA_ENTRY, self::TRANSACTION_POSTING, self::TRANSACTION_APPROVAL => 3,
        self::USER_REGISTRATION, self::ACCOUNT_DEACTIVATION => 4,
        self::PERIOD_CLOSE => 2,
    };
}
```

**Step 3: Add ApprovalReason factory for edge case flags**

```php
public static function fromEdgeCaseFlags(array $flags): self
{
    $descriptions = array_map(fn($f) => $f->description(), $flags);
    $types = array_map(fn($f) => $f->type(), $flags);

    return new self(
        sprintf('Edge case flags: %s', implode('; ', $descriptions)),
        [
            'flag_types' => $types,
            'flag_count' => count($flags),
        ],
    );
}
```

**Step 4: Commit**

```bash
git add src/Domain/Approval/ValueObject/ApprovalType.php src/Domain/Approval/ValueObject/ApprovalReason.php
git commit -m "feat(domain): add approval types for edge case routing"
```

---

## Phase 3E: API Response Updates

**Purpose:** Expose edge case flags in API responses for frontend awareness.

---

### Task 3E.1: Update Transaction Validation Endpoint

**Files:**
- Modify: `src/Api/Controller/TransactionValidationController.php`

**Step 1: Inject EdgeCaseDetectionService**

**Step 2: Add edge case detection to validate response**

```php
public function validate(ServerRequestInterface $request): ResponseInterface
{
    // ... existing validation logic ...

    // Add edge case detection
    $edgeCaseResult = $this->edgeCaseDetectionService->detect(
        $lines,
        $transactionDate,
        $description,
        $companyId,
    );

    return $this->json([
        'valid' => $validationResult->isValid(),
        'errors' => $validationResult->errors(),
        'edge_cases' => $edgeCaseResult->toArray(),
    ]);
}
```

**Step 3: Commit**

```bash
git add src/Api/Controller/TransactionValidationController.php
git commit -m "feat(api): add edge case detection to validation endpoint"
```

---

## Validation Flow Summary

```
Transaction Submit (API)
       │
       ▼
┌─────────────────────────┐
│  REQUEST VALIDATION     │ ← TransactionValidation (API layer)
│  (format, types)        │
└─────────┬───────────────┘
          │ pass
          ▼
┌─────────────────────────┐
│  HARD BLOCK CHECK       │ ← TransactionValidationService (existing)
│  (8 rules)              │    - Balance check
│                         │    - Duplicate accounts
│                         │    - Zero amounts, etc.
└─────────┬───────────────┘
          │ pass
          ▼
┌─────────────────────────┐
│  EDGE CASE DETECTION    │ ← EdgeCaseDetectionService (NEW)
│  (19 rules)             │    - TimingAnomalyDetector
│                         │    - AmountAnomalyDetector
│                         │    - AccountTypeAnomalyDetector
│                         │    - DocumentationAnomalyDetector
│                         │    - BalanceImpactDetector
└─────────┬───────────────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
 CLEAN      FLAGGED
    │           │
    ▼           ▼
 POST        CREATE
 NORMALLY    APPROVAL
             REQUEST
```

---

## File Inventory

### New Files (Create)
| Path | Purpose |
|------|---------|
| `docker/mysql/migrations/002_edge_case_thresholds.sql` | DB migration |
| `src/Domain/Transaction/ValueObject/EdgeCaseThresholds.php` | Threshold config |
| `src/Domain/Transaction/ValueObject/EdgeCaseFlag.php` | Individual flag |
| `src/Domain/Transaction/ValueObject/EdgeCaseDetectionResult.php` | Aggregated result |
| `src/Domain/Transaction/Service/EdgeCaseDetectionServiceInterface.php` | Interface |
| `src/Domain/Transaction/Service/EdgeCaseDetectionService.php` | Orchestrator |
| `src/Domain/Transaction/Repository/ThresholdRepositoryInterface.php` | Threshold repo interface |
| `src/Infrastructure/Persistence/Mysql/Repository/MysqlThresholdRepository.php` | Threshold repo impl |
| `src/Domain/Transaction/Service/EdgeCaseDetector/TimingAnomalyDetector.php` | Rules 1-3 |
| `src/Domain/Transaction/Service/EdgeCaseDetector/AmountAnomalyDetector.php` | Rules 4-6 |
| `src/Domain/Transaction/Service/EdgeCaseDetector/AccountTypeAnomalyDetector.php` | Rules 7-11 |
| `src/Domain/Transaction/Service/EdgeCaseDetector/DocumentationAnomalyDetector.php` | Rules 18-19 |
| `src/Domain/Transaction/Service/EdgeCaseDetector/BalanceImpactDetector.php` | Rules 12-14 |

### Modified Files
| Path | Changes |
|------|---------|
| `docker/mysql/00-fresh-schema.sql` | Add threshold columns |
| `src/Application/Handler/Transaction/CreateTransactionHandler.php` | Inject & use detection service |
| `src/Domain/Approval/ValueObject/ApprovalType.php` | Add new types |
| `src/Domain/Approval/ValueObject/ApprovalReason.php` | Add edge case factory |
| `src/Api/Controller/TransactionValidationController.php` | Add edge case response |
| `src/Infrastructure/DependencyInjection/services.php` | Register new services |

---

## Testing Strategy

Run all edge case tests:
```bash
./vendor/bin/phpunit tests/Unit/Domain/Transaction/ValueObject/ -v
./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetector/ -v
./vendor/bin/phpunit tests/Unit/Domain/Transaction/Service/EdgeCaseDetectionServiceTest.php -v
```

Integration test:
```bash
./vendor/bin/phpunit tests/Integration/Transaction/EdgeCaseDetectionIntegrationTest.php -v
```

---

Plan complete and saved to `docs/plans/2025-12-28-transaction-edge-case-validation.md`.

