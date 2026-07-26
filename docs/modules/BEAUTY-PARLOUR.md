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

## Expand when Phase 4 begins — resolve D-004 first.
