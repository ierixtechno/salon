# Beauty Parlour Module

**Phase:** 4 (catalogue/consultation) onward · **Domain:** Beauty Parlour (vertical) · **Tenant scope:** requires `beauty` module enabled for tenant + branch

## Purpose

Beauty-specific behavior layered on top of Core Services/Appointments/Customers, including bridal event management.

## Core entities

Beauty service catalogue, skin profile, skin consultation, treatment recommendation, treatment plans, treatment sessions, session progress, bridal management, bridal events, makeup management, before/after records with consent, beauty product consumption.

## Possible service categories

Facial, cleanup, waxing, threading, bleach, manicure, pedicure, nail care, nail art, makeup, bridal makeup, skin treatment, body polishing, hand/foot care.

## Bridal events

Engagement, haldi, mehendi, sangeet, wedding, reception — each with date, time, venue, services, staff, travel charges, payments, notes.

## Key business rules

- **Before/after records require consent capture** (who, when, purpose/scope) before storage, a defined retention period, and must be included in the customer erasure workflow — see [05-SECURITY.md](../05-SECURITY.md) Data Privacy & Retention and decision D-004. This is the module's single highest-risk area, not a routine media-upload feature.
- Skin consultation/treatment functionality requires the `beauty` module enabled for tenant + branch (`.claude/skills/beauty-saas-development/SKILL.md` §9).
- Bridal events can carry their own sub-schedule of services/staff/payments distinct from a normal single-service appointment — coordinate with [APPOINTMENT.md](APPOINTMENT.md) on how a multi-service, multi-day event is represented (likely a parent "event" record with child appointments, to be finalized in Phase 4).

## Implemented (Phase 4)

- `App\Domain\BeautyParlour\Models\SkinProfile` — one row per customer (upsert), persistent skin characteristics (skin type, known conditions, allergies). Requires `beauty-consultations.update`.
- `App\Domain\BeautyParlour\Models\SkinConsultation` — append-only per-visit ledger (concerns, treatment plan, recommendation, notes), tied to a tenant branch that must have the `beauty` module enabled. Requires `beauty-consultations.create`; no update/delete route exists (immutable history, CLAUDE.md §46/§14).
- Routes nested under `customers/{customer}/beauty/...`, gated by `module:beauty` (tenant-level) + `can:beauty-consultations.*` (permission-level); branch-level module enforcement happens inside `StoreSkinConsultationRequest`.
- `EraseCustomer` purges both tables for the customer on a DPDP erasure request.
- **Deliberately deferred, not attempted in this phase:** before/after photo capture (needs its own consent-capture UI, retention job, and storage-quota accounting — CLAUDE.md §34/§36 — before it should be built), and bridal event management (needs the Phase 5 Appointment Engine's parent-event/child-appointment model to exist first, per this file's own prior note). Both remain open follow-ups, not silently dropped.

## Expand when bridal events and before/after photos are picked up — see the deferred items above.
