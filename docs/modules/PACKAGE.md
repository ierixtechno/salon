# Package Module

**Phase:** 8 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Pre-paid bundles of services (may span multiple enabled verticals) that customers purchase and redeem over time.

## Core entities

Validity, purchased quantity, redeemed quantity, remaining quantity, expiry, redemption history.

## Key business rules

- A package may contain services from multiple enabled modules (`CLAUDE.md` §14).
- Before redemption, verify: ownership, active status, validity, applicable service, applicable branch, remaining quantity. Redemption is atomic — concurrent requests must never consume the same final entitlement twice (`.claude/skills/beauty-saas-development/SKILL.md` §18).

## Expand when Phase 8 begins, alongside [MEMBERSHIP.md](MEMBERSHIP.md).
