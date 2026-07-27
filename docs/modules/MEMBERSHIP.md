# Membership Module

**Phase:** 8 · **Domain:** Core · **Tenant scope:** tenant + branch + module + service scoped applicability

## Purpose

Recurring membership plans customers buy for ongoing benefits/discounts.

## Core entities

Membership plans, validity, module applicability, branch applicability, service applicability, benefits, discounts, usage limits, renewal.

## Key business rules

Benefits are always resolved server-side: status, start date, expiry, branch, module, service, usage limits are all checked at the point of use — a discount percentage submitted by the frontend is never authoritative (`.claude/skills/beauty-saas-development/SKILL.md` §19).

## Implemented (Phase 8)

Schema: `membership_plans` (`discount_percent`, nullable `usage_limit`), three independent applicability pivots (`membership_plan_modules`/`_branches`/`_services` — empty means "applies to all" for that dimension, mirroring `Service::isAvailableAtBranch`'s pivot convention), `customer_memberships` (purchased instance), `membership_usages` (traceability ledger — one row per invoice line the discount landed on).

Unlike Package redemption, a membership discount **does** integrate directly with the Invoice engine (`AddInvoiceLine`'s new optional `$membership` parameter) — it's a live discount on a real, currently-taxed service sale, not a prepaid-consideration question, so there's no GST ambiguity to defer. The discount is computed server-side from `MembershipPlan::appliesToServiceAtBranch()` and **replaces** any manual `discount_amount` entirely (mutually exclusive, to avoid an unspecified stacking rule). `AddInvoiceLine` remains 100% backward compatible — every pre-Phase-8 call site that omits `$membership` behaves byte-for-byte as before.

Purchase (`SellMembershipToCustomer`) is recorded directly, same D-006 scope note as Package.

Permissions: `memberships.view/create/update/delete/sell`. Same Manager/Owner split as [PACKAGE.md](PACKAGE.md).
