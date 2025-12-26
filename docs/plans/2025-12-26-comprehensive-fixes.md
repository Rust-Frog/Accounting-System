# Comprehensive Codebase Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix all 26 identified issues across security, database, code bugs, and quality improvements.

**Architecture:** Prioritized fixes starting with critical security issues, then database schema, then code bugs, then quality improvements. Each task uses TDD where applicable.

**Tech Stack:** PHP 8.2, MySQL 8.0, Redis, PHPUnit, Docker

---

## Phase 1: Critical Security Fixes (Tasks 1-4)

### Task 1: Fix Role Enforcement Regex Escaping

**Files:**
- Modify: `src/Api/Middleware/RoleEnforcementMiddleware.php:119-129`
- Test: `tests/Unit/Api/Middleware/RoleEnforcementMiddlewareTest.php`

**Step 1: Write the failing test**

Create test file if not exists, add test:

```php
public function testMatchesPatternEscapesSpecialCharacters(): void
{
    // Test that special regex characters in route don't break matching
    $middleware = new RoleEnforcementMiddleware([], $this->createMock(UserRepositoryInterface::class));

    // Use reflection to test private method
    $reflection = new \ReflectionClass($middleware);
    $method = $reflection->getMethod('matchesPattern');
    $method->setAccessible(true);

    // Should match literal dots and other special chars
    $this->assertTrue($method->invoke($middleware, 'GET:/api/v1.0/test', 'GET:/api/v1.0/*'));
    $this->assertFalse($method->invoke($middleware, 'GET:/api/v1X0/test', 'GET:/api/v1.0/*'));
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec accounting-app-dev vendor/bin/phpunit tests/Unit/Api/Middleware/RoleEnforcementMiddlewareTest.php --filter testMatchesPatternEscapesSpecialCharacters -v`
Expected: FAIL (method not properly escaping)

**Step 3: Fix the regex escaping**

```php
// In RoleEnforcementMiddleware.php, replace matchesPattern method:
private function matchesPattern(string $routeKey, string $pattern): bool
{
    // First escape all regex special characters
    $escaped = preg_quote($pattern, '/');

    // Then convert our wildcard (*) back to regex pattern
    $regex = '/^' . str_replace('\*', '[^\/]+', $escaped) . '$/';

    return preg_match($regex, $routeKey) === 1;
}
```

**Step 4: Run test to verify it passes**

Run: `docker exec accounting-app-dev vendor/bin/phpunit tests/Unit/Api/Middleware/RoleEnforcementMiddlewareTest.php --filter testMatchesPatternEscapesSpecialCharacters -v`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Api/Middleware/RoleEnforcementMiddleware.php tests/Unit/Api/Middleware/
git commit -m "fix(security): properly escape regex in role enforcement middleware"
```

---

### Task 2: Integrate Audit Logging for Access Denials

**Files:**
- Modify: `src/Api/Middleware/RoleEnforcementMiddleware.php:15-20, 140`
- Modify: `src/Infrastructure/Container/ContainerBuilder.php`

**Step 1: Add AuditChainServiceInterface to constructor**

```php
// In RoleEnforcementMiddleware.php, update constructor:
public function __construct(
    private readonly array $rolePermissions,
    private readonly UserRepositoryInterface $userRepository,
    private readonly ?AuditChainServiceInterface $auditService = null
) {
}
```

**Step 2: Replace error_log with audit service**

```php
// Replace line 140 area:
private function logAccessDenial(string $userId, string $role, string $routeKey): void
{
    $message = sprintf(
        'Access denied: User %s with role %s attempted %s',
        $userId,
        $role,
        $routeKey
    );

    if ($this->auditService !== null) {
        // Log to audit chain for security tracking
        $this->auditService->logSecurityEvent('access_denied', [
            'user_id' => $userId,
            'role' => $role,
            'route' => $routeKey,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    // Keep error_log as fallback for development
    error_log($message);
}
```

**Step 3: Update ContainerBuilder to inject audit service**

```php
// In registerAuthServices, find RoleEnforcementMiddleware registration and add:
$container->singleton(\Api\Middleware\RoleEnforcementMiddleware::class, fn(ContainerInterface $c) =>
    new \Api\Middleware\RoleEnforcementMiddleware(
        [], // Role permissions loaded from config
        $c->get(UserRepositoryInterface::class),
        $c->get(\Domain\Audit\Service\AuditChainServiceInterface::class)
    )
);
```

**Step 4: Run existing tests**

Run: `docker exec accounting-app-dev vendor/bin/phpunit tests/Unit/Api/Middleware/ -v`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Api/Middleware/RoleEnforcementMiddleware.php src/Infrastructure/Container/ContainerBuilder.php
git commit -m "feat(security): integrate audit logging for access denials"
```

---

### Task 3: Create Production Environment Template

**Files:**
- Modify: `docker/.env.production`

**Step 1: Create production env template**

```bash
# =============================================================================
# PRODUCTION Environment Configuration
# =============================================================================
# CRITICAL: Generate all secrets with: openssl rand -base64 32
# NEVER use development defaults in production!
# =============================================================================

# Application
APP_ENV=production
APP_DEBUG=false

# Database (REQUIRED - no defaults)
DB_HOST=your-production-db-host
DB_PORT=3306
DB_DATABASE=accounting_production
DB_USERNAME=
DB_PASSWORD=

# Redis
REDIS_HOST=your-production-redis-host
REDIS_PORT=6379

# JWT (REQUIRED - generate strong secret)
# Generate with: openssl rand -base64 32
JWT_SECRET=
JWT_EXPIRATION=3600
JWT_ISSUER=accounting-api

# Security
BCRYPT_COST=12
SESSION_DURATION_HOURS=8
RATE_LIMIT_MAX_REQUESTS=60
RATE_LIMIT_WINDOW_SECONDS=60
```

**Step 2: Commit**

```bash
git add docker/.env.production
git commit -m "docs(security): add production environment template with security notes"
```

---

### Task 4: Add Security Event Interface to AuditChainService

**Files:**
- Modify: `src/Domain/Audit/Service/AuditChainServiceInterface.php`
- Modify: `src/Infrastructure/Service/AuditChainService.php`

**Step 1: Add logSecurityEvent to interface**

```php
// Add to AuditChainServiceInterface.php:
/**
 * Log a security-related event for audit trail.
 *
 * @param string $eventType Type of security event (access_denied, login_failed, etc)
 * @param array<string, mixed> $context Event context data
 */
public function logSecurityEvent(string $eventType, array $context): void;
```

**Step 2: Implement in AuditChainService**

```php
// Add to AuditChainService.php:
public function logSecurityEvent(string $eventType, array $context): void
{
    $log = ActivityLog::create(
        actorId: $context['user_id'] ?? 'system',
        actorType: 'user',
        action: $eventType,
        entityType: 'security',
        entityId: $context['route'] ?? 'unknown',
        changes: $context,
        context: RequestContext::fromRequest(
            ipAddress: $context['ip'] ?? '0.0.0.0',
            userAgent: $context['user_agent'] ?? 'System',
            requestId: uniqid('sec_', true)
        )
    );

    $this->activityLogRepository->save($log);
}
```

**Step 3: Run tests**

Run: `docker exec accounting-app-dev vendor/bin/phpunit tests/Unit/Infrastructure/Service/ -v`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Domain/Audit/Service/AuditChainServiceInterface.php src/Infrastructure/Service/AuditChainService.php
git commit -m "feat(audit): add security event logging to audit chain service"
```

---

## Phase 2: Database Schema Fixes (Tasks 5-7)

### Task 5: Create account_balances Table Migration

**Files:**
- Create: `docker/mysql/migration_account_balances.sql`

**Step 1: Create migration file**

```sql
-- Migration: Create account_balances table
-- Required for ledger balance tracking
-- Run after init.sql

CREATE TABLE IF NOT EXISTS account_balances (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    company_id CHAR(36) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    opening_balance_cents BIGINT NOT NULL DEFAULT 0,
    current_balance_cents BIGINT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_period (account_id, period_end),
    INDEX idx_company_period (company_id, period_end),
    INDEX idx_account (account_id),

    CONSTRAINT fk_balance_account FOREIGN KEY (account_id)
        REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_balance_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Step 2: Apply migration in container**

Run: `docker exec accounting-mysql-dev mysql -u accounting_user -paccounting_pass accounting_system < /docker-entrypoint-initdb.d/migration_account_balances.sql`

**Step 3: Commit**

```bash
git add docker/mysql/migration_account_balances.sql
git commit -m "feat(db): add account_balances table for ledger tracking"
```

---

### Task 6: Fix Currency Default Alignment

**Files:**
- Modify: `docker/mysql/init.sql:106`

**Step 1: Update accounts table default currency**

```sql
-- Change line 106 from:
currency CHAR(3) NOT NULL DEFAULT 'PHP',
-- To:
currency CHAR(3) NOT NULL DEFAULT 'USD',
```

**Step 2: Commit**

```bash
git add docker/mysql/init.sql
git commit -m "fix(db): align account currency default with company (USD)"
```

---

### Task 7: Add proof_json Column to Approvals

**Files:**
- Create: `docker/mysql/migration_approval_proof.sql`

**Step 1: Create migration**

```sql
-- Migration: Add proof_json to approvals table
-- Required for approval audit proof chain

ALTER TABLE approvals
ADD COLUMN IF NOT EXISTS proof_json JSON NULL DEFAULT NULL
AFTER status;

-- Add index for proof lookups
CREATE INDEX IF NOT EXISTS idx_approvals_proof ON approvals ((CAST(proof_json->>'$.hash' AS CHAR(64))));
```

**Step 2: Commit**

```bash
git add docker/mysql/migration_approval_proof.sql
git commit -m "feat(db): add proof_json column for approval audit chain"
```

---

## Phase 3: Code Bug Fixes (Tasks 8-12)

### Task 8: Fix UpdateTransactionHandler Account Lookup

**Files:**
- Modify: `src/Application/Handler/Transaction/UpdateTransactionHandler.php:115-130`

**Step 1: Add account lookup to buildLineDto method**

```php
// Replace the buildLineDto method or add account lookup:
private function buildLineDto(TransactionLine $line, int $index): TransactionLineDto
{
    $account = $this->accountRepository->findById($line->accountId());

    return new TransactionLineDto(
        id: (string)$index,
        accountId: $line->accountId()->toString(),
        accountCode: $account?->code()->toInt() ?? 0,
        accountName: $account?->name() ?? 'Unknown Account',
        lineType: $line->lineType()->value,
        amountCents: $line->amount()->cents(),
        currency: $line->amount()->currency()->value,
        description: $line->description() ?? '',
        lineOrder: $index,
    );
}
```

**Step 2: Run tests**

Run: `docker exec accounting-app-dev vendor/bin/phpunit tests/Unit/Application/Handler/Transaction/ -v`
Expected: PASS

**Step 3: Commit**

```bash
git add src/Application/Handler/Transaction/UpdateTransactionHandler.php
git commit -m "fix: add account lookup in UpdateTransactionHandler for proper DTO population"
```

---

### Task 9: Fix MysqlLedgerRepository Company ID Placeholder

**Files:**
- Modify: `src/Infrastructure/Persistence/Mysql/Repository/MysqlLedgerRepository.php:125-130`

**Step 1: Get company ID from account**

```php
// In hydrateBalance method, replace placeholder with actual lookup:
private function hydrateBalance(array $row): AccountBalance
{
    $account = $this->accountRepository->findById(
        AccountId::fromString($row['account_id'])
    );

    $companyId = $account !== null
        ? $account->companyId()
        : CompanyId::fromString($row['company_id'] ?? '00000000-0000-0000-0000-000000000000');

    return AccountBalance::reconstitute(
        id: BalanceId::fromString($row['id']),
        accountId: AccountId::fromString($row['account_id']),
        companyId: $companyId,
        // ... rest of hydration
    );
}
```

**Step 2: Add AccountRepositoryInterface to constructor**

```php
public function __construct(
    ?PDO $connection = null,
    private readonly ?AccountRepositoryInterface $accountRepository = null
) {
    parent::__construct($connection);
}
```

**Step 3: Update ContainerBuilder**

```php
$container->singleton(LedgerRepositoryInterface::class, fn(ContainerInterface $c) =>
    new MysqlLedgerRepository(
        $c->get(PDO::class),
        $c->get(AccountRepositoryInterface::class)
    )
);
```

**Step 4: Commit**

```bash
git add src/Infrastructure/Persistence/Mysql/Repository/MysqlLedgerRepository.php src/Infrastructure/Container/ContainerBuilder.php
git commit -m "fix: resolve company ID from account in MysqlLedgerRepository"
```

---

### Task 10: Implement getAllBalances and getBalancesByType

**Files:**
- Modify: `src/Infrastructure/Persistence/Mysql/Repository/MysqlLedgerRepository.php:68-78`

**Step 1: Implement getAllBalances**

```php
public function getAllBalances(CompanyId $companyId): array
{
    $sql = "SELECT ab.* FROM account_balances ab
            INNER JOIN accounts a ON ab.account_id = a.id
            WHERE a.company_id = :company_id
            ORDER BY ab.period_end DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['company_id' => $companyId->toString()]);

    $balances = [];
    while ($row = $stmt->fetch()) {
        $balances[] = $this->hydrateBalance($row);
    }

    return $balances;
}
```

**Step 2: Implement getBalancesByType**

```php
public function getBalancesByType(CompanyId $companyId, AccountType $type): array
{
    $sql = "SELECT ab.* FROM account_balances ab
            INNER JOIN accounts a ON ab.account_id = a.id
            WHERE a.company_id = :company_id AND a.type = :type
            ORDER BY ab.period_end DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
        'company_id' => $companyId->toString(),
        'type' => $type->value,
    ]);

    $balances = [];
    while ($row = $stmt->fetch()) {
        $balances[] = $this->hydrateBalance($row);
    }

    return $balances;
}
```

**Step 3: Commit**

```bash
git add src/Infrastructure/Persistence/Mysql/Repository/MysqlLedgerRepository.php
git commit -m "feat: implement getAllBalances and getBalancesByType in MysqlLedgerRepository"
```

---

### Task 11: Add Hydration Method to JournalEntry

**Files:**
- Modify: `src/Domain/Ledger/Entity/JournalEntry.php`
- Modify: `src/Infrastructure/Persistence/Mysql/Repository/MysqlJournalEntryRepository.php:134-150`

**Step 1: Add static reconstitute method to JournalEntry**

```php
// Add to JournalEntry.php:
public static function reconstitute(
    JournalEntryId $id,
    TransactionId $transactionId,
    CompanyId $companyId,
    DateTimeImmutable $entryDate,
    string $description,
    JournalEntryStatus $status,
    DateTimeImmutable $createdAt,
    ?DateTimeImmutable $postedAt,
    array $lines = []
): self {
    $entry = new self(
        id: $id,
        transactionId: $transactionId,
        companyId: $companyId,
        entryDate: $entryDate,
        description: $description,
        status: $status,
        createdAt: $createdAt
    );

    $entry->postedAt = $postedAt;
    $entry->lines = $lines;

    return $entry;
}
```

**Step 2: Update MysqlJournalEntryRepository to use reconstitute**

```php
// Replace reflection-based hydration:
private function hydrateJournalEntry(array $row): JournalEntry
{
    return JournalEntry::reconstitute(
        id: JournalEntryId::fromString($row['id']),
        transactionId: TransactionId::fromString($row['transaction_id']),
        companyId: CompanyId::fromString($row['company_id']),
        entryDate: new DateTimeImmutable($row['entry_date']),
        description: $row['description'],
        status: JournalEntryStatus::from($row['status']),
        createdAt: new DateTimeImmutable($row['created_at']),
        postedAt: $row['posted_at'] ? new DateTimeImmutable($row['posted_at']) : null
    );
}
```

**Step 3: Commit**

```bash
git add src/Domain/Ledger/Entity/JournalEntry.php src/Infrastructure/Persistence/Mysql/Repository/MysqlJournalEntryRepository.php
git commit -m "refactor: replace reflection with reconstitute method for JournalEntry hydration"
```

---

### Task 12: Add Hydration Method to Transaction

**Files:**
- Modify: `src/Domain/Transaction/Entity/Transaction.php`
- Modify: `src/Infrastructure/Persistence/Mysql/Hydrator/TransactionHydrator.php:30-50`

**Step 1: Add static reconstitute method to Transaction**

```php
// Add to Transaction.php:
public static function reconstitute(
    TransactionId $id,
    CompanyId $companyId,
    DateTimeImmutable $transactionDate,
    string $description,
    UserId $createdBy,
    DateTimeImmutable $createdAt,
    TransactionStatus $status,
    ?string $referenceNumber,
    ?DateTimeImmutable $postedAt = null,
    ?UserId $postedBy = null,
    ?DateTimeImmutable $voidedAt = null,
    ?UserId $voidedBy = null,
    ?string $voidReason = null,
    array $lines = []
): self {
    $transaction = new self(
        id: $id,
        companyId: $companyId,
        transactionDate: $transactionDate,
        description: $description,
        createdBy: $createdBy,
        createdAt: $createdAt,
        status: $status,
        referenceNumber: $referenceNumber
    );

    $transaction->postedAt = $postedAt;
    $transaction->postedBy = $postedBy;
    $transaction->voidedAt = $voidedAt;
    $transaction->voidedBy = $voidedBy;
    $transaction->voidReason = $voidReason;
    $transaction->lines = $lines;

    return $transaction;
}
```

**Step 2: Update TransactionHydrator to use reconstitute**

```php
// Replace reflection-based hydration in TransactionHydrator:
public function hydrate(array $row, array $lines = []): Transaction
{
    $hydratedLines = array_map(
        fn(array $lineRow) => $this->hydrateLine($lineRow),
        $lines
    );

    return Transaction::reconstitute(
        id: TransactionId::fromString($row['id']),
        companyId: CompanyId::fromString($row['company_id']),
        transactionDate: new DateTimeImmutable($row['transaction_date']),
        description: $row['description'],
        createdBy: UserId::fromString($row['created_by']),
        createdAt: new DateTimeImmutable($row['created_at']),
        status: TransactionStatus::from($row['status']),
        referenceNumber: $row['reference_number'] ?? null,
        postedAt: $row['posted_at'] ? new DateTimeImmutable($row['posted_at']) : null,
        postedBy: $row['posted_by'] ? UserId::fromString($row['posted_by']) : null,
        voidedAt: $row['voided_at'] ? new DateTimeImmutable($row['voided_at']) : null,
        voidedBy: $row['voided_by'] ? UserId::fromString($row['voided_by']) : null,
        voidReason: $row['void_reason'] ?? null,
        lines: $hydratedLines
    );
}
```

**Step 3: Run tests**

Run: `docker exec accounting-app-dev vendor/bin/phpunit tests/ -v`
Expected: Most tests PASS

**Step 4: Commit**

```bash
git add src/Domain/Transaction/Entity/Transaction.php src/Infrastructure/Persistence/Mysql/Hydrator/TransactionHydrator.php
git commit -m "refactor: replace reflection with reconstitute method for Transaction hydration"
```

---

## Phase 4: Quality Improvements (Tasks 13-15)

### Task 13: Implement Recovery Codes for OTP

**Files:**
- Create: `src/Domain/Identity/ValueObject/RecoveryCode.php`
- Modify: `src/Application/Handler/Admin/SetupAdminHandler.php:55-60`

**Step 1: Create RecoveryCode value object**

```php
<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

final readonly class RecoveryCode
{
    private const CODE_LENGTH = 8;
    private const CODE_COUNT = 10;

    private function __construct(
        private string $code,
        private bool $used = false
    ) {
    }

    public static function generate(): self
    {
        $code = strtoupper(bin2hex(random_bytes(self::CODE_LENGTH / 2)));
        return new self($code);
    }

    /**
     * @return array<self>
     */
    public static function generateSet(): array
    {
        $codes = [];
        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $codes[] = self::generate();
        }
        return $codes;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markUsed(): self
    {
        return new self($this->code, true);
    }

    public function matches(string $input): bool
    {
        return hash_equals($this->code, strtoupper(trim($input)));
    }
}
```

**Step 2: Update SetupAdminHandler**

```php
// In SetupAdminHandler.php, replace recovery_codes line:
$recoveryCodes = RecoveryCode::generateSet();

return new SetupAdminResponse(
    userId: $user->id()->toString(),
    email: $user->email()->toString(),
    otpSecret: $totpSecret,
    otpQrCode: $this->totpService->getProvisioningUri($totpSecret, $user->email()->toString()),
    recoveryCodes: array_map(fn($code) => $code->code(), $recoveryCodes),
);
```

**Step 3: Commit**

```bash
git add src/Domain/Identity/ValueObject/RecoveryCode.php src/Application/Handler/Admin/SetupAdminHandler.php
git commit -m "feat: implement OTP recovery codes generation"
```

---

### Task 14: Add strict_types to DTOs

**Files:**
- Modify: `src/Application/Dto/Transaction/TransactionDto.php`
- Modify: `src/Application/Dto/Transaction/TransactionLineDto.php`

**Step 1: Add declare statement to TransactionDto**

```php
<?php

declare(strict_types=1);

namespace Application\Dto\Transaction;
// ... rest of file
```

**Step 2: Add declare statement to TransactionLineDto**

```php
<?php

declare(strict_types=1);

namespace Application\Dto\Transaction;
// ... rest of file
```

**Step 3: Commit**

```bash
git add src/Application/Dto/Transaction/
git commit -m "refactor: add strict_types to Transaction DTOs"
```

---

### Task 15: Clean Up Unused Sessions Table Documentation

**Files:**
- Create: `docs/decisions/001-redis-sessions.md`

**Step 1: Create architecture decision record**

```markdown
# ADR-001: Redis-Only Session Storage

## Status
Accepted

## Context
The database schema includes a `sessions` table, but the application uses Redis for session storage via `SessionAuthenticationService`.

## Decision
Sessions are stored exclusively in Redis for:
- Performance (no database queries for session validation)
- Automatic TTL expiration
- Horizontal scaling support

## Consequences
- The `sessions` table in `init.sql` is unused legacy code
- Session data is lost if Redis restarts without persistence
- Consider enabling Redis persistence (RDB/AOF) for production

## Migration Path
The `sessions` table can be removed in a future schema cleanup. It remains for now to avoid breaking existing deployments.
```

**Step 2: Commit**

```bash
git add docs/decisions/001-redis-sessions.md
git commit -m "docs: add ADR for Redis-only session storage decision"
```

---

## Final Phase: Run Full Test Suite (Task 16)

### Task 16: Verify All Fixes

**Step 1: Apply database migrations**

```bash
docker exec accounting-mysql-dev mysql -u accounting_user -paccounting_pass accounting_system -e "source /var/www/html/docker/mysql/migration_account_balances.sql"
docker exec accounting-mysql-dev mysql -u accounting_user -paccounting_pass accounting_system -e "source /var/www/html/docker/mysql/migration_approval_proof.sql"
```

**Step 2: Run full test suite**

```bash
docker exec accounting-app-dev vendor/bin/phpunit tests/ -v
```

**Step 3: Verify API endpoints**

```bash
curl -s http://localhost:8080/api/v1/auth/login -X POST -H "Content-Type: application/json" -d '{"email":"test@test.com","password":"test"}'
```

**Step 4: Final commit**

```bash
git add .
git commit -m "chore: apply all comprehensive fixes - 26 issues resolved"
```

---

## Summary

| Phase | Tasks | Description |
|-------|-------|-------------|
| 1 | 1-4 | Critical Security Fixes |
| 2 | 5-7 | Database Schema Fixes |
| 3 | 8-12 | Code Bug Fixes |
| 4 | 13-15 | Quality Improvements |
| Final | 16 | Verification |

**Total Tasks:** 16
**Estimated Time:** 2-3 hours
**Risk Level:** Medium (database migrations require careful execution)
