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

## Implemented (Phase 6)

- **Invoice/InvoiceLine/Payment/Refund/InvoiceSequence** (`App\Domain\Core\Models`). State machine (`Invoice::TRANSITIONS`): `draft → finalized → {partially_paid, paid, void} → refunded`. A `finalized` invoice with zero payments is the only state void is reachable from — the instant a payment lands, void is no longer reachable in the graph (use a refund instead), which is how "void only when unpaid" ends up enforced structurally rather than by an ad hoc flag check.
- **GST compliance (D-003)**: per-branch `gstin`/`state`, per-service `sac_code`, sequential invoice numbering (`{branch_code}/{financial_year}/{seq}`) unbroken per branch per financial year, CGST+SGST split from each service's `tax_rate_percent` (single-state v1 — see D-003's IGST deferral note). The counter (`invoice_sequences`) is incremented under `lockForUpdate()` — the same "lock a proxy row" concurrency pattern as the Appointment Engine (CLAUDE.md §24), since MySQL has no atomic-sequence primitive.
- **Lines only ever sell a Service** — no standalone/misc charge lines, since sellable Products don't exist until Phase 7. A line can auto-fill from a `completed` Appointment (reusing its already-agreed price, and an appointment can only ever be invoiced once) or be a standalone walk-in sale.
- **Payments are idempotent** by a client-generated UUID (`idempotency_key`, unique) — a double-submitted payment form returns the already-recorded payment rather than creating a duplicate. Overpayment beyond the remaining balance is rejected as a 409, never silently allowed.
- **Tips** are recorded on the Payment row (`tip_amount`) but never counted toward the invoice's taxable total — a tip is a gratuity to staff, not salon revenue.
- **Customer erasure never corrupts a finalized invoice**: `customer_name`/`customer_phone` are snapshotted onto the Invoice at creation, independent of the live Customer record, so a later DPDP erasure (Phase 3) leaves historical invoices intact (CLAUDE.md §36).
- **Permissions**: `invoices.view/create/void`, `payments.create`, `refunds.create`. Manager gets view/create/payments (POS is explicitly an operational permission per docs/04-RBAC.md); void/refund are Owner-only by default, a deliberate security-conscious default given no explicit rule either way.
- **Deliberately deferred, not attempted in this phase**: real payment gateway integration (v1 is manual recording — "front desk logs paid via UPI, ref #..."), credit notes (needs Wallet, Phase 8, to have anywhere to actually redeem a credit), cross-state IGST determination (D-003 — single-state CGST+SGST only for now), e-Invoicing/IRN (deferred until a tenant crosses the statutory turnover threshold), and Cash Register (Phase 10) / Commission (Phase 9) / Loyalty-Wallet-GiftCard redemption at POS (Phase 8) — none of those modules exist yet for POS to hook into.

## Expenses / Cash Register

Expense categories, branch expenses, vendor, tax, payment method, attachment, approval where configured. Cash register tracks opening cash, cash sales/expense/refund, cash in/out, expected vs. actual closing, and the difference.

## Commission

Service/product/package/membership commission; fixed, percentage, or slab rules; targets, incentives. Calculated only from authoritative finalized transaction data — never trust UI-submitted commission values. Refunds/voids adjust commission per business rules.

## Loyalty / Wallet

Both are transaction ledgers, not mutable balances. Every wallet change carries type, amount, reference, timestamp, actor/system source, and reason where appropriate. Concurrent redemption is protected against double-spend on both.

## Gift Cards / Vouchers

Issuance, value, balance, redemption, expiry, cancellation where legally/business appropriate.

## Expand incrementally as each sub-area's phase begins (6 / 8 / 9 / 10). D-002 and D-003 in [decisions/README.md](../decisions/README.md) must be resolved before Phase 6.
