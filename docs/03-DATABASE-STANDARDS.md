# Database Standards

Full authority: `CLAUDE.md` §18–24, §42(Financial), §45–46, §48, §53.

## Indexing

Foreign keys, unique constraints, composite indexes, and explicit relationships where appropriate. Every tenant-owned table considers indexes beginning with `tenant_id`, based on actual query patterns — don't add indexes blindly.

Common: `(tenant_id, branch_id)`, `(tenant_id, status)`, `(tenant_id, created_at)`.
Appointment-specific: `(tenant_id, branch_id, appointment_date)`, `(tenant_id, employee_id, appointment_date)`.

## Primary keys

One consistent strategy project-wide — never changed module by module.

> **Proposed default (see [decisions/README.md](decisions/README.md) — confirm before Phase 0 migrations are written):** unsigned `bigint` auto-increment internal PKs everywhere, plus a separate ULID/UUID public-reference column on externally-exposed entities (invoice numbers, API resources, booking references). Internal keys are never exposed in tenant-facing URLs/APIs.

## Money

Never FLOAT/DOUBLE.

> **Proposed default:** `DECIMAL(12,2)`, single currency per tenant, round-half-up applied only to final calculated totals (never intermediate values). See [decisions/README.md](decisions/README.md) for the multi-currency question.

Server always calculates: subtotal, discounts, taxes, commissions, refunds, wallet impact, loyalty impact, totals. Browser-submitted totals are never trusted.

## Tax & invoicing

See `CLAUDE.md` §21 and [decisions/README.md](decisions/README.md) — target country/jurisdiction must be confirmed before Phase 6, since it determines invoice numbering rules, tax fields (GST/HSN/SAC if India), and per-branch tax registration needs.

## Time & timezones

Store canonical timestamps consistently; tenant timezone is explicitly configured and used for all display conversions. Never rely on server local timezone for business logic. Branch-level timezone support may be introduced later — appointment scheduling must respect it if so.

## Transactions & concurrency

Use `DB::transaction()` for: checkout, invoice finalization, payment, refund, stock transfer, package redemption, wallet operation, loyalty redemption, membership purchase, commission finalization. Roll back fully on any critical step failure. Don't call external APIs inside long-running transactions unless required.

Race conditions to explicitly guard (locking / unique constraints / idempotency / atomic updates): appointment booking, room allocation, resource allocation, stock decrement, package redemption, gift card redemption, wallet redemption, loyalty redemption, payment processing, invoice numbering. Never assume sequential request order.

## Financial & inventory integrity

- Finalized financial records are never silently edited or deleted — use void / reversal / refund / credit note / adjustment, and make every change traceable.
- Every stock change produces a stock movement (`PURCHASE`, `SALE`, `SERVICE_CONSUMPTION`, `TRANSFER_IN`, `TRANSFER_OUT`, `ADJUSTMENT`, `RETURN`, `DAMAGE`, `EXPIRY`) — stock balance must be reconstructable from its ledger, not treated as an independently mutable column.
- Loyalty and wallet are transaction ledgers, not mutable balances.

## Soft deletes

Used selectively — not as a substitute for a proper business lifecycle state machine. Financial ledgers and audit history are generally immutable, not soft-deletable.

## Migrations

Never edit an already-deployed migration to change production schema — write a new one. Consider existing production data before adding non-null columns, unique constraints, foreign keys, or enum-like constraints. Ensure safe rollback where feasible.
