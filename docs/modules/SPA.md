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

## Expand when Phase 4 begins, in close coordination with [APPOINTMENT.md](APPOINTMENT.md) since Spa exercises the Appointment Engine's hardest resource-coordination case.
