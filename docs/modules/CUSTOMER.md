# Customer Module

**Phase:** 3 · **Domain:** Core · **Tenant scope:** tenant-scoped, identity shared across all enabled verticals

## Purpose

A single customer identity shared across Salon/Beauty Parlour/Spa — a customer who gets a haircut and a facial is one customer record, not two.

## Core entities

Customer profile, contact information, preferences, tags, notes, visit history, appointment history, purchase history, membership, packages, loyalty, wallet, feedback.

## Key business rules

- Customer identity is shared across enabled business modules (`CLAUDE.md` §14) — do not fork per-vertical customer records.
- Sensitive data captured here (skin/hair consultation notes, before/after photos — populated by [SALON.md](SALON.md)/[BEAUTY-PARLOUR.md](BEAUTY-PARLOUR.md)) is subject to [05-SECURITY.md](../05-SECURITY.md) Data Privacy & Retention — consent, retention period, erasure workflow. **Decision needed before this phase starts** — see [decisions/README.md](../decisions/README.md) D-004.
- Global/customer search must enforce tenant isolation (`CLAUDE.md` §57).

## Expand when Phase 3 begins — this is also the point to finalize D-004 (data protection regime).
