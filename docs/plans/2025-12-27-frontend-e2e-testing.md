# Frontend End-to-End Testing Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Verify all frontend pages load correctly, API integrations work, and user flows complete successfully.

**Architecture:** Manual E2E testing using curl for API verification and browser-based page load testing. Each page will be tested for: HTML loading, CSS/JS assets, API calls, and interactive functionality.

**Tech Stack:** curl, Docker (accounting-nginx-dev on port 8080), Browser DevTools

---

## Prerequisites

Before testing, ensure:
1. Docker containers are running: `docker ps | grep accounting`
2. API is responding: `curl -s http://localhost:8080/api/v1/setup/status`
3. Valid auth token available for authenticated tests

---

## Task 1: Test Landing Page (index.html)

**Files:**
- Test: `public/index.html`
- JS: Inline script (setup redirect logic)

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/
```

Expected: `200`

**Step 2: Verify redirect logic calls setup API**

```bash
curl -s http://localhost:8080/api/v1/setup/status
```

Expected: JSON response with `success` field

**Step 3: Document result**

Record: Page loads, redirects to either `/setup.html` or `/login.html` based on setup status.

---

## Task 2: Test Login Page (login.html)

**Files:**
- Test: `public/login.html`
- CSS: `public/assets/css/app.css`
- JS: `public/assets/js/login.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/login.html
```

Expected: `200`

**Step 2: Verify CSS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/assets/css/app.css
```

Expected: `200`

**Step 3: Verify JS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/assets/js/login.js
```

Expected: `200`

**Step 4: Test login API endpoint**

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"wrong","otp_code":"000000"}'
```

Expected: 401/422 with error message (confirms API is responding)

**Step 5: Document login flow**

- Step 1: Enter username -> "Verify Identity" button
- Step 2: Enter password + OTP -> "Authenticate" button
- Success: Redirect to `/admin/dashboard/`
- Failure: Show error, clear OTP field

---

## Task 3: Test Setup Page (setup.html)

**Files:**
- Test: `public/setup.html`
- JS: `public/assets/js/setup.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/setup.html
```

Expected: `200`

**Step 2: Verify setup JS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/assets/js/setup.js
```

Expected: `200`

**Step 3: Test setup status API**

```bash
curl -s http://localhost:8080/api/v1/setup/status
```

Expected: JSON with `is_setup_required` field

---

## Task 4: Test Dashboard Page

**Files:**
- Test: `public/admin/dashboard/index.html`
- CSS: `public/admin/dashboard/dashboard.css`
- JS: `public/admin/dashboard/dashboard.js`
- Shared: `public/shared/js/api.js`, `public/shared/css/app.css`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/dashboard/
```

Expected: `200`

**Step 2: Verify dashboard CSS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/dashboard/dashboard.css
```

Expected: `200`

**Step 3: Verify dashboard JS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/dashboard/dashboard.js
```

Expected: `200`

**Step 4: Verify shared API client loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/js/api.js
```

Expected: `200`

**Step 5: Test dashboard stats API (requires auth)**

```bash
curl -s http://localhost:8080/api/v1/dashboard/stats \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with `todays_transactions`, `pending_approvals`, `gl_accounts`

**Step 6: Test activities API (requires auth)**

```bash
curl -s http://localhost:8080/api/v1/activities?limit=4 \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON array of recent activities

**Step 7: Document dashboard elements**

- Stats grid: Active Sessions, Today's Transactions, Companies, Operators
- Recent Activity panel (live feed)
- Quick Actions: Pending Approvals, Add Company, Add User, Generate Report
- System Status: Database, Authentication, Session, 2FA

---

## Task 5: Test Transactions Page

**Files:**
- Test: `public/admin/transactions/index.html`
- CSS: `public/admin/transactions/transactions.css`
- JS: `public/admin/transactions/transactions.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/transactions/
```

Expected: `200`

**Step 2: Verify transactions CSS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/transactions/transactions.css
```

Expected: `200`

**Step 3: Verify transactions JS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/transactions/transactions.js
```

Expected: `200`

**Step 4: Test transactions list API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/transactions?page=1&limit=20" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with transactions array and pagination

**Step 5: Document transaction features**

- List view with status filter (All, Draft, Pending, Posted, Voided)
- Create transaction modal (date, description, lines with debit/credit)
- Actions: Edit (draft only), Post (requires approval), Void (with reason)
- Balance validation: debits must equal credits

---

## Task 6: Test Chart of Accounts Page

**Files:**
- Test: `public/admin/accounts/index.html`
- CSS: `public/admin/accounts/accounts.css`
- JS: `public/admin/accounts/accounts.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/accounts/
```

Expected: `200`

**Step 2: Verify accounts CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/accounts/accounts.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/accounts/accounts.js
```

Expected: `200` for both

**Step 3: Test accounts list API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/accounts" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with accounts array

**Step 4: Document accounts features**

- List view grouped by type (Assets 1xxx, Liabilities 2xxx, Equity 3xxx, Revenue 4xxx, Expenses 5xxx)
- Create account (code, name, description, parent)
- Toggle active/inactive
- View account transactions

---

## Task 7: Test Journal Page

**Files:**
- Test: `public/admin/journal/index.html`
- CSS: `public/admin/journal/journal.css`
- JS: `public/admin/journal/journal.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/journal/
```

Expected: `200`

**Step 2: Verify journal CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/journal/journal.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/journal/journal.js
```

Expected: `200` for both

**Step 3: Test journal API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/journal" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with journal entries (posted transactions)

**Step 4: Document journal features**

- Chronological list of posted journal entries
- Each entry shows: date, transaction number, accounts, debits, credits
- Hash chain verification for integrity

---

## Task 8: Test Ledger Page

**Files:**
- Test: `public/admin/ledger/index.html`
- CSS: `public/admin/ledger/ledger.css`
- JS: `public/admin/ledger/ledger.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/ledger/
```

Expected: `200`

**Step 2: Verify ledger CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/ledger/ledger.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/ledger/ledger.js
```

Expected: `200` for both

**Step 3: Test ledger summary API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/ledger/summary" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with account balances summary

**Step 4: Document ledger features**

- Account selector dropdown
- Date range filter
- Running balance display
- Debit/credit columns

---

## Task 9: Test Trial Balance Report

**Files:**
- Test: `public/admin/reports/trial-balance/index.html`
- CSS: `public/admin/reports/trial-balance/trial-balance.css`
- JS: `public/admin/reports/trial-balance/trial-balance.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/trial-balance/
```

Expected: `200`

**Step 2: Verify trial-balance CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/trial-balance/trial-balance.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/trial-balance/trial-balance.js
```

Expected: `200` for both

**Step 3: Test trial balance API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/trial-balance" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with accounts, total_debits, total_credits (should be equal)

**Step 4: Document trial balance features**

- As-of date selector
- Account list with debit/credit columns
- Total row showing balanced debits = credits
- Print/Export functionality

---

## Task 10: Test Income Statement Report

**Files:**
- Test: `public/admin/reports/income-statement/index.html`
- CSS: `public/admin/reports/income-statement/income-statement.css`
- JS: `public/admin/reports/income-statement/income-statement.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/income-statement/
```

Expected: `200`

**Step 2: Verify income-statement CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/income-statement/income-statement.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/income-statement/income-statement.js
```

Expected: `200` for both

**Step 3: Test income statement API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/income-statement" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with revenues, expenses, net_income

**Step 4: Document income statement features**

- Period selector (start/end date)
- Revenue section (4xxx accounts)
- Expense section (5xxx accounts)
- Net Income calculation

---

## Task 11: Test Balance Sheet Report

**Files:**
- Test: `public/admin/reports/balance-sheet/index.html`
- CSS: `public/admin/reports/balance-sheet/balance-sheet.css`
- JS: `public/admin/reports/balance-sheet/balance-sheet.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/balance-sheet/
```

Expected: `200`

**Step 2: Verify balance-sheet CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/balance-sheet/balance-sheet.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/balance-sheet/balance-sheet.js
```

Expected: `200` for both

**Step 3: Test balance sheet API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/balance-sheet" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with assets, liabilities, equity (A = L + E)

**Step 4: Document balance sheet features**

- As-of date selector
- Assets section (1xxx accounts)
- Liabilities section (2xxx accounts)
- Equity section (3xxx accounts)
- Accounting equation verification

---

## Task 12: Test Users Management Page

**Files:**
- Test: `public/admin/users/index.html`
- JS: `public/admin/users/users.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/users/
```

Expected: `200`

**Step 2: Verify users JS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/users/users.js
```

Expected: `200`

**Step 3: Test users list API (requires auth, admin only)**

```bash
curl -s "http://localhost:8080/api/v1/users" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with users array

**Step 4: Document users features**

- List view with role/status filters
- Actions: Approve (pending), Decline (pending), Activate, Deactivate
- User details: username, email, role, registration status

---

## Task 13: Test Companies Page

**Files:**
- Test: `public/admin/companies/index.html`
- CSS: `public/admin/companies/companies.css`
- JS: `public/admin/companies/companies.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/companies/
```

Expected: `200`

**Step 2: Verify companies CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/companies/companies.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/companies/companies.js
```

Expected: `200` for both

**Step 3: Test companies list API (requires auth)**

```bash
curl -s "http://localhost:8080/api/v1/companies" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with companies array

**Step 4: Document companies features**

- List view with status indicators
- Create company modal (name, legal name, tax ID, address, currency)
- Actions: Activate, Suspend, Reactivate, Deactivate

---

## Task 14: Test Audit Log Page

**Files:**
- Test: `public/admin/audit/index.html`
- CSS: `public/admin/audit/audit-log.css`
- JS: `public/admin/audit/audit-log.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/audit/
```

Expected: `200`

**Step 2: Verify audit CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/audit/audit-log.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/audit/audit-log.js
```

Expected: `200` for both

**Step 3: Test audit logs API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/audit-logs?limit=50" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with logs array and pagination

**Step 4: Test audit stats API (requires auth + company)**

```bash
curl -s "http://localhost:8080/api/v1/companies/1/audit-logs/stats" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with by_category, by_severity, by_activity_type

**Step 5: Document audit features**

- Filters: date range, activity type, entity type, severity
- Log entry details: actor, action, entity, timestamp, IP address
- Hash chain integrity indicators

---

## Task 15: Test Settings Page

**Files:**
- Test: `public/admin/settings/index.html`
- CSS: `public/admin/settings/settings.css`
- JS: `public/admin/settings/settings.js`

**Step 1: Verify page loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/settings/
```

Expected: `200`

**Step 2: Verify settings CSS/JS load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/settings/settings.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/settings/settings.js
```

Expected: `200` for both

**Step 3: Test settings API (requires auth)**

```bash
curl -s "http://localhost:8080/api/v1/settings" \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with theme, localization, notifications settings

**Step 4: Document settings features**

- Theme: Dark/Light mode
- Localization: Language, timezone, date format
- Notifications: Email, browser
- Session: Timeout duration
- Security: Change password, OTP enable/disable, backup codes

---

## Task 16: Test Shared Components

**Files:**
- Test: `public/shared/js/api.js` (API client)
- Test: `public/shared/js/components.js` (UI components)
- Test: `public/shared/js/sidebar.js` (navigation)
- Test: `public/shared/js/ui.js` (UI utilities)
- Test: `public/shared/css/*.css` (shared styles)

**Step 1: Verify all shared JS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/js/api.js
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/js/components.js
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/js/sidebar.js
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/js/ui.js
```

Expected: `200` for all

**Step 2: Verify all shared CSS loads**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/css/app.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/css/layout.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/css/components.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/css/pages.css
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/shared/css/ui.css
```

Expected: `200` for all

**Step 3: Document shared component functionality**

- ApiClient: Token management, request/response handling, auto-redirect on 401
- Sidebar: Navigation highlighting, logout button
- UI utilities: Modals, toasts, loading states

---

## Task 17: Test Sidebar Navigation

**Step 1: Verify all nav links are valid**

Test each sidebar link resolves to a valid page:

```bash
# Main section
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/dashboard/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/transactions/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/accounts/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/journal/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/ledger/

# Reports section
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/trial-balance/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/income-statement/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/reports/balance-sheet/

# Administration section
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/users/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/companies/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/audit/
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/settings/
```

Expected: `200` for all

**Step 2: Document navigation structure**

- Main: Dashboard, Transactions, Chart of Accounts, Journal, Ledger
- Reports: Trial Balance, Income Statement, Balance Sheet
- Administration: Users, Companies, Activity (Audit), Settings

---

## Task 18: Test Authentication Flow End-to-End

**Step 1: Start at index.html**

```bash
curl -s http://localhost:8080/ -o /dev/null -w "%{redirect_url}"
```

Expected: Redirects to `/login.html` or `/setup.html`

**Step 2: Test login with valid credentials**

```bash
# Get valid token (requires real admin credentials)
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"REAL_PASSWORD","otp_code":"REAL_OTP"}'
```

Expected: JSON with `token`, `expires_at`, `user_id`

**Step 3: Test authenticated access**

```bash
export TOKEN="<token_from_step_2>"
curl -s http://localhost:8080/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

Expected: JSON with user details

**Step 4: Test logout**

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

Expected: Success response, token invalidated

---

## Task 19: Test Error Handling

**Step 1: Test 401 handling (no token)**

```bash
curl -s http://localhost:8080/api/v1/companies
```

Expected: 401 with "Authentication required" message

**Step 2: Test 401 handling (invalid token)**

```bash
curl -s http://localhost:8080/api/v1/companies \
  -H "Authorization: Bearer invalid_token_here"
```

Expected: 401 with authentication error

**Step 3: Test 404 handling**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/nonexistent-page
```

Expected: `404`

**Step 4: Test rate limiting**

```bash
# Hit login endpoint 6+ times quickly
for i in {1..7}; do
  curl -s -X POST http://localhost:8080/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"test","password":"test","otp_code":"000000"}' \
    -o /dev/null -w "%{http_code}\n"
done
```

Expected: First 5 return 4xx, 6th+ return `429` (rate limited)

---

## Task 20: Create Test Results Summary

**Step 1: Create results template**

Create file `docs/plans/2025-12-27-frontend-e2e-results.md`:

```markdown
# Frontend E2E Test Results

**Date:** 2025-12-27
**Tester:** Claude
**Environment:** Docker (localhost:8080)

## Summary

| Category | Total | Pass | Fail |
|----------|-------|------|------|
| Page Loads | 15 | ? | ? |
| Asset Loads | 30+ | ? | ? |
| API Endpoints | 20+ | ? | ? |
| User Flows | 5 | ? | ? |

## Detailed Results

### Landing & Auth
- [ ] index.html - Loads and redirects
- [ ] login.html - Loads with all assets
- [ ] setup.html - Loads with all assets

### Admin Pages
- [ ] Dashboard - Loads, APIs work
- [ ] Transactions - Loads, CRUD works
- [ ] Accounts - Loads, CRUD works
- [ ] Journal - Loads, list works
- [ ] Ledger - Loads, filters work

### Reports
- [ ] Trial Balance - Loads, generates
- [ ] Income Statement - Loads, generates
- [ ] Balance Sheet - Loads, generates

### Administration
- [ ] Users - Loads, actions work
- [ ] Companies - Loads, CRUD works
- [ ] Audit Log - Loads, filters work
- [ ] Settings - Loads, updates work

## Issues Found

1. [Issue description]
   - Page:
   - Expected:
   - Actual:
   - Severity:

```

**Step 2: Execute all tests and record results**

Run each task's curl commands and record pass/fail status.

**Step 3: Commit test plan and results**

```bash
git add docs/plans/
git commit -m "docs: add frontend E2E testing plan and results"
```

---

## Execution Notes

- Tasks 1-16: Can run independently (page load tests)
- Tasks 17-19: Require sequential flow (integration tests)
- Task 20: Run after all other tasks complete

**Estimated time per task:** 2-5 minutes
**Total estimated time:** 60-90 minutes

---

Plan complete and saved to `docs/plans/2025-12-27-frontend-e2e-testing.md`.

**Two execution options:**

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

**Which approach?**
