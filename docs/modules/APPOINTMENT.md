# Appointment Module (Appointment Engine)

**Phase:** 5 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Booking, calendar, availability, and the full appointment lifecycle — arguably the highest-stakes concurrency surface in the whole platform.

## Core entities

Booking, calendar, availability, staff allocation, resource allocation, walk-ins, waitlist, check-in, reschedule, cancellation, no-show, completion, rebooking, recurring appointments.

## State machine

```
PENDING → CONFIRMED → CHECKED_IN → IN_SERVICE → COMPLETED
PENDING → CANCELLED
```

No arbitrary status transitions — reject anything not in this graph (`.claude/skills/beauty-saas-development/SKILL.md` §32).

## Key business rules

- Conflict checks occur server-side, always (`CLAUDE.md` §14).
- Before booking, verify: tenant, branch, module, service availability, employee capability, employee schedule, leave, working hours, existing appointment, resource availability, preparation/buffer time, room turnaround where applicable (`.claude/skills/beauty-saas-development/SKILL.md` §16). Availability shown to a user is never a guarantee — revalidate immediately before persisting.
- Two customers may attempt the same slot simultaneously — use transactional conflict protection (locking / unique constraints / atomic reservation), never trust a prior availability API response (SKILL.md §17).
- Spa bookings specifically may need `Customer + Therapist + Room/resource + Time slot` all confirmed available together before confirmation (`CLAUDE.md` §17).

## Expand when Phase 5 begins.
