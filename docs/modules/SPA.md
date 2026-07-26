# Spa Module

**Phase:** 4 (catalogue/consultation) onward · **Domain:** Spa (vertical) · **Tenant scope:** requires `spa` module enabled for tenant + branch

## Purpose

Spa-specific behavior layered on top of Core Services/Appointments/Customers, with the most complex resource-coordination requirement of the three verticals.

## Core entities

Spa service catalogue, spa consultation, therapy plan, therapy/session records, therapist assignment, room management, room availability, room turnaround, couple bookings, spa product consumption.

## Possible service categories

Massage, body therapy, body scrub, body wrap, aromatherapy, hydrotherapy, steam, sauna, reflexology, couple spa, Ayurvedic therapy, wellness package.

## Key business rules

- Spa scheduling requires **Customer + Therapist + Room/resource + Time slot** all confirmed available together before booking is confirmed (`CLAUDE.md` §17) — this is a stricter multi-resource check than a typical Salon/Beauty appointment, and the Appointment Engine ([APPOINTMENT.md](APPOINTMENT.md)) needs to support it generically (couple bookings need two therapists + one or two rooms simultaneously).
- Room turnaround time (cleaning/reset between sessions) is a real availability constraint, not just a display nicety — it must reduce actual bookable capacity in the availability check.
- Spa functionality requires the `spa` module enabled for tenant + branch.

## Implemented (Phase 4)

- `App\Domain\Spa\Models\SpaProfile` — one row per customer (upsert), persistent health/preference profile (health conditions, allergies, pressure preference, areas to avoid) used to screen contraindications before a session. Requires `spa-consultations.update`.
- `App\Domain\Spa\Models\SpaConsultation` — append-only per-visit ledger (concerns, recommendation, notes), tied to a tenant branch that must have the `spa` module enabled. Requires `spa-consultations.create`; no update/delete route exists (immutable history, CLAUDE.md §46/§14).
- Routes nested under `customers/{customer}/spa/...`, gated by `module:spa` (tenant-level) + `can:spa-consultations.*` (permission-level); branch-level module enforcement happens inside `StoreSpaConsultationRequest`.
- `EraseCustomer` purges both tables for the customer on a DPDP erasure request.
- **Deliberately deferred, not attempted in this phase:** room/resource allocation, room turnaround time, couple bookings, and therapist-schedule coordination all belong to the Phase 5 Appointment Engine, not the consultation record — this phase only covers the intake/consultation data, in line with this file's original scope note.

## Implemented (Phase 5)

Single-resource Spa bookings work end-to-end through the general Appointment Engine ([APPOINTMENT.md](APPOINTMENT.md)): `Customer + Therapist + Room/resource + Time slot` are all validated together before confirmation, and room turnaround time reduces actual bookable capacity (a resource's `turnaround_minutes` extends its busy window past `ends_at`, symmetrically with the employee's `buffer_minutes`). A resource's `capacity` > 1 (e.g. a couple's massage room) already allows that many simultaneous appointments before conflicting.

True **couple bookings** — two therapists and two rooms reserved together as one atomic unit — are deliberately not built yet; see APPOINTMENT.md's "Deliberately deferred" section for why and what schema hook (`group_uuid`) keeps it open for later.
