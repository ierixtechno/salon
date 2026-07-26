# POS Module

**Phase:** 6 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Point of sale checkout — the second highest-stakes concurrency/financial surface after Appointments.

## Core entities / must support

Services, products, packages, memberships, gift cards, discounts, tax, loyalty redemption, wallet redemption, split payment, tips.

## Key business rules

Financial workflow order (`.claude/skills/beauty-saas-development/SKILL.md` §10):

```
authorize → validate → resolve authoritative prices → calculate totals server-side →
begin transaction → lock records where necessary → write financial records →
write dependent ledgers → write audit event → commit → dispatch non-critical side effects
```

Never send a notification before the financial transaction commits. Never trust browser-calculated totals — see [03-DATABASE-STANDARDS.md](../03-DATABASE-STANDARDS.md) and [FINANCE.md](FINANCE.md) for invoice/payment/refund detail.

## Implemented (Phase 6)

Services-only checkout: `App\Http\Controllers\Core\InvoiceController` builds a draft sale (branch + customer), adds lines (from a completed Appointment or a standalone service pick), finalizes it (`CheckoutSale` — assigns the GST invoice number, locks totals), then accepts split payments (`RecordPayment`, idempotent) and tips. Discounts are a manual flat amount per line, staff-entered but server-validated (never exceeding the line's pre-discount value) — no discount-code/coupon system was built, since none was requested.

**Products, packages, memberships, gift cards, loyalty redemption, and wallet redemption are not supported at POS yet** — none of those Domain modules exist until Phase 7 (Products/Inventory) and Phase 8 (Packages/Membership/Loyalty/Wallet/Gift Cards). This is the natural phase-ordering constraint, not an oversight; POS will grow additional line-item types as each module ships, following the same server-side price-resolution pattern already established for services.

See [FINANCE.md](FINANCE.md) for the full Invoice/Payment/Refund implementation detail.
