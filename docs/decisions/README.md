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

**Status:** OPEN — no default proposed, business decision required
**Question:** Which country/countries is this platform launching in? Indian bridal terminology (haldi, mehendi, sangeet) suggests India, but this needs explicit confirmation, since it determines:
- GST-compliant sequential invoice numbering (per branch, per financial year)
- CGST/SGST vs IGST logic, HSN/SAC codes, per-branch GSTIN
- e-Invoicing/IRN thresholds
- TDS/TCS on supplier payments
**Affects:** Phase 6 invoice/tax schema. **Must be confirmed before Phase 6 starts** — retrofitting statutory invoice numbering onto live financial data is high-risk (`CLAUDE.md` §21).

## D-004 — Data protection regime for sensitive customer data

**Status:** OPEN — no default proposed, business decision required
**Question:** Which data protection law applies (e.g., India's DPDP Act, or another jurisdiction)? Drives retention period defaults and the shape of the customer erasure workflow for consultation notes and before/after photos.
**Affects:** Phase 3 (Customer CRM) and Phase 4 (vertical consultation records). Confirm before those phases begin (`CLAUDE.md` §36).

## D-005 — Payroll scope

**Status:** CONFIRMED (default assumption, revisit if wrong)
**Decision:** Full payroll processing (salary computation, statutory deductions like PF/ESI, payslips) is **out of scope**. Only attendance, leave, and commission are tracked, for operational/incentive purposes.
**Affects:** Phase 9. Flag before Phase 9 starts if this assumption is wrong.

---

*Add new entries at the bottom as new ambiguities surface during implementation. Never delete a superseded entry — mark it SUPERSEDED and link to its replacement.*
