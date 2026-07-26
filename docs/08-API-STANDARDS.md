# API Standards

Full authority: `CLAUDE.md` §38, §42; `.claude/skills/beauty-saas-development/SKILL.md` §24–25, §27, §31.

## Authentication & scope

Every API request is authenticated, tenant-scoped, permission-checked, rate-limited, validated, and paginated where it returns collections. Production responses never leak internal exceptions/stack traces (see [06-ERROR-HANDLING.md](06-ERROR-HANDLING.md) for the response shape).

## External integrations

Every third-party integration (payment, WhatsApp, SMS, email, storage) sits behind an interface/adapter (`PaymentProvider`, `WhatsAppProvider`, `SmsProvider`, `EmailProvider`, `StorageProvider`). Adapters: configure credentials via environment, set timeouts, catch and translate provider errors to domain-level failures, log safely, retry only when safe, support idempotency. Provider exceptions are never surfaced directly to users.

## Webhooks

```
receive raw request → verify signature → validate provider → validate event →
identify account/tenant safely → check idempotency → process transactionally →
store processing result → return provider-compatible response
```

Never trust a `tenant_id` carried inside an unsigned webhook payload — resolve tenant from the verified provider/account mapping instead.

## Idempotency

Required wherever duplicate submission is plausible: payments, refunds, webhook processing, package redemption, gift card redemption, wallet operations, and other financially-consequential booking actions. Retrying a request must never produce duplicate financial effects.

## Public/unauthenticated endpoints (Phase 13+)

See `CLAUDE.md` §75 — public booking endpoints get additional rate limiting, information minimization, and bot/spam protection beyond the baseline above.
