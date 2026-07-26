# Security

Full authority: `CLAUDE.md` §11–13, §25–38 (renumbered §21-comment: see §33–36 for privacy/storage additions), §47.

## Authentication

Laravel's supported mechanisms only — never custom cryptography. Secure password hashing, CSRF protection, session regeneration after login, logout invalidation, login rate limiting, password reset expiry, secure cookies + HTTPS in production, email verification where configured.

## Authorization

Middleware + Policies + Gates/permissions, always with tenant and branch context. Controllers are never the sole authorization layer — see [04-RBAC.md](04-RBAC.md) for the full access evaluation order.

## Validation

Every external input is untrusted. Form Requests validate type, format, range, enum, length, ownership, tenant scope, branch scope, business state. Client-side validation is UX only.

## Mass assignment

Never `request->all()` into a model. Explicit validated/allowed fields only. Never mass-assignable: `tenant_id`, role, permissions, subscription status, invoice totals, payment status, wallet balance, loyalty balance, commission amount.

## Injection & output

- SQL: Eloquent/query builder parameter binding always; raw SQL needs documented justification.
- XSS: escape by default; never render arbitrary customer/staff HTML.
- CSRF: Laravel CSRF on all state-changing browser requests; never disabled globally — webhooks use provider signature verification instead.

## IDOR

Possession of a record ID never implies access. Every resource fetch/mutation verifies tenant ownership, branch authorization, permission, and resource-specific policy — for both web and API routes. Cross-tenant IDs resolve as inaccessible without revealing whether the record exists.

## File uploads

Validate file type, MIME type, extension, size, authorization, storage destination. Server-generates filenames — never trust the uploaded filename or concatenate it into a path (path traversal). Sensitive files are never publicly accessible; use authorized/signed download endpoints.

## Tenant storage quota

Shared hosting has finite disk. Track per-tenant (and optionally per-branch) storage consumption, enforce a plan-configurable limit, warn near-quota, and reject over-quota uploads with a clear business error — not a 500. Super Admin needs visibility into per-tenant usage. Keep the abstraction swappable for object storage later.

## Sensitive information & secrets

Never log passwords, reset tokens, API secrets, payment credentials, access tokens, private keys, or unnecessary full customer data. Secrets live in environment configuration; never committed to Git.

## Data privacy & retention

The Beauty Parlour module stores skin/hair consultation notes and before/after photos captured with consent. This data needs:

- Recorded consent (who, when, purpose/scope) before storage
- A defined, non-indefinite retention period
- A customer erasure workflow scoped to non-financial, non-audit data — erasure must never touch finalized invoices or audit logs (those stay immutable per [03-DATABASE-STANDARDS.md](03-DATABASE-STANDARDS.md))
- Consent-respecting marketing communication with opt-out, and (if targeting India) confirmed DLT/TRAI SMS registration and WhatsApp Business API opt-in before Phase 11

**Decision needed** — see [decisions/README.md](decisions/README.md) — before Phase 3/4.

## Payment security

Never store raw card details — provider tokenization/hosted flows only. Webhooks verify provider signature, expected event, amount, currency, merchant/account context, and idempotency; duplicate delivery must never double-process a payment.

## API security

Authenticated, tenant-scoped, permission-enforced, rate-limited, validated, paginated. Production APIs never leak internal exceptions or stack traces.

## Security review checklist (run before marking any endpoint/feature complete)

- [ ] Tenant isolation
- [ ] Branch isolation
- [ ] Authorization
- [ ] Validation
- [ ] Mass assignment
- [ ] SQL injection
- [ ] XSS
- [ ] CSRF
- [ ] IDOR
- [ ] Upload security
- [ ] Secret handling
- [ ] Rate limiting where appropriate
- [ ] Financial integrity
- [ ] Auditability
- [ ] Safe errors
- [ ] Logging
