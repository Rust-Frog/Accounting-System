# Backend Implementation TODO

> **Last Updated:** 2025-12-13  
> **Status:** Architecture-Aligned Implementation Guide  
> **Purpose:** Comprehensive backend development roadmap following DDD, TDD, and documented architecture

---

## 🎯 Overview

This document provides a precise, step-by-step guide for implementing the Accounting System backend following:
- **Domain-Driven Design (DDD)** principles
- **Test-Driven Development (TDD)** approach
- **Hexagonal Architecture** (Ports & Adapters)
- **Event-Driven Architecture** patterns
- All documented subsystem specifications

---

## 📋 Table of Contents

1. [Architecture Foundation](#1-architecture-foundation)
2. [Development Environment](#2-development-environment)
3. [Phase-by-Phase Implementation](#3-phase-by-phase-implementation)
4. [Database Schema](#4-database-schema)
5. [API Endpoints](#5-api-endpoints)
6. [Testing Strategy](#6-testing-strategy)
7. [Quality Gates](#7-quality-gates)
8. [Deployment Workflow](#8-deployment-workflow)

---

## 1. Architecture Foundation

### 1.1 Core Principles

**DDD Layers:**
```
┌─────────────────────────────────────────┐
│  Infrastructure Layer (HTTP, DB, etc)   │  ← Adapters
├─────────────────────────────────────────┤
│  Application Layer (Use Cases)          │  ← Orchestration
├─────────────────────────────────────────┤
│  Domain Layer (Business Logic)          │  ← Core
└─────────────────────────────────────────┘
```

**Dependency Rule:**
- Domain Layer → No dependencies (pure PHP)
- Application Layer → Depends on Domain
- Infrastructure Layer → Depends on Application & Domain

### 1.2 Bounded Contexts (8 Total)

1. **Identity & Access Management** - Authentication, users, sessions
2. **Company Management** - Multi-tenancy, settings
3. **Chart of Accounts** - Account structure
4. **Transaction Processing** - Double-entry transactions
5. **Ledger & Posting** - Balance tracking
6. **Financial Reporting** - Reports generation
7. **Approval Workflow** - Admin approvals
8. **Audit Trail** - Activity logging

### 1.3 Directory Structure

```
src/
├── Domain/                    # Core business logic
│   ├── Shared/               # Shared Kernel
│   │   ├── ValueObject/
│   │   │   ├── Uuid.php
│   │   │   ├── Money.php
│   │   │   ├── Email.php
│   │   │   └── Currency.php
│   │   ├── Event/
│   │   │   └── DomainEvent.php
│   │   └── Exception/
│   │       └── DomainException.php
│   │
│   ├── Identity/             # Bounded Context
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   └── Session.php
│   │   ├── ValueObject/
│   │   │   ├── UserId.php
│   │   │   ├── Role.php
│   │   │   └── RegistrationStatus.php
│   │   ├── Repository/
│   │   │   └── UserRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── AuthenticationService.php
│   │   │   └── PasswordService.php
│   │   └── Event/
│   │       └── UserRegistered.php
│   │
│   ├── Company/              # Bounded Context
│   ├── ChartOfAccounts/      # Bounded Context
│   ├── Transaction/          # Bounded Context
│   ├── Ledger/               # Bounded Context
│   ├── Approval/             # Bounded Context
│   └── Audit/                # Bounded Context
│
├── Application/              # Use Cases
│   ├── Command/
│   │   ├── Identity/
│   │   │   ├── RegisterUserCommand.php
│   │   │   └── AuthenticateCommand.php
│   │   └── Transaction/
│   │       ├── CreateTransactionCommand.php
│   │       └── PostTransactionCommand.php
│   ├── Handler/
│   │   ├── Identity/
│   │   │   ├── RegisterUserHandler.php
│   │   │   └── AuthenticateHandler.php
│   │   └── Transaction/
│   │       ├── CreateTransactionHandler.php
│   │       └── PostTransactionHandler.php
│   ├── Query/
│   │   ├── GetUserQuery.php
│   │   └── GetTransactionQuery.php
│   └── DTO/
│       ├── UserDTO.php
│       └── TransactionDTO.php
│
└── Infrastructure/           # External concerns
    ├── Persistence/
    │   ├── MySQL/
    │   │   ├── MySQLUserRepository.php
    │   │   └── MySQLTransactionRepository.php
    │   └── InMemory/
    │       └── InMemoryUserRepository.php
    ├── Http/
    │   ├── Controller/
    │   │   ├── AuthController.php
    │   │   └── TransactionController.php
    │   ├── Middleware/
    │   │   └── AuthMiddleware.php
    │   └── Response/
    │       └── JsonResponse.php
    ├── Event/
    │   └── SimpleEventBus.php
    └── Migration/
        └── Migrator.php
```

---

## 2. Development Environment

### 2.1 Docker Setup

```bash
# Start development environment
make up ENV=dev

# Install dependencies
make composer-install ENV=dev

# Access shell
make shell ENV=dev
```

### 2.2 Required Tools

- **PHP 8.2+** with extensions: pdo_mysql, mbstring, bcmath
- **MySQL 8.0**
- **Redis** (for caching/sessions)
- **Composer** for dependency management
- **PHPUnit** for testing
- **PHPStan** (Level 8) for static analysis
- **PHP_CodeSniffer** (PSR-12) for code style

### 2.3 Dependencies (composer.json)

```json
{
  "require": {
    "php": ">=8.2",
    "ramsey/uuid": "^4.7",
    "firebase/php-jwt": "^6.10"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5",
    "phpstan/phpstan": "^1.10",
    "squizlabs/php_codesniffer": "^3.8"
  },
  "autoload": {
    "psr-4": {
      "AccountingSystem\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "AccountingSystem\\Tests\\": "tests/"
    }
  }
}
```

---

## 3. Phase-by-Phase Implementation

### Phase 1: Shared Kernel (Week 1)

**Priority:** CRITICAL  
**Estimated Time:** 3-4 days

#### Task 1.1: Core Value Objects

**Files to Create:**

```php
// src/Domain/Shared/ValueObject/Uuid.php
final class Uuid
{
    private function __construct(private readonly string $value) {}
    
    public static function generate(): self
    public static function fromString(string $value): self
    public function toString(): string
    public function equals(self $other): bool
}

// src/Domain/Shared/ValueObject/Money.php
final class Money
{
    private function __construct(
        private readonly float $amount,
        private readonly Currency $currency
    ) {}
    
    public static function fromFloat(float $amount, Currency $currency): self
    public function add(self $other): self
    public function subtract(self $other): self
    public function isZero(): bool
    public function isPositive(): bool
    public function equals(self $other): bool
}

// src/Domain/Shared/ValueObject/Email.php
final class Email
{
    private function __construct(private readonly string $value) {}
    
    public static function fromString(string $value): self
    public function toString(): string
    public function equals(self $other): bool
}

// src/Domain/Shared/ValueObject/Currency.php
enum Currency: string
{
    case PHP = 'PHP';
    case USD = 'USD';
    case EUR = 'EUR';
}
```

**TDD Approach:**

```php
// tests/Unit/Domain/Shared/ValueObject/MoneyTest.php
class MoneyTest extends TestCase
{
    public function test_creates_money_from_float(): void
    {
        $money = Money::fromFloat(100.50, Currency::PHP);
        $this->assertEquals(100.50, $money->amount());
    }
    
    public function test_adds_two_money_objects(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(50.00, Currency::PHP);
        $result = $m1->add($m2);
        $this->assertEquals(150.00, $result->amount());
    }
    
    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromFloat(-10.00, Currency::PHP);
    }
}
```

**Implementation Steps:**
1. Write failing test
2. Implement minimal code to pass
3. Refactor
4. Repeat for all value objects

#### Task 1.2: Domain Events

**Files to Create:**

```php
// src/Domain/Shared/Event/DomainEvent.php
interface DomainEvent
{
    public function occurredOn(): DateTimeImmutable;
    public function eventName(): string;
    public function toArray(): array;
}

// src/Domain/Shared/Event/EventDispatcher.php
interface EventDispatcher
{
    public function dispatch(DomainEvent $event): void;
}
```

#### Task 1.3: Domain Exceptions

```php
// src/Domain/Shared/Exception/DomainException.php
abstract class DomainException extends Exception {}

// src/Domain/Shared/Exception/InvalidArgumentException.php
final class InvalidArgumentException extends DomainException {}

// src/Domain/Shared/Exception/ValidationException.php
final class ValidationException extends DomainException
{
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed'
    ) {
        parent::__construct($message);
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

**Phase 1 Checklist:**
- [ ] All value objects implemented with tests
- [ ] Domain events interface created
- [ ] Exception hierarchy established
- [ ] 100% test coverage on value objects
- [ ] PHPStan level 8 passing
- [ ] PSR-12 compliant

---

### Phase 2: Identity Domain (Week 1-2)

**Priority:** CRITICAL  
**Estimated Time:** 4-5 days

#### Task 2.1: Value Objects

```php
// src/Domain/Identity/ValueObject/UserId.php
final class UserId
{
    private function __construct(private readonly Uuid $value) {}
    
    public static function generate(): self
    public static function fromString(string $value): self
    public function toString(): string
    public function equals(self $other): bool
}

// src/Domain/Identity/ValueObject/Role.php
enum Role: string
{
    case ADMIN = 'admin';
    case TENANT = 'tenant';
    
    public function isAdmin(): bool
    public function canApprove(): bool
}

// src/Domain/Identity/ValueObject/RegistrationStatus.php
enum RegistrationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DECLINED = 'declined';
    
    public function isPending(): bool
    public function canAuthenticate(): bool
}
```

#### Task 2.2: User Entity (TDD)

```php
// tests/Unit/Domain/Identity/Entity/UserTest.php
class UserTest extends TestCase
{
    public function test_creates_user_with_valid_data(): void
    {
        $user = User::register(
            username: 'john.doe',
            email: Email::fromString('john@example.com'),
            password: 'Password123',
            role: Role::TENANT,
            companyId: CompanyId::fromString('...')
        );
        
        $this->assertEquals('john.doe', $user->username());
        $this->assertEquals(RegistrationStatus::PENDING, $user->registrationStatus());
    }
    
    public function test_hashes_password_on_creation(): void
    {
        $user = User::register(...);
        $this->assertNotEquals('Password123', $user->passwordHash());
        $this->assertTrue(password_verify('Password123', $user->passwordHash()));
    }
    
    public function test_authenticates_with_correct_password(): void
    {
        $user = User::register(...);
        $user->approve(UserId::generate()); // Approved by admin
        
        $result = $user->authenticate('Password123');
        $this->assertTrue($result);
    }
    
    public function test_rejects_wrong_password(): void
    {
        $user = User::register(...);
        $result = $user->authenticate('WrongPassword');
        $this->assertFalse($result);
    }
    
    public function test_pending_user_cannot_authenticate(): void
    {
        $user = User::register(...); // Still pending
        $this->expectException(DomainException::class);
        $user->authenticate('Password123');
    }
    
    public function test_cannot_self_approve(): void
    {
        $user = User::register(...);
        $this->expectException(DomainException::class);
        $user->approve($user->id()); // Same user
    }
}
```

#### Task 2.3: User Entity Implementation

```php
// src/Domain/Identity/Entity/User.php
final class User
{
    private array $domainEvents = [];
    
    private function __construct(
        private readonly UserId $userId,
        private ?CompanyId $companyId,
        private string $username,
        private Email $email,
        private string $passwordHash,
        private Role $role,
        private RegistrationStatus $registrationStatus,
        private bool $isActive,
        private ?DateTimeImmutable $lastLoginAt,
        private ?string $lastLoginIp,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {}
    
    public static function register(
        string $username,
        Email $email,
        string $password,
        Role $role,
        ?CompanyId $companyId = null
    ): self {
        // BR-IAM-003: Password validation
        self::validatePassword($password);
        
        // BR-IAM-006: Admins have no company
        if ($role === Role::ADMIN && $companyId !== null) {
            throw new InvalidArgumentException('Admins cannot belong to a company');
        }
        
        // BR-IAM-007: Tenants must have company
        if ($role === Role::TENANT && $companyId === null) {
            throw new InvalidArgumentException('Tenants must belong to a company');
        }
        
        $user = new self(
            userId: UserId::generate(),
            companyId: $companyId,
            username: $username,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            role: $role,
            registrationStatus: RegistrationStatus::PENDING, // BR-IAM-005
            isActive: true,
            lastLoginAt: null,
            lastLoginIp: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable()
        );
        
        $user->recordEvent(new UserRegistered($user->userId, $user->email, $user->role));
        
        return $user;
    }
    
    public function authenticate(string $password): bool
    {
        // BR-IAM-009: Deactivated users cannot authenticate
        if (!$this->isActive) {
            throw new DomainException('User account is deactivated');
        }
        
        // BR-IAM-005: Pending users cannot authenticate
        if ($this->registrationStatus === RegistrationStatus::PENDING) {
            throw new DomainException('User registration pending approval');
        }
        
        if ($this->registrationStatus === RegistrationStatus::DECLINED) {
            throw new DomainException('User registration was declined');
        }
        
        $valid = password_verify($password, $this->passwordHash);
        
        if ($valid) {
            $this->lastLoginAt = new DateTimeImmutable();
            $this->recordEvent(new UserAuthenticated($this->userId));
        }
        
        return $valid;
    }
    
    public function approve(UserId $approverId): void
    {
        // BR-IAM-010: Cannot self-approve
        if ($this->userId->equals($approverId)) {
            throw new DomainException('Users cannot approve themselves');
        }
        
        if ($this->registrationStatus !== RegistrationStatus::PENDING) {
            throw new DomainException('Only pending registrations can be approved');
        }
        
        $this->registrationStatus = RegistrationStatus::APPROVED;
        $this->updatedAt = new DateTimeImmutable();
        $this->recordEvent(new RegistrationApproved($this->userId, $approverId));
    }
    
    private static function validatePassword(string $password): void
    {
        // BR-IAM-003: Password requirements
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidArgumentException('Password must contain uppercase letter');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new InvalidArgumentException('Password must contain lowercase letter');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('Password must contain digit');
        }
    }
    
    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
    
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
    
    // Getters
    public function id(): UserId { return $this->userId; }
    public function username(): string { return $this->username; }
    public function email(): Email { return $this->email; }
    public function role(): Role { return $this->role; }
    public function registrationStatus(): RegistrationStatus { return $this->registrationStatus; }
    public function passwordHash(): string { return $this->passwordHash; }
}
```

#### Task 2.4: Repository Interface

```php
// src/Domain/Identity/Repository/UserRepositoryInterface.php
interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(UserId $userId): ?User;
    public function findByUsername(string $username): ?User;
    public function findByEmail(Email $email): ?User;
    public function existsByUsername(string $username): bool;
    public function existsByEmail(Email $email): bool;
    public function findPendingUsers(): array;
}
```

**Phase 2 Checklist:**
- [ ] All Identity value objects implemented
- [ ] User entity with full business rules
- [ ] Session entity
- [ ] Repository interfaces defined
- [ ] Domain events for Identity
- [ ] 100% test coverage
- [ ] All business rules enforced

---

### Phase 3: Company Domain (Week 2)

**Priority:** CRITICAL  
**Estimated Time:** 3 days

#### Company Entity (TDD First)

```php
// tests/Unit/Domain/Company/Entity/CompanyTest.php
class CompanyTest extends TestCase
{
    public function test_creates_company_with_valid_data(): void
    {
        $company = Company::create(
            companyName: 'Acme Corp',
            legalName: 'Acme Corporation Inc.',
            taxId: TaxIdentifier::fromString('123-456-789'),
            address: Address::create(...),
            currency: Currency::PHP
        );
        
        $this->assertEquals('Acme Corp', $company->companyName());
        $this->assertEquals(CompanyStatus::PENDING, $company->status());
    }
    
    public function test_only_admins_can_activate_company(): void
    {
        $company = Company::create(...);
        $admin = $this->createAdmin();
        
        $company->activate($admin->id());
        $this->assertEquals(CompanyStatus::ACTIVE, $company->status());
    }
    
    public function test_non_admin_cannot_activate(): void
    {
        $company = Company::create(...);
        $tenant = $this->createTenant();
        
        $this->expectException(DomainException::class);
        $company->activate($tenant->id());
    }
}
```

**Phase 3 Checklist:**
- [ ] Company entity with TDD
- [ ] CompanySettings entity
- [ ] TaxIdentifier, Address value objects
- [ ] Company repository interface
- [ ] Company domain events

---

### Phase 4: Chart of Accounts (Week 2-3)

**Priority:** HIGH  
**Estimated Time:** 3-4 days

#### Account Entity (TDD)

```php
// tests/Unit/Domain/ChartOfAccounts/Entity/AccountTest.php
class AccountTest extends TestCase
{
    public function test_derives_account_type_from_code(): void
    {
        $account = Account::create(
            accountCode: AccountCode::fromString('1000'),
            accountName: 'Cash',
            companyId: CompanyId::generate()
        );
        
        $this->assertEquals(AccountType::ASSET, $account->accountType());
    }
    
    public function test_derives_normal_balance_from_type(): void
    {
        // Asset account (1000-1999) has debit normal balance
        $asset = Account::create(
            accountCode: AccountCode::fromString('1000'),
            ...
        );
        $this->assertEquals(NormalBalance::DEBIT, $asset->normalBalance());
        
        // Liability account (2000-2999) has credit normal balance
        $liability = Account::create(
            accountCode: AccountCode::fromString('2000'),
            ...
        );
        $this->assertEquals(NormalBalance::CREDIT, $liability->normalBalance());
    }
    
    public function test_rejects_invalid_account_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AccountCode::fromString('9999'); // Invalid range
    }
    
    public function test_cannot_deactivate_account_with_balance(): void
    {
        $account = Account::create(...);
        $account->recordBalance(Money::fromFloat(100, Currency::PHP));
        
        $this->expectException(DomainException::class);
        $account->deactivate();
    }
}
```

**Phase 4 Checklist:**
- [ ] AccountCode value object with validation
- [ ] AccountType and NormalBalance enums
- [ ] Account entity with hierarchy support
- [ ] Account code range validation (BR-COA-002)
- [ ] Default chart initializer service

---

### Phase 5: Transaction Processing (Week 3-4)

**Priority:** CRITICAL  
**Estimated Time:** 5-6 days

This is the CORE domain - most complex business logic.

#### Transaction Entity (TDD)

```php
// tests/Unit/Domain/Transaction/Entity/TransactionTest.php
class TransactionTest extends TestCase
{
    public function test_balanced_transaction_is_valid(): void
    {
        $transaction = Transaction::create(
            companyId: CompanyId::generate(),
            transactionDate: new DateTimeImmutable('2024-01-15'),
            description: 'Cash sale',
            createdBy: UserId::generate()
        );
        
        // Add lines
        $transaction->addLine(
            accountId: AccountId::fromString('...'), // Cash
            lineType: LineType::DEBIT,
            amount: Money::fromFloat(1000, Currency::PHP)
        );
        
        $transaction->addLine(
            accountId: AccountId::fromString('...'), // Revenue
            lineType: LineType::CREDIT,
            amount: Money::fromFloat(1000, Currency::PHP)
        );
        
        $result = $transaction->validate();
        $this->assertTrue($result->isValid());
    }
    
    public function test_unbalanced_transaction_is_invalid(): void
    {
        $transaction = Transaction::create(...);
        $transaction->addLine(..., LineType::DEBIT, Money::fromFloat(1000, ...));
        $transaction->addLine(..., LineType::CREDIT, Money::fromFloat(500, ...));
        
        $result = $transaction->validate();
        $this->assertFalse($result->isValid());
        $this->assertContains('Debits must equal credits', $result->getErrors());
    }
    
    public function test_requires_minimum_two_lines(): void
    {
        $transaction = Transaction::create(...);
        $transaction->addLine(..., LineType::DEBIT, Money::fromFloat(100, ...));
        
        $result = $transaction->validate();
        $this->assertFalse($result->isValid());
        $this->assertContains('At least 2 lines required', $result->getErrors());
    }
    
    public function test_posted_transaction_cannot_be_edited(): void
    {
        $transaction = $this->createValidTransaction();
        $transaction->post(UserId::generate());
        
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Posted transactions cannot be modified');
        $transaction->addLine(...);
    }
    
    public function test_voided_transaction_is_terminal(): void
    {
        $transaction = $this->createPostedTransaction();
        $transaction->void('Duplicate entry', UserId::generate());
        
        $this->expectException(DomainException::class);
        $transaction->post(UserId::generate()); // Cannot re-post
    }
}
```

#### TransactionValidator Service

```php
// src/Domain/Transaction/Service/TransactionValidator.php
final class TransactionValidator
{
    public function validate(Transaction $transaction): ValidationResult
    {
        $errors = [];
        
        // BR-TXN-002: Minimum lines
        if (count($transaction->lines()) < 2) {
            $errors[] = 'Transaction must have at least 2 lines';
        }
        
        // Check for at least one debit and one credit
        $hasDebit = false;
        $hasCredit = false;
        foreach ($transaction->lines() as $line) {
            if ($line->lineType() === LineType::DEBIT) $hasDebit = true;
            if ($line->lineType() === LineType::CREDIT) $hasCredit = true;
        }
        
        if (!$hasDebit || !$hasCredit) {
            $errors[] = 'Transaction must have at least one debit and one credit';
        }
        
        // BR-TXN-001: Double-entry balance
        $totalDebits = $this->calculateTotal($transaction, LineType::DEBIT);
        $totalCredits = $this->calculateTotal($transaction, LineType::CREDIT);
        
        if (abs($totalDebits - $totalCredits) > 0.01) {
            $errors[] = sprintf(
                'Transaction is not balanced: Debits (%s) != Credits (%s)',
                $totalDebits,
                $totalCredits
            );
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
    
    private function calculateTotal(Transaction $transaction, LineType $type): float
    {
        return array_reduce(
            array_filter(
                $transaction->lines(),
                fn($line) => $line->lineType() === $type
            ),
            fn($sum, $line) => $sum + $line->amount()->amount(),
            0.0
        );
    }
}
```

**Phase 5 Checklist:**
- [ ] Transaction entity with full state machine
- [ ] TransactionLine value entity
- [ ] TransactionValidator service
- [ ] BalanceCalculator service
- [ ] TransactionNumberGenerator service
- [ ] All 9 transaction business rules enforced
- [ ] Comprehensive test coverage for edge cases

---

### Phase 6: Ledger & Posting (Week 4-5)

**Priority:** CRITICAL  
**Estimated Time:** 4 days

#### Balance Calculation

```php
// src/Domain/Ledger/Service/BalanceCalculationService.php
final class BalanceCalculationService
{
    public function calculateChange(
        Account $account,
        LineType $lineType,
        Money $amount
    ): Money {
        // BR-LED: Balance change calculation
        // If line type matches normal balance = increase
        // If line type differs from normal balance = decrease
        
        $shouldIncrease = ($account->normalBalance() === NormalBalance::DEBIT && $lineType === LineType::DEBIT)
            || ($account->normalBalance() === NormalBalance::CREDIT && $lineType === LineType::CREDIT);
        
        return $shouldIncrease ? $amount : Money::fromFloat(-$amount->amount(), $amount->currency());
    }
}
```

**Phase 6 Checklist:**
- [ ] AccountBalance entity with optimistic locking
- [ ] BalanceChange entity
- [ ] LedgerPostingService
- [ ] AccountingEquationValidator
- [ ] Balance change calculation tests
- [ ] Accounting equation validation

---

### Phase 7: Application Layer (Week 5-6)

**Priority:** HIGH  
**Estimated Time:** 5 days

#### Command/Handler Pattern

```php
// src/Application/Command/Transaction/CreateTransactionCommand.php
final readonly class CreateTransactionCommand
{
    public function __construct(
        public string $companyId,
        public string $transactionDate,
        public string $description,
        public array $lines,
        public string $createdBy
    ) {}
}

// src/Application/Handler/Transaction/CreateTransactionHandler.php
final class CreateTransactionHandler
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private AccountRepositoryInterface $accountRepository,
        private TransactionValidator $validator,
        private EventDispatcher $eventDispatcher
    ) {}
    
    public function handle(CreateTransactionCommand $command): TransactionDTO
    {
        // 1. Create domain objects from command
        $transaction = Transaction::create(
            companyId: CompanyId::fromString($command->companyId),
            transactionDate: new DateTimeImmutable($command->transactionDate),
            description: $command->description,
            createdBy: UserId::fromString($command->createdBy)
        );
        
        // 2. Add lines
        foreach ($command->lines as $lineData) {
            $account = $this->accountRepository->findById(
                AccountId::fromString($lineData['accountId'])
            );
            
            if ($account === null) {
                throw new InvalidArgumentException('Account not found');
            }
            
            $transaction->addLine(
                accountId: $account->id(),
                lineType: LineType::from($lineData['type']),
                amount: Money::fromFloat($lineData['amount'], Currency::PHP)
            );
        }
        
        // 3. Validate
        $validationResult = $this->validator->validate($transaction);
        if (!$validationResult->isValid()) {
            throw new ValidationException($validationResult->getErrors());
        }
        
        // 4. Save
        $this->transactionRepository->save($transaction);
        
        // 5. Dispatch events
        foreach ($transaction->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
        
        // 6. Return DTO
        return TransactionDTO::fromEntity($transaction);
    }
}
```

**Phase 7 Checklist:**
- [ ] All command classes
- [ ] All handler classes
- [ ] DTO mappings
- [ ] Query handlers
- [ ] Integration tests for use cases

---

### Phase 8: Infrastructure Layer (Week 6-7)

**Priority:** HIGH  
**Estimated Time:** 5-6 days

#### MySQL Repository Implementation

```php
// src/Infrastructure/Persistence/MySQL/MySQLUserRepository.php
final class MySQLUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo) {}
    
    public function save(User $user): void
    {
        $sql = "INSERT INTO users (
            id, company_id, username, email, password_hash, role,
            registration_status, is_active, created_at, updated_at
        ) VALUES (
            :id, :company_id, :username, :email, :password_hash, :role,
            :registration_status, :is_active, :created_at, :updated_at
        ) ON DUPLICATE KEY UPDATE
            registration_status = VALUES(registration_status),
            is_active = VALUES(is_active),
            updated_at = VALUES(updated_at)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $user->id()->toString(),
            'company_id' => $user->companyId()?->toString(),
            'username' => $user->username(),
            'email' => $user->email()->toString(),
            'password_hash' => $user->passwordHash(),
            'role' => $user->role()->value,
            'registration_status' => $user->registrationStatus()->value,
            'is_active' => $user->isActive() ? 1 : 0,
            'created_at' => $user->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt()->format('Y-m-d H:i:s')
        ]);
    }
    
    public function findById(UserId $userId): ?User
    {
        $sql = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $userId->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }
    
    private function hydrate(array $row): User
    {
        // Reconstruct User from database row
        // Use reflection or a factory method
    }
}
```

**Phase 8 Checklist:**
- [ ] MySQL repositories for all aggregates
- [ ] InMemory repositories for testing
- [ ] Database migrations
- [ ] Connection pooling
- [ ] Transaction management
- [ ] Integration tests with real database

---

### Phase 9: HTTP API (Week 7-8)

**Priority:** HIGH  
**Estimated Time:** 5 days

#### REST Controllers

```php
// src/Infrastructure/Http/Controller/TransactionController.php
final class TransactionController
{
    public function __construct(
        private CreateTransactionHandler $createHandler,
        private PostTransactionHandler $postHandler
    ) {}
    
    public function create(Request $request): JsonResponse
    {
        try {
            $command = new CreateTransactionCommand(
                companyId: $request->get('company_id'),
                transactionDate: $request->get('transaction_date'),
                description: $request->get('description'),
                lines: $request->get('lines'),
                createdBy: $request->user()->id
            );
            
            $dto = $this->createHandler->handle($command);
            
            return JsonResponse::created($dto->toArray());
        } catch (ValidationException $e) {
            return JsonResponse::unprocessableEntity(['errors' => $e->getErrors()]);
        } catch (DomainException $e) {
            return JsonResponse::badRequest(['error' => $e->getMessage()]);
        }
    }
    
    public function post(Request $request, string $id): JsonResponse
    {
        try {
            $command = new PostTransactionCommand(
                transactionId: $id,
                postedBy: $request->user()->id
            );
            
            $dto = $this->postHandler->handle($command);
            
            return JsonResponse::ok($dto->toArray());
        } catch (DomainException $e) {
            return JsonResponse::badRequest(['error' => $e->getMessage()]);
        }
    }
}
```

**Phase 9 Checklist:**
- [ ] All REST controllers
- [ ] Authentication middleware
- [ ] Authorization middleware
- [ ] Request validation
- [ ] Error handling
- [ ] API documentation (OpenAPI)

---

## 4. Database Schema

### Migration Strategy

```php
// database/migrations/001_create_users_table.php
return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE users (
                id CHAR(36) PRIMARY KEY,
                company_id CHAR(36) NULL,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'tenant') NOT NULL DEFAULT 'tenant',
                registration_status ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                last_login_at TIMESTAMP NULL,
                last_login_ip VARCHAR(45) NULL,
                password_changed_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deactivated_at TIMESTAMP NULL,
                
                INDEX idx_users_company (company_id),
                INDEX idx_users_status (registration_status),
                INDEX idx_users_active (is_active),
                
                CONSTRAINT fk_users_company FOREIGN KEY (company_id)
                    REFERENCES companies(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS users");
    }
};
```

**Run Migrations:**
```bash
make shell ENV=dev
php database/migrate.php up
```

---

## 5. API Endpoints

### Authentication
- `POST /api/v1/auth/register` - Register user
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/logout` - Logout

### Companies
- `GET /api/v1/companies` - List companies
- `POST /api/v1/companies` - Create company
- `GET /api/v1/companies/:id` - Get company
- `POST /api/v1/companies/:id/activate` - Activate

### Accounts
- `GET /api/v1/accounts` - List accounts
- `POST /api/v1/accounts` - Create account
- `GET /api/v1/accounts/:id` - Get account
- `GET /api/v1/accounts/:id/balance` - Get balance

### Transactions
- `GET /api/v1/transactions` - List transactions
- `POST /api/v1/transactions` - Create transaction
- `GET /api/v1/transactions/:id` - Get transaction
- `POST /api/v1/transactions/:id/post` - Post transaction
- `POST /api/v1/transactions/:id/void` - Void transaction

---

## 6. Testing Strategy

### Test Pyramid

```
       /\
      /  \     E2E (API Tests)
     /────\    
    /      \   Integration Tests  
   /────────\  
  /          \ Unit Tests
 /────────────\
```

### Test Structure

```
tests/
├── Unit/
│   ├── Domain/
│   │   ├── Shared/
│   │   │   └── ValueObject/
│   │   │       ├── MoneyTest.php
│   │   │       └── EmailTest.php
│   │   ├── Identity/
│   │   │   └── Entity/
│   │   │       └── UserTest.php
│   │   └── Transaction/
│   │       ├── Entity/
│   │       │   └── TransactionTest.php
│   │       └── Service/
│   │           └── TransactionValidatorTest.php
│   └── Application/
│       └── Handler/
│           └── CreateTransactionHandlerTest.php
├── Integration/
│   └── Infrastructure/
│       └── Persistence/
│           └── MySQLUserRepositoryTest.php
└── Feature/
    └── Api/
        └── TransactionApiTest.php
```

### Running Tests

```bash
# All tests
make test ENV=dev

# Unit tests only
docker-compose -f docker/docker-compose.dev.yml exec app \
  vendor/bin/phpunit tests/Unit

# With coverage
docker-compose -f docker/docker-compose.dev.yml exec app \
  vendor/bin/phpunit --coverage-html coverage/
```

---

## 7. Quality Gates

Every PR must pass:

### Static Analysis
```bash
# PHPStan Level 8
vendor/bin/phpstan analyse src tests --level=8
```

### Code Style
```bash
# PHP_CodeSniffer PSR-12
vendor/bin/phpcs --standard=PSR12 src tests
```

### Tests
```bash
# Must have 80%+ coverage
vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml
```

### Architecture Rules
- Domain layer has zero dependencies
- No infrastructure code in domain
- All business rules in domain layer
- Repository pattern for data access
- Event-driven communication between contexts

---

## 8. Deployment Workflow

### Development
```bash
make up ENV=dev
make composer-install ENV=dev
make migrate ENV=dev
make seed ENV=dev
```

### Production Build
```bash
# Build image with code baked in
docker-compose -f docker/docker-compose.prod.yml build

# Tag and push
docker tag accounting-system:latest registry.com/accounting:v1.0.0
docker push registry.com/accounting:v1.0.0

# Deploy
ssh production
docker-compose -f docker/docker-compose.prod.yml pull
docker-compose -f docker/docker-compose.prod.yml up -d
docker-compose -f docker/docker-compose.prod.yml exec app php migrate up
```

---

## 9. Implementation Schedule

### Week 1
- [ ] Shared Kernel (Value Objects, Events, Exceptions)
- [ ] Identity Domain (User, Session)

### Week 2
- [ ] Company Domain
- [ ] Chart of Accounts Domain

### Week 3-4
- [ ] Transaction Processing Domain (Most Complex)

### Week 4-5
- [ ] Ledger & Posting Domain
- [ ] Approval Workflow Domain

### Week 5-6
- [ ] Application Layer (Commands, Handlers, DTOs)

### Week 6-7
- [ ] Infrastructure Layer (Repositories, Database)

### Week 7-8
- [ ] HTTP API Layer (Controllers, Middleware)

### Week 8
- [ ] Integration Testing
- [ ] API Documentation
- [ ] Deployment

---

## 10. Critical Success Factors

✅ **TDD Discipline:** Write tests FIRST, always  
✅ **Business Rules in Domain:** No logic in controllers  
✅ **Repository Pattern:** Clean separation  
✅ **Event-Driven:** Decouple bounded contexts  
✅ **Immutable Value Objects:** Thread-safe, predictable  
✅ **Validation:** At domain boundaries  
✅ **Error Handling:** Use domain exceptions  
✅ **Documentation:** Keep docs updated  

---

## 11. Common Pitfalls to Avoid

❌ **Anemic Domain Model:** Don't put business logic in services  
❌ **God Objects:** Keep aggregates focused  
❌ **Breaking Dependency Rule:** Domain must be pure  
❌ **Skipping Tests:** TDD is non-negotiable  
❌ **Infrastructure in Domain:** No database code in entities  
❌ **Mutable Value Objects:** Always immutable  
❌ **Large Transactions:** Keep aggregates small  

---

## 12. Next Steps

1. ✅ Start Docker: `make up ENV=dev`
2. ✅ Install Dependencies: `make composer-install ENV=dev`
3. ✅ Create First Test: `tests/Unit/Domain/Shared/ValueObject/MoneyTest.php`
4. ✅ Implement Money Value Object
5. ✅ Continue with Shared Kernel
6. ✅ Move to Identity Domain
7. ✅ Follow phase-by-phase plan

---

**Ready to build! 🚀 Start with Phase 1: Shared Kernel**

