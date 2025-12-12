# Event Catalog

Complete list of domain events in the system.

## Naming Convention
- Past tense (event already happened)
- Domain-specific language
- Include aggregate ID in payload

---

## Core Events

### TransactionCreated
```json
{
  "eventId": "uuid",
  "occurredAt": "2025-12-12T10:30:00Z",
  "transactionId": "uuid",
  "companyId": "uuid",
  "lines": [...],
  "totalAmount": "decimal",
  "createdBy": "uuid"
}
```

### TransactionPosted
```json
{
  "eventId": "uuid",
  "transactionId": "uuid",
  "postedBy": "uuid",
  "balanceChanges": [{
    "accountId": "uuid",
    "previousBalance": "decimal",
    "newBalance": "decimal"
  }]
}
```

### AccountBalanceChanged
```json
{
  "eventId": "uuid",
  "accountId": "uuid",
  "previousBalance": "decimal",
  "newBalance": "decimal",
  "causedBy": "transactionId"
}
```

### UserAuthenticated
```json
{
  "eventId": "uuid",
  "userId": "uuid",
  "ipAddress": "string",
  "sessionId": "uuid"
}
```

---

## Event Subscriptions

| Event | Consumed By |
|-------|-------------|
| TransactionPosted | Ledger & Posting, Financial Reporting, Audit Trail |
| AccountBalanceChanged | Financial Reporting, Audit Trail |
| UserAuthenticated | Audit Trail |
| ALL | Audit Trail |
