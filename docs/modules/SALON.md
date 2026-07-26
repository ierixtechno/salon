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

## Expand when Phase 4 begins.
