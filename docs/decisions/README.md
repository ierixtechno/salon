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

**Status:** PROPOSED
**Question:** Storage format for monetary values, and whether multi-currency (per tenant or per branch) is required.
**Proposed default:** `DECIMAL(12,2)`, single currency per tenant, round-half-up applied only at final calculated totals.
**Affects:** Phase 6 (POS/Invoice/Payment/Refund) schema. Confirm before Phase 6 begins — revisiting after real financial data exists is high-risk.

## D-003 — Target country / tax & invoice compliance

**Status:** PRESUMPTIVELY CONFIRMED — India (see D-004; formal GST/invoice specifics still open)
**Question:** Which country/countries is this platform launching in?
**Decision so far:** The user confirmed India's DPDP Act for D-004, which strongly implies India as the target market. Treat "India" as confirmed for Customer/data-privacy purposes now. The *tax/invoicing* specifics below are **still open** and must be nailed down before Phase 6:
- GST-compliant sequential invoice numbering (per branch, per financial year)
- CGST/SGST vs IGST logic, HSN/SAC codes, per-branch GSTIN
- e-Invoicing/IRN thresholds
- TDS/TCS on supplier payments
**Affects:** Phase 6 invoice/tax schema. **Must be confirmed before Phase 6 starts** — retrofitting statutory invoice numbering onto live financial data is high-risk (`CLAUDE.md` §21).

## D-004 — Data protection regime for sensitive customer data

**Status:** CONFIRMED — India's Digital Personal Data Protection (DPDP) Act, 2023
**Decision:** The Customer module (Phase 3) and vertical consultation records (Phase 4) are designed against DPDP: consent is tracked as an auditable ledger (not a single mutable flag), erasure requests **anonymize the customer record in place** rather than deleting the row (financial/appointment/audit history will reference `customer_id` from Phase 5/6 onward and must never be left with a broken foreign key — CLAUDE.md §36/§45), and automatic time-based retention purging is deliberately deferred (see note below) rather than built speculatively.
**Not yet built:** Automatic retention-period purging (a scheduled job enforcing "don't keep data past necessity"). DPDP requires *not retaining longer than necessary*, but with no real usage data yet, a manual erasure workflow satisfies the immediate requirement; revisit automatic purging once real retention patterns exist (candidate: Phase 14, Security hardening).
**Affects:** Phase 3 (Customer CRM, this phase) and Phase 4 (vertical consultation records / before-after photos — will reuse the same consent-ledger and erasure pattern).

## D-005 — Payroll scope

**Status:** CONFIRMED (default assumption, revisit if wrong)
**Decision:** Full payroll processing (salary computation, statutory deductions like PF/ESI, payslips) is **out of scope**. Only attendance, leave, and commission are tracked, for operational/incentive purposes.
**Affects:** Phase 9. Flag before Phase 9 starts if this assumption is wrong.

---

*Add new entries at the bottom as new ambiguities surface during implementation. Never delete a superseded entry — mark it SUPERSEDED and link to its replacement.*
