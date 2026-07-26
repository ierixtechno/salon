# Appointment Module (Appointment Engine)

**Phase:** 5 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Booking, calendar, availability, and the full appointment lifecycle — arguably the highest-stakes concurrency surface in the whole platform.

## Core entities

Booking, calendar, availability, staff allocation, resource allocation, walk-ins, waitlist, check-in, reschedule, cancellation, no-show, completion, rebooking, recurring appointments.

## State machine

The doc's original diagram (`PENDING → CONFIRMED → CHECKED_IN → IN_SERVICE → COMPLETED`, `PENDING → CANCELLED`) was a simplification. The implemented, authoritative graph (`App\Domain\Core\Models\Appointment::TRANSITIONS`) extends it with the transitions real front-desk use needs:

```
pending    → confirmed, cancelled
confirmed  → checked_in, cancelled, no_show
checked_in → in_service
in_service → completed
```

Cancellation is legitimately needed from `confirmed` too (a customer calling to cancel the day before), and `no_show` only makes sense once an appointment was `confirmed`. No arbitrary status transitions — reject anything not in this graph (`.claude/skills/beauty-saas-development/SKILL.md` §32).

Every appointment booked through this phase starts `confirmed`, not `pending` — every booking here is staff-initiated (front desk or walk-in), so there's no separate "customer requests, staff confirms" step. `pending` remains valid for the future Phase 13 self-service/online booking flow (`CLAUDE.md` §75), which this phase does not build.

## Key business rules

- Conflict checks occur server-side, always (`CLAUDE.md` §14).
- Before booking, verify: tenant, branch, module, service availability, employee capability, employee schedule, working hours, existing appointment, resource availability, preparation/buffer time, room turnaround where applicable (`.claude/skills/beauty-saas-development/SKILL.md` §16). Availability shown to a user is never a guarantee — revalidate immediately before persisting. (Employee *leave* is not yet part of this check — Phase 9 hasn't been built; when it lands, it's one more exclusion filter on the same schedule check, not an architecture change.)
- Two customers may attempt the same slot simultaneously — use transactional conflict protection (locking / unique constraints / atomic reservation), never trust a prior availability API response (SKILL.md §17).
- Spa bookings specifically may need `Customer + Therapist + Room/resource + Time slot` all confirmed available together before confirmation (`CLAUDE.md` §17).

## Implemented (Phase 5)

- **Schema**: `appointments` (tenant/branch/customer/service/variant/employee/resource, `starts_at`/`ends_at` as canonical UTC per CLAUDE.md §22, server-resolved `price` snapshot, `group_uuid` for future linked-booking support) and `waitlist_entries` (deliberately decoupled from the appointment state machine — a waitlist entry never holds a firm slot). `services.buffer_minutes` and `resources.turnaround_minutes` were added (default 0, backward compatible) to make prep/cleanup time a real availability constraint rather than a display nicety.
- **Availability engine** (`App\Domain\Core\Actions\BookAppointment` + the shared `AssertsAppointmentAvailability` trait): validates branch business hours (falling back tenant → hardcoded 09:00–18:00 default, matching what the settings UI itself shows before anything is saved), branch holidays, the employee's weekly schedule, employee conflicts (buffer-aware), and resource conflicts (turnaround-aware, respecting `capacity` > 1 for multi-occupancy rooms). Stable facts (module/branch/service-availability/employee-capability) are validated once in `StoreAppointmentRequest`; every time-sensitive fact is re-verified fresh inside a locked transaction immediately before insert.
- **Concurrency**: MySQL has no native range-exclusion constraint, so simultaneous bookings for the same employee/resource are serialized by taking a `lockForUpdate()` on that employee's/resource's own row before the conflict check runs — the standard "lock a proxy row, then check-then-act" pattern for range conflicts.
- **Lifecycle actions**: `CheckInAppointment`, `StartAppointmentService`, `CompleteAppointment`, `CancelAppointment`, `MarkAppointmentNoShow`, `RescheduleAppointment` (re-runs the full availability check for the new slot; only valid while `pending`/`confirmed`).
- **Walk-ins**: same `BookAppointment` action, `source = 'walk_in'`, no separate code path.
- **Waitlist**: lightweight CRUD; "book" hands off to the normal booking form pre-filled with the entry's customer/branch/service, and the entry is marked `booked` once a real appointment is created from it — never a special availability path of its own.
- **Authorization**: `appointments.view/create/update/cancel` + `waitlist.view/create/update` (the exact permission examples CLAUDE.md §10 already names), `AppointmentPolicy` also checks `User::canAccessBranch()` so a staff member can't see/act on another branch's appointments even with the right permission.

## Deliberately deferred, not silently dropped

- **True couple bookings** (2 therapists + 2 rooms coordinated atomically) — `group_uuid` exists on `appointments` specifically so sibling rows can be linked later without a schema change, but the multi-resource coordinator itself isn't built. Single-resource Spa bookings (one therapist, one room, optionally a capacity-2 room for a shared couple's session) work today.
- **Recurring appointments** — the doc lists this as a core entity, but no concrete recurrence rules (pattern, series-cancellation behavior) are specified anywhere; building it now would mean inventing business rules silently (CLAUDE.md §70). `group_uuid` again keeps the door open.
- **A live slot-suggestion/calendar-scanning UI** — booking today requires picking a specific time and getting a clear rejection if it doesn't work, the same interaction pattern as the rest of this app's forms. Scanning a day for all open slots is a bigger feature, not the load-bearing correctness/security part of this phase.
