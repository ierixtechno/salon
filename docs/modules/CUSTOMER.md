# Customer Module

**Phase:** 3 · **Domain:** Core · **Tenant scope:** tenant-scoped, identity shared across all enabled verticals

## Purpose

A single customer identity shared across Salon/Beauty Parlour/Spa — a customer who gets a haircut and a facial is one customer record, not two.

## Core entities

- `customers` — profile, contact info, tags, preferences, marketing consent flag (denormalized latest state), source, erasure timestamps.
- `customer_notes` — timestamped, staff-authored notes (append-only in spirit; never a single mutable text blob).
- `customer_consents` — an auditable ledger of consent grants/revocations per purpose (`service_delivery`, `marketing`, ...), per [decisions/README.md](../decisions/README.md) D-004.

Visit history, appointment history, purchase history, membership, packages, loyalty, and wallet are **not** built here — they're populated by their owning modules (Appointment Engine in Phase 5, POS/Invoice in Phase 6, Packages/Membership/Loyalty/Wallet in Phase 8) and will read from/relate to `customers` once those exist. Building placeholder relations to non-existent tables now would be premature.

## Key business rules

- Customer identity is shared across enabled business modules (`CLAUDE.md` §14) — do not fork per-vertical customer records.
- **D-004 (CONFIRMED — India's DPDP Act):** consent is a ledger, not a mutable flag. Erasure requests **anonymize the customer row in place** — they never delete it, since Phase 5/6 financial and appointment records will hold a foreign key to `customer_id` that must never dangle (`CLAUDE.md` §36/§45). Erasure is gated behind a narrow `customers.erase` permission (Owner only by default), and every erasure is audited.
- Sensitive data captured *later* (skin/hair consultation notes, before/after photos — [SALON.md](SALON.md)/[BEAUTY-PARLOUR.md](BEAUTY-PARLOUR.md), Phase 4) reuses this same consent-ledger and erasure pattern rather than inventing a second one.
- Global/customer search must enforce tenant isolation (`CLAUDE.md` §57).
- Automatic retention-period purging (DPDP: don't retain longer than necessary) is **deliberately deferred** — manual erasure satisfies the immediate requirement; a scheduled purge job is a candidate for Phase 14 once real retention patterns exist.
