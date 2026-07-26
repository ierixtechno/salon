# Product Vision

## What this is

A production-grade, multi-tenant SaaS platform serving three business verticals:

- **Salon**
- **Beauty Parlour**
- **Spa**

One shared Core provides common business capabilities (customers, appointments, employees, products, invoices, etc.). Each vertical module adds vertical-specific behavior on top of Core. A tenant can run any combination of the three verticals — see [02-TENANCY.md](02-TENANCY.md).

## Who uses it

- **Super Admin** — the platform operator. Manages tenants, modules, plans, subscriptions, and platform-wide configuration. Never mixed with tenant-level administration (see [02-TENANCY.md](02-TENANCY.md)).
- **Tenant Admin / Owner** — runs a salon/beauty/spa business (possibly multi-branch, possibly multi-vertical).
- **Branch staff** — managers, stylists, therapists, front-desk, with branch- and permission-scoped access.
- **Customers** — end clients; from Phase 13 onward, customers get self-service online booking.

## Non-negotiables

These carry through every phase and every module (full detail in the docs they link to):

- **Tenant isolation is a security boundary**, not a convenience filter — [02-TENANCY.md](02-TENANCY.md).
- **Money and inventory are ledger-based and server-authoritative** — [03-DATABASE-STANDARDS.md](03-DATABASE-STANDARDS.md).
- **Every protected action is authorized server-side**, regardless of what the UI shows — [04-RBAC.md](04-RBAC.md), [05-SECURITY.md](05-SECURITY.md).
- **Correctness and security outrank development speed.**

## Deployment reality

Initial target is shared hosting (MySQL, cron, no persistent workers, no Redis/Docker requirement), with the architecture kept portable to VPS/cloud later without a rewrite — see [10-SHARED-HOSTING.md](10-SHARED-HOSTING.md).

## Roadmap

See "Development Priority" in [01-ARCHITECTURE.md](01-ARCHITECTURE.md) for the 14-phase build order. We are currently in **Phase 0 (Architecture and foundations) → Phase 1 (Authentication, Tenancy, RBAC, Super Admin, Modules, Plans, Subscriptions, Onboarding)**.

## Open decisions

A short list of business/architecture decisions is pending confirmation before certain phases begin. Tracked in [decisions/README.md](decisions/README.md) — check there before starting Phase 3, 6, or 11.

## Source of truth

`CLAUDE.md` at the project root is the authoritative constitution. This `docs/` tree expands on it topic-by-topic and module-by-module; where anything here appears to conflict with `CLAUDE.md`, `CLAUDE.md` wins and the conflict should be flagged, not silently resolved.
