# Error Handling

Full authority: `CLAUDE.md` §39–41; `.claude/skills/beauty-saas-development/SKILL.md` §26–29.

## Categories

`VALIDATION` · `AUTHENTICATION` · `AUTHORIZATION` · `NOT FOUND` · `CONFLICT` · `BUSINESS RULE` · `EXTERNAL SERVICE` · `SYSTEM`

Expected business failures are never generic 500s:

| Situation | Category | HTTP |
|---|---|---|
| Invalid appointment date | Validation | 422 |
| Unauthenticated | Authentication | 401 (API) / login redirect (web) |
| Authenticated, not permitted | Authorization | 403 |
| Resource inaccessible/absent | Not Found | 404 |
| Slot booked / room occupied / duplicate invoice op | Conflict | 409 |
| Membership expired / package exhausted / insufficient stock | Business Rule | structured failure |
| Provider failure | External Service | safe retry/failure state, no provider internals exposed |
| Unexpected | System | generic safe message, log technical detail |

## JSON API error shape

```json
{
  "success": false,
  "code": "APPOINTMENT_SLOT_UNAVAILABLE",
  "message": "The selected appointment slot is no longer available.",
  "errors": {}
}
```

Never expose SQL, stack traces, class names, server paths, or environment values in a response — those go to server-side logs only, with a correlation/request ID surfaced to the user instead.

## Exception handling discipline

No blanket `catch (Exception $e)` that swallows errors into a success-shaped response. Catch only to: convert an infrastructure error into a domain error, perform cleanup, add safe context, or implement an intentional fallback. Everything else reaches centralized exception handling and gets logged.

## Logging context

Include `tenant_id`, `branch_id`, `user_id`, `action`/`operation`, `entity_type`, `entity_id`, `request_id` on every logged failure. Never log secrets or credentials (see [05-SECURITY.md](05-SECURITY.md)).
