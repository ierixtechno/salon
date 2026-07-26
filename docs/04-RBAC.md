# RBAC (Roles, Permissions, Modules, Features, Plans)

Full authority: `CLAUDE.md` §5, §9–10, §14 (Users & RBAC), §26; `.claude/skills/beauty-saas-development/SKILL.md` §6, §9.

## Two completely separate admin worlds

- **Super Admin** (Platform layer) — separate authentication guard/table (`platform_admins`, guard `platform`). Manages tenants, modules, plans, subscriptions, platform config/audit/reporting.
- **Tenant users** (`users` table, guard `web`) — tenant-scoped. Roles: Owner, Manager, Staff, and whatever a tenant configures within what the platform allows.

These must never share a login table, a session guard, or a permission set. A Super Admin does not automatically gain tenant data access; "impersonation" (§47 audit list) is the only sanctioned bridge, and it must be audited.

## Mechanism: spatie/laravel-permission, teams = tenant

- Package chosen because Laravel doesn't ship RBAC, this package is the de facto standard, actively maintained, MySQL-only (shared-hosting friendly), and its **teams** feature maps directly onto "roles/permissions scoped per tenant" without hand-rolled scoping code (Dependency Policy check: SKILL.md §42 — passes on necessity, maintenance, compatibility, hosting).
- `team_foreign_key` = `tenant_id`. A user's roles/permissions are always evaluated within their tenant's team context.
- Branch-level access is a separate concern from role/permission — see below.

## Access evaluation order (repeated from `02-TENANCY.md` — this is the canonical checklist for every endpoint)

1. Authentication
2. Account status
3. Tenant status
4. Subscription status
5. Tenant module enabled
6. Branch module enabled
7. Plan feature enabled
8. Role/permission
9. Resource ownership/scope

Only after all nine pass does the operation execute. Hidden navigation is never authorization.

## Branch access

A user has access to: all branches, selected branches, or one branch. This is modeled as a `branch_user` pivot (or an `all_branches` flag) and checked server-side on every branch-scoped request — never trust a client-supplied `branch_id`.

## Permission naming

`{resource}.{action}` — e.g. `appointments.view`, `appointments.create`, `appointments.update`, `appointments.cancel`.

## Module authorization

Before any vertical-specific functionality executes: verify the tenant has the module enabled, then (if branch-specific) verify the branch has it enabled too. Module-disabled requests must fail server-side even when the route is manually requested — this is not a UI-only concern.

## Base roles seeded in Phase 1

- **Owner** — full tenant administration, all branches by default, cannot be permission-limited below platform-enforced minimums.
- **Manager** — branch-scoped by default; operational permissions across POS/appointments/customers/inventory, not billing/subscription.
- **Staff** — narrowest default: own appointments, check-in/out, customer view within assigned branch.

Exact permission grants per role are finalized during Phase 1 implementation and recorded in a seeder, not hard-coded into application logic.
