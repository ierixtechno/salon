# Package Module

**Phase:** 8 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Pre-paid bundles of services (may span multiple enabled verticals) that customers purchase and redeem over time.

## Core entities

Validity, purchased quantity, redeemed quantity, remaining quantity, expiry, redemption history.

## Key business rules

- A package may contain services from multiple enabled modules (`CLAUDE.md` §14).
- Before redemption, verify: ownership, active status, validity, applicable service, applicable branch, remaining quantity. Redemption is atomic — concurrent requests must never consume the same final entitlement twice (`.claude/skills/beauty-saas-development/SKILL.md` §18).

## Implemented (Phase 8)

Schema: `packages` (template) + `package_services` (recipe pivot, plain — both sides already tenant-scoped, no independent `tenant_id`, same precedent as `service_branch`/`service_user`), `customer_packages` (purchased instance — status `active|expired|exhausted|cancelled`), `customer_package_items` (per-service entitlement snapshotted from the recipe at purchase time), `package_redemptions` (the redemption ledger, unique per `appointment_id`).

`SellPackageToCustomer` snapshots the template's current recipe into `CustomerPackageItem` rows so a later edit to the template never retroactively changes an already-sold instance (CLAUDE.md §45). Purchase is recorded directly (method/reference/price_paid) rather than through the Invoice/GST engine — see [decisions/README.md](../decisions/README.md) D-006.

`RedeemPackageItem` is a manual, staff-triggered action against a completed appointment — same shape as Phase 7's `RecordServiceConsumption`, deliberately not wired into `CompleteAppointment`. It does **not** touch the Invoice engine at all (no $0 invoice line is created) — this sidesteps the GST-on-redemption question entirely, since D-006 already defers the harder question of whether GST applies at prepaid sale or at redemption; not double-taxing was the safe default either way. "Applicable branch" reuses the existing `Service::isAvailableAtBranch()` check (Phase 4) rather than a new package-specific branch-applicability table. Concurrency: the `CustomerPackageItem` row is locked before the remaining-quantity check.

Permissions: `packages.view/create/update/delete/sell/redeem`. Manager gets sell/redeem (front-desk operations, same precedent as POS in Phase 6) but not `packages.delete` (template deactivation is Owner-only, mirrors `products.delete`).
