# Architecture & Business Decisions Log

Per `CLAUDE.md` §70 and `.claude/skills/beauty-saas-development/SKILL.md` §47: ambiguities affecting security, tenancy, money, inventory, permissions, subscriptions, historical data, or API compatibility are recorded here rather than silently decided. Once confirmed, a decision becomes binding under `CLAUDE.md` §71 (do not rewrite without explicit approval).

Status values: **PROPOSED** (default suggested, awaiting confirmation) · **CONFIRMED** (binding) · **SUPERSEDED** (replaced — old entry kept for history, never deleted).

---

## D-001 — Primary key strategy

**Status:** PROPOSED
**Question:** Internal PK format across all tables.
**Proposed default:** Unsigned `bigint` auto-increment internal PKs everywhere; separate ULID/UUID public-reference column on entities exposed externally (invoices, API resources, booking references). Internal keys never appear in tenant-facing URLs/APIs.
**Affects:** Every migration from Phase 0 onward. Blocks nothing today (default is in use); revisiting later is expensive (`CLAUDE.md` §71).

## D-002 — Money representation & currency

**Status:** CONFIRMED
**Question:** Storage format for monetary values, and whether multi-currency (per tenant or per branch) is required.
**Decision:** `DECIMAL(12,2)`, single currency per tenant (set at onboarding, INR by default), round-half-up applied only at final calculated totals (never to intermediate values). Multi-currency is not supported — a tenant's branches all bill in that one tenant currency.
**Affects:** Phase 6 (POS/Invoice/Payment/Refund) schema onward.

## D-003 — Target country / tax & invoice compliance

**Status:** CONFIRMED — India, full GST compliance, single-state assumption for v1
**Question:** Which country/countries is this platform launching in, and how GST-compliant must invoices be?
**Decision:**
- India is the sole target market (consistent with D-004's DPDP Act confirmation).
- **Full GST compliance from day one**: per-branch GSTIN, HSN codes on products / SAC codes on services, GST-compliant sequential invoice numbering (unbroken, per branch, per financial year), CGST/SGST/IGST determination on every invoice line.
- **Single-state assumption for v1**: a tenant's branches are assumed to all operate in the same state as their registered GSTIN, so v1 only needs to implement CGST+SGST (branch state = place of supply). Full cross-state place-of-supply determination (→ IGST) is **not built yet** — the schema (per-branch GSTIN, place-of-supply field) is shaped so this can be added later without a rewrite, but the actual IGST-vs-CGST/SGST branching logic is deferred until a tenant genuinely operates cross-state.
- **e-Invoicing/IRN**: deferred entirely — out of scope until a real tenant crosses the applicable turnover threshold. Schema must not preclude adding it later.
- **TDS/TCS on supplier payments**: deferred to Phase 7 (Suppliers/Purchasing), not relevant to Phase 6's customer-facing invoices.
**Affects:** Phase 6 invoice/tax schema onward. Revisit the single-state assumption before supporting a tenant with multi-state branches (`CLAUDE.md` §71 — don't casually change the tax-determination architecture once real invoices exist).

## D-004 — Data protection regime for sensitive customer data

**Status:** CONFIRMED — India's Digital Personal Data Protection (DPDP) Act, 2023
**Decision:** The Customer module (Phase 3) and vertical consultation records (Phase 4) are designed against DPDP: consent is tracked as an auditable ledger (not a single mutable flag), erasure requests **anonymize the customer record in place** rather than deleting the row (financial/appointment/audit history will reference `customer_id` from Phase 5/6 onward and must never be left with a broken foreign key — CLAUDE.md §36/§45), and automatic time-based retention purging is deliberately deferred (see note below) rather than built speculatively.
**Not yet built:** Automatic retention-period purging (a scheduled job enforcing "don't keep data past necessity"). DPDP requires *not retaining longer than necessary*, but with no real usage data yet, a manual erasure workflow satisfies the immediate requirement; revisit automatic purging once real retention patterns exist (candidate: Phase 14, Security hardening).
**Affects:** Phase 3 (Customer CRM, this phase) and Phase 4 (vertical consultation records / before-after photos — will reuse the same consent-ledger and erasure pattern).

## D-005 — Payroll scope

**Status:** CONFIRMED (default assumption, revisit if wrong)
**Decision:** Full payroll processing (salary computation, statutory deductions like PF/ESI, payslips) is **out of scope**. Only attendance, leave, and commission are tracked, for operational/incentive purposes.
**Affects:** Phase 9. Flag before Phase 9 starts if this assumption is wrong.

## D-006 — GST treatment of prepaid Package/Membership/Gift Card sales

**Status:** PROPOSED (default in effect — see below)
**Question:** Under Indian GST, does a prepaid package/membership/gift-card sale attract GST at the moment of sale (advance payment for a future service), at the moment of redemption, or is the incoming amount treated as a security-deposit-like liability with no GST event until the underlying service is actually rendered? This is genuinely jurisdiction- and structure-specific and was not going to be guessed (same weight as D-003).
**Default in effect:** Package/Membership/Gift Card purchases (Phase 8) are recorded as simple payment-captured sales — `CustomerPackage`/`CustomerMembership`/`GiftCard` rows with `price_paid`/`purchase_method`/`purchase_reference` — entirely outside the GST-compliant Invoice/`InvoiceLine` engine built in Phase 6. No invoice number, no CGST/SGST line is generated for these sales. Redemption avoids the question rather than resolving it: Package redemption never creates an invoice line at all (a pure ledger entry, `PackageRedemption`); Membership discount is a normal server-computed discount on an already-taxed service line (no new tax question); Loyalty/Wallet/Gift Card redemption pay down an already-fully-taxed invoice's `grand_total` (also no new tax question).
**Affects:** Phase 8 schema (`customer_packages`, `customer_memberships`, `gift_cards` all lack `invoice_id`/GST fields by design) and any future GST/reporting work that needs to reconcile "money collected" against "revenue recognized" for these three sale types. Revisit before this platform is used by a tenant whose accountant needs GST-compliant invoices for package/membership/gift-card sales specifically (`CLAUDE.md` §71 — don't retrofit statutory numbering onto these sales after real production data exists without a deliberate migration plan).

---

*Add new entries at the bottom as new ambiguities surface during implementation. Never delete a superseded entry — mark it SUPERSEDED and link to its replacement.*
