# Accounting System

> **Professional Accounting System** built with Domain-Driven Design, Event-Driven Architecture, and Test-Driven Development principles.

[![PHP Version](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

---

## 📋 Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Documentation](#documentation)
- [Development](#development)
- [Testing](#testing)
- [Deployment](#deployment)

---

## 🎯 Overview

A complete double-entry accounting system featuring:

- ✅ Multi-tenant architecture
- ✅ Chart of Accounts management
- ✅ Transaction processing with double-entry validation
- ✅ Approval workflow system
- ✅ Comprehensive audit trail
- ✅ Financial reporting (Balance Sheet, Income Statement, Trial Balance)
- ✅ REST API with JWT authentication
- ✅ Event-driven architecture
- ✅ Full test coverage

**Built for learning professional software architecture patterns.**

---

## 🚀 Quick Start

### Prerequisites

- Docker 20.10+
- Docker Compose 2.0+
- Make (optional)

### Setup (5 minutes)

```bash
# 1. Clone repository
git clone <repository-url>
cd Accounting-System

# 2. Copy environment file
cp .env.example .env

# 3. Start Docker containers
make up ENV=dev
# or
./scripts/docker.sh up dev

# 4. Install dependencies
make composer-install ENV=dev

# 5. Run migrations
make migrate ENV=dev

# 6. Seed database
make seed ENV=dev

# 7. Open browser
open http://localhost:8080
```

**Done!** 🎉

### Access Points

- **Application**: http://localhost:8080
- **PHPMyAdmin**: http://localhost:8081
- **API Docs**: http://localhost:8080/api/docs

---

## 🏗️ Architecture

### Design Patterns

- **Domain-Driven Design (DDD)**: Clear bounded contexts
- **Hexagonal Architecture**: Ports & Adapters
- **Event-Driven Architecture**: Domain events
- **CQRS**: Command/Query separation
- **Repository Pattern**: Data access abstraction
- **TDD**: Test-first development

### Bounded Contexts

1. **Identity & Access Management** - User authentication & authorization
2. **Company Management** - Multi-tenant company handling
3. **Chart of Accounts** - Account structure management
4. **Transaction Processing** - Double-entry transactions
5. **Ledger & Posting** - Balance tracking
6. **Financial Reporting** - Report generation
7. **Audit Trail** - Complete activity logging
8. **Approval Workflow** - Transaction approvals

### Technology Stack

- **Backend**: PHP 8.2
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Server**: Nginx + PHP-FPM
- **Testing**: PHPUnit
- **Quality**: PHPStan (Level 8), PHP_CodeSniffer (PSR-12)
- **CI/CD**: GitHub Actions
- **Containers**: Docker

---

## 📚 Documentation

### Core Documentation

| Document | Description |
|----------|-------------|
| [Architecture Overview](docs/01-architecture/) | System design and patterns |
| [Domain Models](docs/02-subsystems/) | All bounded contexts |
| [API Specification](docs/04-api/) | REST API documentation |
| [Database Schema](docs/03-algorithms/database-schema.md) | Complete database structure |
| [Testing Strategy](docs/06-testing/) | Testing approach |

### Setup & Deployment

| Document | Description |
|----------|-------------|
| [Docker Guide](docker/README.md) | Docker setup and commands |
| [Implementation Plan](docs/plans/implementation-plan.md) | Development roadmap |
| [Backend TODO](docs/plans/backend-todo.md) | ERD, flowcharts, use cases |

---

## 💻 Development

### Project Structure

```
.
├── src/
│   ├── Domain/              # Core business logic
│   │   ├── Identity/
│   │   ├── Company/
│   │   ├── ChartOfAccounts/
│   │   ├── Transaction/
│   │   ├── Ledger/
│   │   ├── Approval/
│   │   └── Audit/
│   ├── Application/         # Use cases
│   └── Infrastructure/      # External concerns
├── tests/
│   ├── Unit/               # Unit tests
│   └── Integration/        # Integration tests
├── docker/                 # Docker configuration
├── docs/                   # Documentation
├── scripts/                # Utility scripts
└── public/                 # Web entry point
```

### Common Commands

```bash
# Start containers
make up ENV=dev

# View logs
make logs ENV=dev

# Access shell
make shell ENV=dev

# Run tests
make test ENV=dev

# Run linter
make lint ENV=dev

# Run static analysis
make analyse ENV=dev

# Database operations
make migrate ENV=dev        # Run migrations
make seed ENV=dev          # Seed data
make fresh ENV=dev         # Fresh database

# Stop containers
make down ENV=dev
```

### Development Workflow

1. **Create feature branch**
   ```bash
   git checkout -b feature/transaction-validation
   ```

2. **Write failing test (TDD)**
   ```bash
   # tests/Unit/Domain/Transaction/TransactionTest.php
   make test ENV=dev
   ```

3. **Implement feature**
   ```bash
   # src/Domain/Transaction/...
   make test ENV=dev
   ```

4. **Run quality checks**
   ```bash
   make lint ENV=dev
   make analyse ENV=dev
   make test ENV=dev
   ```

5. **Commit and push**
   ```bash
   git add .
   git commit -m "feat(transaction): add double-entry validation"
   git push origin feature/transaction-validation
   ```

---

## 🧪 Testing

### Test Coverage

- **Unit Tests**: Domain logic, Value Objects, Entities
- **Integration Tests**: Repositories, Database
- **API Tests**: HTTP endpoints

### Running Tests

```bash
# All tests
make test ENV=dev

# Specific test file
docker-compose -f docker/docker-compose.dev.yml exec app \
  vendor/bin/phpunit tests/Unit/Domain/Transaction/TransactionTest.php

# With coverage
docker-compose -f docker/docker-compose.dev.yml exec app \
  vendor/bin/phpunit --coverage-html coverage/
```

### Example Test

```php
public function test_transaction_validates_double_entry(): void
{
    // Arrange
    $transaction = new Transaction(/* ... */);
    
    // Act
    $result = $transaction->validate();
    
    // Assert
    $this->assertTrue($result->isValid());
    $this->assertEquals(0, $result->getTotalDebits() - $result->getTotalCredits());
}
```

---

## 🚢 Deployment

### Development

```bash
./scripts/docker.sh up dev
```

### Production

```bash
# 1. Build production image
docker-compose -f docker/docker-compose.prod.yml build

# 2. Push to registry
docker tag accounting-system:latest registry.com/accounting:1.0.0
docker push registry.com/accounting:1.0.0

# 3. Deploy to server
ssh production-server
docker-compose -f docker/docker-compose.prod.yml pull
docker-compose -f docker/docker-compose.prod.yml up -d

# 4. Run migrations
docker-compose -f docker/docker-compose.prod.yml exec app php migrate up
```

See [Docker Guide](docker/README.md) for detailed instructions.

---

## 📊 Current Status

**Phase:** Documentation Complete ✅ | Implementation Ready 🔄

| Component | Status |
|-----------|--------|
| Architecture Documentation | ✅ Complete |
| Domain Models (8/8) | ✅ Complete |
| API Specification | ✅ Complete |
| Database Schema | ✅ Complete |
| Docker Setup | ✅ Complete |
| CI/CD Pipelines | ✅ Complete |
| Implementation Plan | ✅ Complete |
| **Domain Implementation** | 🔄 Ready for TDD |

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](docs/CONTRIBUTING.md).

### Development Setup

1. Fork the repository
2. Create feature branch
3. Follow TDD approach
4. Ensure tests pass
5. Submit pull request

### Code Standards

- PSR-12 coding standard
- PHPStan level 8
- 80%+ code coverage
- Conventional commits

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👥 Authors

- **Master Architect** - Initial design and architecture

---

## 🙏 Acknowledgments

- Built for teaching professional software architecture
- Inspired by industry best practices
- Domain-Driven Design community
- Clean Architecture principles

---

## 📮 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/your-repo/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-repo/discussions)

---

**Happy Coding! 🚀**
