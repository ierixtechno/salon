# Organization Module

**Phase:** 2 · **Domain:** Core · **Tenant scope:** tenant-scoped

## Purpose

The tenant's own business profile and global settings — the "front door" of a tenant's account, set up once at onboarding and revisited rarely.

## Core entities

- Business profile (name, logo, contact details)
- Business settings (currency, tenant timezone, business hours defaults)
- Tax configuration (see [03-DATABASE-STANDARDS.md](../03-DATABASE-STANDARDS.md) and `CLAUDE.md` §21 — pending target-market decision, [decisions/README.md](../decisions/README.md) D-003)
- Policies (cancellation policy, refund policy text, etc. — display/reference data, not enforcement logic)

## Key business rules

- Tenant timezone must be explicitly configured before appointment scheduling goes live for that tenant — it is the basis for all display-time conversions (`CLAUDE.md` §22).
- Currency is set once per tenant under the current single-currency assumption (D-002) — changing it after transactions exist is an unsupported operation, not just a settings tweak.

## Expand when Phase 2 begins

This stub covers the entities; full field-level spec, validation rules, and the onboarding wizard flow should be filled in at the start of Phase 2.
