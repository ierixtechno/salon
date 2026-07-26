# Service Module (Services Core)

**Phase:** 4 · **Domain:** Core (extended per-vertical by [SALON.md](SALON.md), [BEAUTY-PARLOUR.md](BEAUTY-PARLOUR.md), [SPA.md](SPA.md)) · **Tenant scope:** tenant-scoped, with branch-level availability/pricing overrides

## Purpose

The service catalogue: what a tenant sells, at what price, deliverable by which staff, at which branches.

## Core entities

Categories, services, variants, add-ons, durations, pricing, taxes, staff capability, branch availability.

## Key business rules

- Every service identifies its originating vertical/module where applicable (`CLAUDE.md` §14) — a service belongs to Salon, Beauty Parlour, or Spa (or is vertical-agnostic Core, if such services exist).
- Pricing calculations happen server-side always — base pricing, branch pricing, variants, membership benefits, package pricing, promotional pricing, discount, tax (`CLAUDE.md` §14 Pricing). Never trust a submitted price.
- Staff capability here is exactly what the Appointment Engine checks against when validating employee capability for a booking.

## Expand when Phase 4 begins.
