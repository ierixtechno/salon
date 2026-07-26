# Platform Module

**Phase:** 1 · **Domain:** Platform · **Tenant scope:** N/A (this module operates above tenancy)

## Purpose

Everything Super Admin uses to run the SaaS itself. Fully separate from tenant administration — separate guard (`platform`), separate login table (`platform_admins`), separate route group/middleware, separate dashboard.

## Responsibilities

- Super Admin authentication (own guard/table — never shared with tenant `users`)
- Tenant management: create, activate, suspend, view
- Module management: enable/disable Salon/Beauty Parlour/Spa per tenant, and (bounded by tenant) per branch
- Subscription plans: define plans, their features, and their limits
- Feature management: define the catalogue of gatable features (`inventory`, `online_booking`, `loyalty`, `advanced_reports`, `whatsapp`, ...)
- Subscription management: assign/change a tenant's plan, track subscription status
- Trial management: trial start/end, conversion, expiry handling
- Usage limits: enforce plan-defined limits (branches, users, storage — see [05-SECURITY.md](../05-SECURITY.md) Tenant Storage Quota)
- Platform configuration, platform audit logs, platform reporting

## Core entities (Phase 1)

- `platform_admins` (id, name, email, password, ...)
- `tenants` (id, name, slug, status: trial/active/suspended/cancelled, trial_ends_at, timezone, currency, ...)
- `modules` (id, code unique: salon/beauty/spa, name)
- `tenant_modules` (tenant_id, module_id, enabled, enabled_at)
- `features` (id, code unique, name, description)
- `subscription_plans` (id, name, code, price, billing_interval, is_active)
- `plan_features` (plan_id, feature_id, value/limit nullable)
- `tenant_subscriptions` (tenant_id, plan_id, status, starts_at, ends_at, trial_ends_at)
- `platform_audit_logs` (actor platform_admin_id, action, entity_type, entity_id, tenant_id nullable, meta, created_at)

## Key business rules

- Module assignment is database-driven, never hard-coded (`CLAUDE.md` §7).
- Disabling a tenant's module never deletes historical data (`CLAUDE.md` §7).
- A branch's enabled modules must be a subset of its tenant's enabled modules (`CLAUDE.md` §8) — enforced at the point branch-module assignment is written, not just at read time.
- Super Admin impersonating a tenant user (support scenarios) must be explicitly audited (`CLAUDE.md` §47).

## Testing

Platform actions (tenant suspend, module toggle, plan change) need audit-log assertions in addition to the standard checklist in [09-TESTING.md](../09-TESTING.md). Cross-tenant isolation tests don't apply here (Super Admin legitimately spans tenants) — instead test that a **tenant** admin can never reach `platform`-guarded routes.
