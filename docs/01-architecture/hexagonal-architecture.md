# Hexagonal Architecture (Ports & Adapters)

## Principle

Business logic (domain) is isolated in the center. All external dependencies are accessed through interfaces (ports). Different implementations (adapters) can be plugged in without changing the domain.

---

## Structure

```
/src
├── Domain/                      # The Core (Business Logic)
│   ├── Transaction/
│   │   ├── Entity/              # Domain entities
│   │   ├── ValueObject/         # Immutable value objects
│   │   ├── Repository/          # Repository interfaces (PORTS)
│   │   ├── Service/             # Domain services
│   │   └── Event/               # Domain events
│   └── ...other domains...
│
├── Application/                 # Use Cases (Application Logic)
│   ├── Transaction/
│   │   ├── CreateTransaction/
│   │   │   ├── CreateTransactionCommand.php
│   │   │   └── CreateTransactionHandler.php
│   │   └── PostTransaction/
│   └── ...other use cases...
│
└── Infrastructure/              # Adapters (IMPLEMENTATIONS)
    ├── Persistence/             # Database adapters
    │   ├── MySQL/
    │   └── InMemory/            # For testing
    ├── Http/                    # HTTP adapters
    │   ├── Controller/
    │   └── Middleware/
    ├── Event/                   # Event bus adapters
    └── Cli/                     # CLI adapters
```

---

## Example: Transaction Repository Port

**Domain Port (Interface):**

```php
<?php
namespace Domain\Transaction\Repository;

interface TransactionRepositoryInterface
{
    public function save(Transaction $transaction): void;
    public function findById(TransactionId $id): ?Transaction;
    public function findByCompany(string $companyId): array;
}
```

**MySQL Adapter (Implementation):**

```php
<?php
namespace Infrastructure\Persistence\MySQL;

class MySQLTransactionRepository implements TransactionRepositoryInterface
{
    private \PDO $pdo;

    public function save(Transaction $transaction): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (id, company_id, ...)
            VALUES (:id, :company_id, ...)
        ");
        $stmt->execute([...]);
    }
}
```

**In-Memory Adapter (For Testing):**

```php
<?php
namespace Infrastructure\Persistence\InMemory;

class InMemoryTransactionRepository implements TransactionRepositoryInterface
{
    private array $transactions = [];

    public function save(Transaction $transaction): void
    {
        $this->transactions[$transaction->getId()->value()] = $transaction;
    }
}
```

---

## Benefits

1. **Testability**: Use in-memory adapters for fast unit tests
2. **Flexibility**: Swap MySQL for PostgreSQL by changing adapter registration
3. **Technology Independence**: Domain logic doesn't depend on framework
4. **Future-Proof**: Easy to add new adapters (GraphQL, gRPC, message queue)

---

## Dependency Rule

Dependencies flow inward:

```
Infrastructure → Application → Domain

Domain depends on NOTHING
Application depends on Domain interfaces
Infrastructure depends on Application and implements Domain ports
```
