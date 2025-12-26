# ADR-001: Redis-Only Session Storage

## Status

Accepted

## Context

The database schema (`docker/mysql/init.sql`) includes a `sessions` table, but the application uses Redis for session storage via `SessionAuthenticationService`. This creates confusion about where sessions are actually stored.

## Decision

Sessions are stored exclusively in Redis for the following reasons:

1. **Performance** - No database queries for session validation on every request
2. **Automatic TTL Expiration** - Redis handles session expiry natively
3. **Horizontal Scaling** - Shared Redis instance supports multiple application instances
4. **Statelessness** - Application servers remain stateless

### Implementation Details

- `SessionAuthenticationService` uses `Predis\Client` for Redis operations
- Session tokens are stored with configurable TTL (default: 8 hours)
- Session data includes: user_id, role, company_id, created_at, expires_at

## Consequences

### Positive

- Faster session lookups (in-memory vs disk)
- Automatic cleanup of expired sessions
- Easy horizontal scaling

### Negative

- Session data is lost if Redis restarts without persistence
- Additional infrastructure dependency (Redis)

### Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Redis data loss | Enable Redis persistence (RDB/AOF) for production |
| Redis unavailable | Implement circuit breaker pattern, graceful degradation |

## Migration Path

The `sessions` table in `init.sql` is unused legacy code from an earlier design. It remains for now to avoid breaking existing deployments, but can be safely removed in a future schema cleanup.

## Related

- `src/Infrastructure/Service/SessionAuthenticationService.php`
- `src/Domain/Identity/Service/AuthenticationServiceInterface.php`
- `docker/mysql/init.sql` (lines 32-43: unused sessions table)

