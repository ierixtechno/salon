# Finance Module (Invoice, Payment, Refund, Expense, Cash Register, Commission, Loyalty, Wallet, Gift Cards)

**Phase:** 6 (Invoice/Payment/Refund), 8 (Loyalty/Wallet/Gift Cards), 9 (Commission), 10 (Expenses/Cash Register) · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Every module where money moves. The single highest-scrutiny area of the whole platform — see [03-DATABASE-STANDARDS.md](../03-DATABASE-STANDARDS.md) and [05-SECURITY.md](../05-SECURITY.md) for the cross-cutting rules; this doc indexes the finance-specific entities.

## Invoice

States: draft, finalized, paid, partially paid, void, refunded. Finalized invoices are never silently modified/deleted — void, refund, credit note, or adjustment only. Invoice numbering is concurrency-safe and (pending D-003) must satisfy jurisdiction-specific statutory numbering rules.

## Payments

Extensible methods: cash, card, UPI, bank transfer, wallet, gift card. External gateways sit behind provider interfaces/adapters. Idempotent — no duplicate payment entries from refresh/retry/duplicate webhook/double-click.

## Refunds

Full refund, partial refund, credit note. A refund is a dedicated workflow, never `payment_status = refunded` — it must correctly reverse financial entries, inventory, commission, loyalty, wallet, and package/membership usage where applicable (`.claude/skills/beauty-saas-development/SKILL.md` §14).

## Expenses / Cash Register

Expense categories, branch expenses, vendor, tax, payment method, attachment, approval where configured. Cash register tracks opening cash, cash sales/expense/refund, cash in/out, expected vs. actual closing, and the difference.

## Commission

Service/product/package/membership commission; fixed, percentage, or slab rules; targets, incentives. Calculated only from authoritative finalized transaction data — never trust UI-submitted commission values. Refunds/voids adjust commission per business rules.

## Loyalty / Wallet

Both are transaction ledgers, not mutable balances. Every wallet change carries type, amount, reference, timestamp, actor/system source, and reason where appropriate. Concurrent redemption is protected against double-spend on both.

## Gift Cards / Vouchers

Issuance, value, balance, redemption, expiry, cancellation where legally/business appropriate.

## Expand incrementally as each sub-area's phase begins (6 / 8 / 9 / 10). D-002 and D-003 in [decisions/README.md](../decisions/README.md) must be resolved before Phase 6.
