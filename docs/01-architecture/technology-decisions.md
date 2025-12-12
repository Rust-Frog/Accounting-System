# Architecture Decision Records (ADRs)

## ADR-001: PHP as Backend Language
**Status:** Accepted
**Decision:** Use PHP 8.2+ with modern practices
**Rationale:** Required by project constraints, supports interface-based design

## ADR-002: No Framework Initially
**Status:** Accepted
**Decision:** Start with vanilla PHP, modular structure
**Rationale:** Learn architecture patterns deeply, avoid framework lock-in

## ADR-003: MySQL as Primary Database
**Status:** Accepted
**Decision:** Use MySQL 8.0
**Rationale:**
- Student-friendly (widely taught in schools)
- ACID compliance (critical for accounting)
- Familiar to most students
- Standard in educational institutions

## ADR-004: Hybrid Event Architecture
**Status:** Accepted
**Decision:** Event Sourcing for critical domains, traditional for others
**Event Sourced:** Transaction Processing, Ledger & Posting, Approval Workflow
**Traditional:** Identity, Company Management, Chart of Accounts

## ADR-005: Hexagonal Architecture
**Status:** Accepted
**Decision:** Strict hexagonal architecture (ports & adapters)
**Rationale:** Business logic isolated, easy to test, future-proof

## ADR-006: Documentation-First Approach
**Status:** Accepted
**Decision:** Write docs BEFORE code
**Rationale:** Previous project suffered from no architecture, forces design thinking

## ADR-007: Comprehensive CI/CD from Day One
**Status:** Accepted
**Decision:** GitHub Actions from the start
**Pipeline:** Linting (PSR-12), Type checking (PHPStan level 8), Unit tests, Security scanning

## ADR-008: Test-Driven Development (TDD)
**Status:** Accepted
**Decision:** Write tests BEFORE implementation
**Rationale:** Previous project had zero tests, TDD forces good design
