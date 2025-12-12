# System Architecture Overview

## Vision

A robust, modular accounting system built with:
- **Domain-Driven Design (DDD)**: Business logic isolated in domain models
- **Event-Driven Architecture (EDA)**: Subsystems communicate via events
- **Hexagonal Architecture**: Plug-and-play adapters for different technologies
- **Test-Driven Development (TDD)**: Tests written before implementation

## Architectural Principles

### 1. Bounded Contexts (DDD)

Each subsystem is a bounded context with:
- Clear domain models (Entities, Value Objects, Aggregates)
- Explicit boundaries
- Independent evolution
- Well-defined integration points

### 2. Ports & Adapters (Hexagonal)

```
┌─────────────────────────────────────────┐
│           Domain Core                    │
│  (Business Logic, Algorithms)            │
│                                          │
│  ┌────────────────────────────────┐     │
│  │   Entities & Value Objects      │     │
│  │   Domain Services               │     │
│  │   Repository Interfaces (Ports) │     │
│  └────────────────────────────────┘     │
└──────────────┬──────────────────────────┘
               │
    ┌──────────┼──────────┐
    │          │          │
┌───▼───┐  ┌──▼───┐  ┌──▼────┐
│  DB   │  │ HTTP │  │ Event │  Adapters
│Adapter│  │ API  │  │ Bus   │  (Implementations)
└───────┘  └──────┘  └───────┘
```

**Benefits:**
- Swap MySQL for PostgreSQL without touching domain logic
- Replace HTTP API with GraphQL easily
- Add event bus (RabbitMQ, Kafka) later
- Mock adapters for testing

### 3. Event-Driven Communication

**Hybrid Approach:**

**Event Sourcing for:**
- Transaction Processing (complete audit trail)
- Ledger Posting (state reconstruction)
- Approval Workflow (full history)

**Traditional Storage for:**
- Identity & Access Management
- Company Management
- Chart of Accounts

**Event Flow Example:**
```
Transaction Created
    ↓
TransactionValidated
    ↓
TransactionPosted
    ↓
LedgerUpdated + AccountBalanceChanged
    ↓
FinancialReportInvalidated
```

### 4. Subsystem Isolation

Each subsystem:
- Has its own database schema/tables
- Exposes well-defined ports (interfaces)
- Publishes domain events
- Subscribes to relevant events
- Can be deployed independently (future microservices)

## Technology Stack

| Layer | Technology | Why |
|-------|------------|-----|
| Backend | PHP 8.2+ | Required, modular structure |
| Frontend | HTML/CSS/JS | Vanilla, component patterns |
| Database | MySQL 8.0 | Student-friendly, widely taught |
| Events | Simple Event Bus (PHP) | Start simple, upgrade later |
| Container | Docker | Consistent environments |
| CI/CD | GitHub Actions | Automated testing & deployment |

## Quality Gates

Every change must:
1. ✅ Have tests (TDD)
2. ✅ Pass CI checks
3. ✅ Follow architecture patterns
4. ✅ Update documentation
5. ✅ Maintain domain integrity

## Educational Benefits

This architecture is perfect for students because:
- **Clear separation of concerns**: Easy to understand where code belongs
- **Testable**: Each layer can be tested independently
- **Realistic**: Industry-standard patterns
- **Scalable**: Can grow from monolith to microservices
- **Maintainable**: Easy to modify and extend

## Next Steps

See subsystem documentation in `/docs/02-subsystems/` for detailed designs.
