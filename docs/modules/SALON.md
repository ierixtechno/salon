# Salon Module

**Phase:** 4 (catalogue/consultation) onward · **Domain:** Salon (vertical) · **Tenant scope:** requires `salon` module enabled for tenant + branch

## Purpose

Salon-specific behavior layered on top of Core Services/Appointments/Customers.

## Core entities

Salon service catalogue, hair profile, hair consultation, hair/scalp concerns, treatment recommendation, hair treatment history, color formula, color history, stylist capability, salon chair/station allocation, salon product consumption.

## Possible service categories

Haircut, styling, wash, hair spa, hair treatment, coloring, straightening, smoothing, keratin, extensions, beard, shaving, grooming, scalp treatment.

## Key business rules

- Salon consultation/treatment functionality requires the `salon` module to be enabled for both the tenant and (if branch-specific) the branch — verified server-side even on manually-requested routes (`.claude/skills/beauty-saas-development/SKILL.md` §9).
- Color formula/history is sensitive enough to warrant the same retention thinking as Beauty Parlour's before/after records if photos are attached — see [05-SECURITY.md](../05-SECURITY.md).

## Implemented (Phase 4)

- `App\Domain\Salon\Models\HairProfile` — one row per customer (upsert), persistent hair/scalp characteristics (hair type, scalp type, chemical history, allergies). Requires `salon-consultations.update`.
- `App\Domain\Salon\Models\HairConsultation` — append-only per-visit ledger (concerns, recommendation, color formula, notes), tied to a tenant branch that must have the `salon` module enabled. Requires `salon-consultations.create`; no update/delete route exists (immutable history, CLAUDE.md §46/§14).
- Routes nested under `customers/{customer}/salon/...`, gated by `module:salon` (tenant-level) + `can:salon-consultations.*` (permission-level); branch-level module enforcement happens inside `StoreHairConsultationRequest`.
- `EraseCustomer` purges both tables for the customer on a DPDP erasure request.
- Stylist capability, chair/station allocation, and product consumption remain deferred to the Phase 5 Appointment Engine and Phase 7 Inventory respectively — this phase covers catalogue + consultation only.
