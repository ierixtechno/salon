# Tenancy

Full authority: `CLAUDE.md` §4–13, §54–57.

## Strategy

Single Laravel application + single MySQL database + shared schema + `tenant_id` isolation. Tenant isolation is a **security boundary**, not a convenience filter.

## Hierarchy

```
Platform → Tenant → Branch → Users / Employees → Business Operations
```

- **Platform** — controlled by Super Admin only. Manages tenants, module availability, plans, subscriptions, trials, usage limits, platform config, platform audit logs. Super Admin auth is a **separate guard/model** from tenant users — never share a login table between Super Admin and tenant staff.
- **Tenant** — the salon/beauty/spa business account. Owns branches, users, customers, and all business data.
- **Branch** — a physical location under a tenant. Has its own working hours, holidays, module availability (bounded by the tenant's enabled modules), and resources.

## Rule: never trust client-supplied tenant/branch identity

Never trust `tenant_id` or `branch_id` from request body, query string, route parameter, hidden field, JavaScript, or API client. Tenant identity comes from the authenticated server-side tenant context (resolved from the logged-in user, never from anything the client sends).

## Module & feature model — four separate concepts, never substituted for one another

| Concept | Meaning | Example |
|---|---|---|
| **Module** | Business vertical | `salon`, `beauty`, `spa` |
| **Feature** | System capability | `inventory`, `online_booking`, `loyalty`, `advanced_reports`, `whatsapp` |
| **Plan** | Commercial package defining features + limits | "Growth", "Pro" |
| **Permission** | Action a user may perform | `appointments.view`, `appointments.cancel` |

- Module assignment is **database-driven**, per tenant, and per branch (a branch's enabled modules must be a subset of the tenant's enabled modules — the tenant is the upper bound).
- Disabling a module must never delete historical data (appointments, invoices, payments, treatments, sessions, customer history, inventory transactions, audit records all remain intact and queryable, just inaccessible for *new* operations).

## Access evaluation order

Every authorization decision considers, in order:

1. Authentication
2. Account status
3. Tenant status
4. Subscription status
5. Tenant module
6. Branch module
7. Plan feature
8. Role/permission
9. Resource ownership/scope

Hidden navigation is never a substitute for this check — server-side authorization is mandatory even for manually-crafted requests to routes/APIs the UI doesn't expose.

## Tenant isolation — enforcement surface

Cross-tenant access must be impossible via: direct URL manipulation, API requests, exports, reports, autocomplete, search, file downloads, attachments, queued jobs, notifications, background tasks.

Every tenant-aware module ships automated cross-tenant isolation tests (`CLAUDE.md` §62): create Tenant A + Tenant B, create a resource under A, authenticate as B, attempt view/edit/delete/export/API fetch — all must fail safely, and must not reveal whether the record exists.

## Branch isolation

Users may have access to all branches, selected branches, or one branch. Branch access is authorization-checked server-side; never trust a `branch_id` merely because the UI submitted it.

## Implementation notes for Phase 1

- `spatie/laravel-permission`'s **teams** feature is used with `tenant_id` as the team key, so roles/permissions are naturally tenant-scoped without hand-rolled scoping logic.
- A global Eloquent scope (or equivalent) applies `tenant_id = current_tenant()` automatically on tenant-owned models; raw `Model::find($id)` on a tenant-owned model without this guarantee is disallowed (see `.claude/skills/beauty-saas-development/SKILL.md` §7).
