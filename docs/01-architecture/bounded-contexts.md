# Bounded Contexts (DDD)

## Overview

The accounting system is divided into 8 bounded contexts, each with clear responsibilities and boundaries.

---

## 1. Identity & Access Management
- **Responsibility:** User authentication, authorization, role management
- **Events Published:** `UserRegistered`, `UserAuthenticated`, `UserDeactivated`
- **Domain Rules:** Passwords hashed (bcrypt), sessions expire, deactivated users cannot login

## 2. Company Management
- **Responsibility:** Multi-tenant company data, company lifecycle
- **Events Published:** `CompanyCreated`, `CompanyActivated`, `CompanyDeactivated`
- **Domain Rules:** Each tenant belongs to one company, currency code must be valid ISO 4217

## 3. Chart of Accounts
- **Responsibility:** Account structure, account types, account hierarchy
- **Events Published:** `AccountCreated`, `AccountActivated`, `AccountDeactivated`
- **Domain Rules:** Account codes unique per company, cannot deactivate accounts with non-zero balance

## 4. Transaction Processing
- **Responsibility:** Transaction creation, validation, double-entry rules
- **Events Published:** `TransactionCreated`, `TransactionValidated`, `TransactionPosted`, `TransactionVoided`
- **Domain Rules:** Debits = credits, minimum 2 lines, posted transactions cannot be modified

## 5. Ledger & Posting
- **Responsibility:** Account balance management, posting transactions to ledger
- **Events Published:** `LedgerUpdated`, `AccountBalanceChanged`, `NegativeBalanceDetected`
- **Domain Rules:** Balance changes follow normal balance logic, accounting equation must remain balanced

## 6. Financial Reporting
- **Responsibility:** Generate financial reports, maintain report cache
- **Events Published:** `ReportGenerated`
- **Events Consumed:** `AccountBalanceChanged` → Invalidate cached reports

## 7. Audit Trail
- **Responsibility:** Immutable activity log, compliance tracking
- **Events Consumed:** ALL events from all contexts
- **Domain Rules:** Logs are immutable (append-only), every user action logged

## 8. Approval Workflow
- **Responsibility:** Transaction approval routing, approval tracking
- **Events Published:** `ApprovalRequested`, `ApprovalGranted`, `ApprovalDenied`
- **Domain Rules:** Only admins can approve, declined approvals require reason

---

## Context Map

```
┌─────────────┐
│  Identity   │
│  & Access   │
└──────┬──────┘
       │ provides authentication
       ↓
┌─────────────┐      ┌─────────────┐
│   Company   │─────→│  Chart of   │
│ Management  │      │  Accounts   │
└──────┬──────┘      └──────┬──────┘
       │                    │
       │                    │
       ↓                    ↓
┌─────────────┐      ┌─────────────┐
│Transaction  │─────→│   Ledger    │
│ Processing  │posts │  & Posting  │
└──────┬──────┘      └──────┬──────┘
       │                    │
       ↓                    ↓
┌─────────────┐      ┌─────────────┐
│  Approval   │      │  Financial  │
│  Workflow   │      │  Reporting  │
└─────────────┘      └─────────────┘
       │                    │
       └────────┬───────────┘
                │ all events
                ↓
         ┌─────────────┐
         │ Audit Trail │
         └─────────────┘
```
