# Membership Module

**Phase:** 8 · **Domain:** Core · **Tenant scope:** tenant + branch + module + service scoped applicability

## Purpose

Recurring membership plans customers buy for ongoing benefits/discounts.

## Core entities

Membership plans, validity, module applicability, branch applicability, service applicability, benefits, discounts, usage limits, renewal.

## Key business rules

Benefits are always resolved server-side: status, start date, expiry, branch, module, service, usage limits are all checked at the point of use — a discount percentage submitted by the frontend is never authoritative (`.claude/skills/beauty-saas-development/SKILL.md` §19).

## Expand when Phase 8 begins, alongside [PACKAGE.md](PACKAGE.md).
