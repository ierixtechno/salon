# Branch Module

**Phase:** 2 · **Domain:** Core · **Tenant scope:** tenant-scoped, branch is itself the scoping unit for downstream modules

## Purpose

Physical locations under a tenant. Branch is the second level of the SaaS hierarchy (`Platform → Tenant → Branch → Users/Employees → Business Operations`) and the unit most day-to-day authorization checks scope against.

## Core entities

- `branches` (id, tenant_id, name, code, timezone nullable-override, address, is_active)
- `branch_modules` (branch_id, module_id, enabled) — bounded by `tenant_modules` (`CLAUDE.md` §8)
- Branch working hours, holidays

## Key business rules

- A branch cannot enable a module its tenant doesn't have enabled — the tenant is the upper boundary (`CLAUDE.md` §8). Validate this server-side at write time, not just filter at read time.
- Resources (chairs, rooms, treatment beds, stations — see `CLAUDE.md` §14 Resource Management) belong to branches, not to the tenant directly.
- User branch access (all/selected/one) is checked server-side on every branch-scoped request (`branch_user` pivot or `all_branches` flag) — see [04-RBAC.md](../04-RBAC.md).

## Expand when Phase 2 begins

Full spec (holiday calendar model, working-hours-per-day-of-week model, resource type taxonomy) to be finalized at the start of Phase 2, alongside [EMPLOYEE.md](EMPLOYEE.md).
