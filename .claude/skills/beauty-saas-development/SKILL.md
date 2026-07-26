---
name: beauty-saas-development
description: Secure development workflow for the multi-tenant Salon, Beauty Parlour and Spa SaaS built with Laravel and MySQL.
---

# Beauty SaaS Development Skill

## Purpose

Use this skill whenever implementing, modifying, reviewing, debugging, refactoring, or testing functionality in the Beauty Business SaaS.

This skill supplements `CLAUDE.md`. CLAUDE.md is authoritative for project architecture and requirements. When instructions conflict, do not silently choose an interpretation that could affect security, tenancy, financial integrity, inventory integrity, historical data, or API compatibility.

## 1. Pre-Implementation Analysis

Before writing code, determine:

### Domain

Which domain owns the feature?

- Platform
- Core
- Salon
- Beauty Parlour
- Spa

If functionality is reusable across multiple verticals, prefer Core. Do not duplicate shared business logic inside vertical modules.

### Tenant Scope

Determine whether the entity is:

- Platform global
- Tenant scoped
- Branch scoped
- User scoped

Never add tenant-owned functionality without defining its tenant boundary.

### Module Scope

Determine whether the feature requires:

- Salon
- Beauty Parlour
- Spa
- Multiple modules
- No vertical module

### Feature Scope

Determine whether subscription plan feature gating applies.

### Authorization

Determine required permissions before implementing routes/controllers/UI.

### Data Impact

Identify effects on: invoices, payments, inventory, wallet, loyalty, membership, packages, commission, audit logs, reports.

## 2. Mandatory Implementation Order

For a new business capability, follow:

1. Inspect requirements
2. Inspect existing related code
3. Design data model
4. Define invariants
5. Define authorization
6. Create migration
7. Create/update model
8. Create policy/permissions
9. Implement domain action/service
10. Implement validation
11. Implement controller/API
12. Implement UI
13. Implement audit behavior
14. Implement tests
15. Review security
16. Review performance
17. Update documentation

Do not begin from the UI and retrofit security later.

## 3. Security Gate

Before considering any endpoint complete, verify:

- **Authentication:** Is authentication required?
- **Tenant:** Does the resource belong to the active tenant?
- **Branch:** Can this user access this branch?
- **Module:** Is the required module enabled for the tenant and branch?
- **Feature:** Does the plan permit this feature?
- **Permission:** Can the current user perform this action?
- **Ownership:** Does the referenced related record belong to the same tenant?
- **State:** Is the requested operation valid for the current business state?

Only then execute the operation.

## 4. Never Trust Client Data

Treat all request data as hostile.

Never trust client-supplied: `tenant_id`, `branch_id` without authorization, role, permissions, module assignment, subscription status, price, tax, discount, total, commission, wallet balance, loyalty balance, payment status, invoice status.

Recalculate or resolve authoritative values server-side.

## 5. Validation Standard

Use Form Requests or the project's established structured validation mechanism.

Validate: required fields, data type, maximum/minimum length, enum/state, date range, numeric range, foreign key existence, tenant ownership, branch authorization, module compatibility, business-state compatibility.

Database existence alone is not sufficient. A foreign record may exist but belong to another tenant.

## 6. Authorization Standard

Use Laravel Policies/Gates/permissions according to project conventions.

Never rely on: hidden buttons, disabled inputs, frontend route guards, JavaScript checks — as security controls.

Every protected server operation must authorize independently.

## 7. Tenant Isolation Pattern

All tenant-owned queries must execute within current tenant context.

Never write unrestricted queries such as `Model::find($id)` when the model is tenant owned, unless global scoping or an equivalent secure mechanism guarantees tenant isolation.

Prefer explicit tenant-aware retrieval or established tenant scopes.

Cross-tenant IDs must resolve as inaccessible. Do not reveal whether another tenant's record exists.

## 8. Branch Isolation Pattern

When a record belongs to a branch: confirm tenant, confirm branch, confirm user's branch access.

A valid tenant record from an unauthorized branch must remain inaccessible.

## 9. Module Authorization

Before vertical functionality: verify tenant module. If branch-specific: verify branch module.

Examples:

- Salon consultation requires Salon.
- Skin consultation requires Beauty Parlour.
- Spa session requires Spa.

Module-disabled requests must fail server-side even if routes are manually requested.

## 10. Financial Operations

For financial workflows:

1. Authorize
2. Validate
3. Resolve authoritative prices
4. Calculate totals server-side
5. Begin transaction
6. Lock records where necessary
7. Write financial records
8. Write dependent ledgers
9. Write audit event
10. Commit
11. Dispatch non-critical side effects

Never send notification/email before a financial transaction successfully commits.

## 11. Money Safety

Never use floating-point arithmetic for money. Use the project's approved money representation consistently.

Round only according to documented business/tax rules. Never use browser totals as authoritative values.

## 12. Invoice Safety

Finalized invoices are historical financial documents. Do not: delete them, silently rewrite totals, silently replace line items.

Use: void, refund, credit note, adjustment — according to business rules.

Invoice numbering must be concurrency safe.

## 13. Payment Safety

Payment processing must be idempotent.

Never create duplicate payment entries from: refresh, retry, network retry, duplicate webhook, double click.

External payment references must support uniqueness where appropriate.

## 14. Refund Safety

A refund may affect: payment, invoice, stock, loyalty, wallet, package redemption, membership benefits, commission, reports.

Do not implement a refund as simply changing `payment_status`. Use a dedicated refund workflow.

## 15. Inventory Safety

Inventory changes must produce ledger entries.

For stock-changing operations:

1. Authorize
2. Validate item/branch
3. Start transaction
4. Lock required stock state
5. Validate availability
6. Create stock movement
7. Update derived balance if maintained
8. Commit

Never permit unexplained negative stock unless explicitly supported by tenant configuration.

## 16. Appointment Safety

Before booking verify: tenant, branch, module, service availability, employee capability, employee schedule, leave, working hours, existing appointment, resource availability, preparation/buffer time, room turnaround where applicable.

Availability displayed to a user is not a guarantee. Revalidate immediately before persistence.

## 17. Appointment Concurrency

Two customers may attempt the same slot simultaneously. Use transactional conflict protection.

Where appropriate use: locking, unique constraints, atomic reservation logic.

Do not rely on a previous availability API response.

## 18. Package Redemption

Before redemption verify: ownership, active status, validity, applicable service, applicable branch, remaining quantity.

Redemption must be atomic. Concurrent requests must not consume the same final entitlement twice.

Maintain redemption history.

## 19. Membership

Membership benefits must be resolved server-side.

Check: status, start date, expiry, branch, module, service, usage limits.

Never accept a discount percentage submitted by the frontend as authoritative.

## 20. Wallet

Wallet is financial data. Use immutable transaction entries.

Every change must include: type, amount, reference, timestamp, actor/system source, reason where appropriate.

Never simply overwrite wallet balance without ledger impact. Concurrent redemption must be protected.

## 21. Loyalty

Use a points ledger. Every earn/redeem/expire/adjustment operation must be traceable.

Protect against concurrent double redemption.

## 22. Commission

Commission must be calculated from authoritative finalized transaction data.

Refunds and voids must adjust commission according to business rules. Do not trust commission values submitted by UI.

## 23. File Uploads

For every upload: authorize, validate MIME, validate extension, validate size, generate filename, store using Laravel filesystem, record ownership, protect sensitive files.

Never concatenate user filenames into filesystem paths. Prevent path traversal.

## 24. External API Calls

For integrations: define interface, implement provider adapter, configure credentials via environment, set timeout, catch provider errors, log safely, use retry only when safe, support idempotency, return domain-level failure.

Never expose provider exceptions directly to users.

## 25. Webhooks

Webhook endpoint workflow:

1. Receive raw request
2. Verify signature
3. Validate provider
4. Validate event
5. Identify account/tenant safely
6. Check idempotency
7. Process transactionally
8. Store processing result
9. Return provider-compatible response

Never trust `tenant_id` inside an unsigned webhook payload.

## 26. Error Taxonomy

Use appropriate errors:

- **Validation** — Example: invalid appointment date. Response: 422
- **Authentication** — Unauthenticated. Response: 401 for APIs or login redirect for browser.
- **Authorization** — Authenticated but not permitted. Response: 403.
- **Not Found** — Resource inaccessible/not present. Response: 404 where appropriate.
- **Conflict** — Valid request but current state prevents operation. Examples: slot already booked, room occupied, duplicate invoice operation. Response: 409 where appropriate.
- **Business Rule** — Examples: membership expired, package exhausted, insufficient stock. Return structured business failure according to project response conventions.
- **External Provider** — Do not expose provider internals. Return safe retry/failure state.
- **System Failure** — Return generic safe production error. Log technical details.

## 27. Error Response Structure

For JSON APIs, follow one consistent structure. Conceptual example:

```json
{
  "success": false,
  "code": "APPOINTMENT_SLOT_UNAVAILABLE",
  "message": "The selected appointment slot is no longer available.",
  "errors": {}
}
```

Do not expose: SQL, stack trace, class names, server paths, environment values.

## 28. Exception Handling

Do not catch every exception using `catch (Exception $e)` and return success-like responses.

Catch exceptions only when: converting infrastructure errors to domain errors, performing cleanup, adding safe context, implementing intentional fallback.

Unexpected exceptions should reach centralized exception handling and be logged.

## 29. Logging

When logging failures include useful context: `tenant_id`, `branch_id`, `user_id`, `operation`, `entity_type`, `entity_id`, `request_id`.

Do not log sensitive values. Never log passwords, tokens, secrets, or payment credentials.

## 30. Transactions

Use `DB::transaction()` or equivalent for atomic workflows. Keep transactions as short as practical.

Do not perform: email, WhatsApp, SMS, slow HTTP calls — inside a database transaction unless specifically required.

Dispatch after commit where supported.

## 31. Idempotency

Operations vulnerable to duplicate submission should support idempotency. Examples: payments, refunds, webhook processing, package redemption, gift card redemption, wallet operation, expensive booking actions where applicable.

Retrying a request must not produce duplicate financial effects.

## 32. State Machines

Complex business entities should have explicit valid transitions.

Example appointment: `PENDING → CONFIRMED → CHECKED_IN → IN_SERVICE → COMPLETED`

Alternative: `PENDING → CANCELLED`

Do not allow arbitrary status changes. Reject invalid transitions.

## 33. Soft Delete Review

Before adding `SoftDeletes` ask: does deletion make business sense?

Financial/audit records generally require lifecycle states rather than deletion.

## 34. Query Performance

Before completing list/report screens inspect for: N+1, unnecessary eager loading, unbounded collections, missing pagination, missing indexes, repeated queries.

Do not optimize blindly, but avoid known pathological patterns.

## 35. Shared Hosting Check

Before introducing infrastructure verify: can this work with MySQL, database queue, cron, Laravel filesystem, SMTP/API email?

If not, document the requirement and do not make the new infrastructure mandatory without approval.

## 36. Queue Failure Handling

Queued jobs must: validate relevant record still exists, validate state where necessary, be safe to retry, log failure context, avoid duplicate side effects.

Do not assume application state remains unchanged between dispatch and execution.

## 37. Notification Failure

A failed notification must generally not roll back a completed business transaction.

Example:

- **Correct:** Invoice created successfully. WhatsApp fails. Invoice remains valid. Notification failure is logged/retried.
- **Incorrect:** Delete invoice because WhatsApp failed.

## 38. Audit Requirement

Before completing a feature ask: would a business owner/security reviewer need to know who performed this action? If yes, audit it.

Especially: financial actions, permission changes, module changes, price changes, stock adjustments, refunds, impersonation.

## 39. Testing Checklist

Every feature should test relevant scenarios:

- [ ] Successful operation
- [ ] Invalid input
- [ ] Unauthenticated
- [ ] Unauthorized permission
- [ ] Cross-tenant access
- [ ] Unauthorized branch
- [ ] Module disabled
- [ ] Feature disabled
- [ ] Invalid state
- [ ] Duplicate request
- [ ] Rollback on failure
- [ ] Relevant concurrency condition
- [ ] Audit entry
- [ ] External provider failure where applicable

## 40. Security Review Checklist

Before marking complete:

- [ ] Tenant isolation
- [ ] Branch isolation
- [ ] Authorization
- [ ] Validation
- [ ] Mass assignment
- [ ] SQL injection
- [ ] XSS
- [ ] CSRF
- [ ] IDOR
- [ ] Upload security
- [ ] Secret handling
- [ ] Rate limiting where appropriate
- [ ] Financial integrity
- [ ] Auditability
- [ ] Safe errors
- [ ] Logging

## 41. Laravel Review

Use Laravel capabilities before custom solutions: Form Requests, Policies, Gates, Eloquent relationships, casts, transactions, queues, events, notifications, filesystem, cache, scheduler, rate limiting.

Do not recreate framework functionality without a strong reason.

## 42. Dependency Policy

Before adding a Composer/npm package:

1. Confirm functionality is necessary
2. Check whether Laravel already provides it
3. Verify package maintenance
4. Verify compatibility
5. Assess security
6. Assess shared-hosting requirements

Avoid unnecessary dependencies.

## 43. Debugging Workflow

When fixing a bug:

1. Reproduce
2. Identify root cause
3. Inspect tenant/module/permission context
4. Inspect logs
5. Inspect data state
6. Write failing regression test
7. Implement smallest correct fix
8. Run related tests
9. Run security checks
10. Verify no cross-module regression

Do not hide errors with broad try/catch blocks.

## 44. Database Change Workflow

Before schema modification:

1. Inspect existing migration
2. Inspect production assumptions
3. Inspect foreign keys
4. Inspect indexes
5. Inspect nullable/default requirements
6. Consider existing data
7. Create new migration
8. Test migrate
9. Test rollback where feasible

Never casually edit historical deployed migrations.

## 45. Refactoring

Refactoring must preserve: tenant isolation, authorization, API behavior, financial behavior, audit behavior, historical data.

Run relevant tests before and after.

Do not mix large architecture refactors with unrelated feature implementation.

## 46. Completion Report

After implementing a module, report:

- **Implemented** — What was added.
- **Database** — Tables/migrations/indexes affected.
- **Security** — Tenant, branch, module and permission controls.
- **Business Rules** — Important rules enforced.
- **Error Handling** — Expected failure scenarios handled.
- **Tests** — Tests added/run.
- **Risks** — Known limitations or future considerations.

Do not claim production readiness when tests or security review are incomplete.

## 47. Stop Conditions

Stop implementation and request an architectural/business decision when ambiguity affects: tenant isolation, financial calculation, refund behavior, inventory ownership, appointment conflicts, module boundaries, subscription enforcement, permission model, data deletion, customer privacy, payment behavior.

Do not guess irreversible business rules.

## 48. Final Principle

Every feature must answer:

- Who is the tenant?
- Which branch?
- Which module?
- Which feature?
- Who is the user?
- What permission do they have?
- What records can they access?
- What business state is valid?
- What happens if the operation fails halfway?
- Can it run twice safely?
- How is it audited?
- How is it tested?

If these questions do not have clear answers, the feature is not ready for implementation.
