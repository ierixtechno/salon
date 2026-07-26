# Beauty Business SaaS — Claude Code Project Constitution

## 1. Purpose

This repository contains a production-grade multi-tenant SaaS platform for businesses operating in:

- Salon
- Beauty Parlour
- Spa

The application is a single SaaS platform with a shared Core and independently assignable vertical modules.

A tenant may operate:

- Salon only
- Beauty Parlour only
- Spa only
- Salon + Beauty Parlour
- Salon + Spa
- Beauty Parlour + Spa
- Salon + Beauty Parlour + Spa

The Super Admin controls which modules are available to each tenant.

The system must be designed for long-term scalability, security, maintainability, multi-branch operation, and future mobile/API clients.

## 2. Technology

Primary stack:

- Laravel
- PHP version compatible with the selected Laravel release
- MySQL
- Blade
- Tailwind CSS
- Alpine.js where appropriate
- Vite
- REST/JSON APIs where needed
- Laravel Scheduler
- Laravel Queue
- PHPUnit/Pest according to project configuration

Initial production target:

- Shared hosting
- MySQL
- Cron support
- Database queue where workers are unavailable
- File/database cache

The architecture must allow future migration to:

- VPS/cloud
- Redis
- Dedicated queue workers
- Object storage
- Multiple application instances
- Load balancer
- Managed database

Do not make Redis, WebSockets, Docker, Kubernetes, Elasticsearch, RabbitMQ, Kafka, or persistent background processes mandatory for the initial application.

## 3. Architecture

Use a modular monolith. Do **NOT** create microservices.

Logical architecture:

```
Platform
├── Core
├── Salon
├── Beauty Parlour
└── Spa
```

Shared business capabilities belong to Core. Vertical-specific business behavior belongs to the corresponding vertical module.

Do not duplicate shared concepts across vertical modules.

Examples:

**Correct:** `customers`, `appointments`, `employees`, `products`, `invoices`

**Incorrect:** `salon_customers`, `beauty_customers`, `spa_customers`

## 4. SaaS Hierarchy

```
Platform → Tenant → Branch → Users / Employees → Business Operations
```

Every tenant-owned business record must belong to a tenant.

Branch-specific records must additionally belong to a branch where applicable.

## 5. SaaS Platform

The Platform layer is controlled by Super Admin.

Responsibilities:

- Super Admin authentication
- Platform dashboard
- Tenant management
- Module management
- Subscription plans
- Feature management
- Subscription management
- Tenant activation
- Tenant suspension
- Trial management
- Usage limits
- Platform configuration
- Platform audit logs
- Platform reporting

Super Admin and tenant administration must remain logically separated.

## 6. Business Modules

The three primary vertical modules are:

`SALON` · `BEAUTY_PARLOUR` · `SPA`

Store modules using stable machine-readable codes.

Example: `salon`, `beauty`, `spa`

Do not use display labels as identifiers.

## 7. Tenant Module Assignment

Super Admin must be able to enable or disable any vertical module for a tenant.

Example:

- Tenant A: Salon = enabled, Beauty Parlour = enabled, Spa = disabled
- Tenant B: Salon = disabled, Beauty Parlour = disabled, Spa = enabled

Module assignment must be database driven. Never hard-code tenant module assignments.

Disabling a module must **NOT** delete historical data. Historical:

- appointments
- invoices
- payments
- treatments
- sessions
- customer history
- inventory transactions
- audit records

must remain intact.

## 8. Branch Module Assignment

A tenant may operate different modules at different branches.

Example:

- Branch A: Salon, Beauty Parlour, Spa
- Branch B: Salon, Beauty Parlour
- Branch C: Spa

Tenant module enablement is the upper boundary. A branch cannot enable a module unavailable to its tenant.

## 9. Access Evaluation

Authorization must consider:

1. Authentication
2. Account status
3. Tenant status
4. Subscription status
5. Tenant module
6. Branch module
7. Plan feature
8. Role/permission
9. Resource ownership/scope

Do not rely only on menu visibility. Unauthorized routes, controllers, APIs and actions must remain inaccessible even when manually requested.

## 10. Plans, Modules, Features and Permissions

These concepts **MUST** remain separate.

- **MODULE**: Business vertical. Examples: `salon`, `beauty`, `spa`
- **FEATURE**: System capability. Examples: `inventory`, `online_booking`, `loyalty`, `advanced_reports`, `whatsapp`
- **PLAN**: Commercial package defining features and limits.
- **PERMISSION**: Action a user may perform. Example: `appointments.view`, `appointments.create`, `appointments.update`, `appointments.cancel`

Never substitute one concept for another.

## 11. Multi-Tenancy

Initial strategy:

```
Single Laravel application
+ Single MySQL database
+ Shared schema
+ tenant_id isolation
```

Tenant isolation is a **SECURITY BOUNDARY**.

Every tenant-owned record must contain `tenant_id` unless a documented architectural exception exists.

Never trust `tenant_id` supplied by:

- request body
- query string
- route parameter
- hidden field
- JavaScript
- API client

Tenant identity must come from authenticated server-side tenant context.

## 12. Tenant Isolation

A user from Tenant A must **NEVER** access Tenant B data. This includes:

- direct URL manipulation
- API requests
- exports
- reports
- autocomplete
- search
- file downloads
- attachments
- queued jobs
- notifications
- background tasks

Every new tenant-aware module must include automated cross-tenant isolation tests.

## 13. Branch Isolation

Users may have access to:

- all branches
- selected branches
- one branch

Branch access must be authorization checked server-side.

Never trust `branch_id` merely because it was submitted by the UI.

## 14. Common Core

Shared modules include:

### Organization

- Business profile
- Business settings
- Tax configuration
- Currency
- Timezone
- Business hours
- Policies

### Branches

- Branch management
- Working hours
- Holidays
- Module availability
- Branch configuration

### Users & RBAC

- Users
- Roles
- Permissions
- Branch access
- Module access
- Sessions

### Employees

- Employee profile
- Branch assignment
- Role
- Skills
- Services
- Shifts
- Attendance
- Leave
- Commission
- Incentives
- Performance

> **Scope note:** Full payroll processing (salary computation, statutory deductions such as PF/ESI, payslip generation) is **not currently in scope**. Attendance, leave, and commission are tracked for operational and incentive purposes only. Confirm before Phase 9 (see Section 74) whether payroll processing should be added to the roadmap.

### Customers

- Customer profile
- Contact information
- Preferences
- Tags
- Notes
- Visit history
- Appointment history
- Purchase history
- Membership
- Packages
- Loyalty
- Wallet
- Feedback

Customer identity must be shared across enabled business modules.

### Services Core

- Categories
- Services
- Variants
- Add-ons
- Durations
- Pricing
- Taxes
- Staff capability
- Branch availability

Services must identify their originating vertical/module where applicable.

### Appointment Engine

- Booking
- Calendar
- Availability
- Staff allocation
- Resource allocation
- Walk-ins
- Waitlist
- Check-in
- Reschedule
- Cancellation
- No-show
- Completion
- Rebooking
- Recurring appointments

Appointment conflict checks must occur server-side.

### Resource Management

Examples: chair, room, treatment bed, nail station, wash station, steam room, sauna.

Resources belong to branches.

### Pricing

Support:

- Base pricing
- Branch pricing
- Variants
- Membership benefits
- Package pricing
- Promotional pricing
- Discount
- Tax

Pricing calculations must occur server-side.

### Packages

Packages may contain services from multiple enabled modules.

Track:

- Validity
- Purchased quantity
- Redeemed quantity
- Remaining quantity
- Expiry
- Redemption history

### Membership

Support:

- Membership plans
- Validity
- Module applicability
- Branch applicability
- Service applicability
- Benefits
- Discounts
- Usage limits
- Renewal

### Inventory

- Products
- Categories
- Brands
- Units
- Branch stock
- Stock movements
- Service consumption
- Transfers
- Adjustments
- Damage
- Expiry
- Low-stock alerts

Inventory must use a stock ledger. Do not treat a mutable quantity column as the authoritative transaction history.

### Suppliers & Purchasing

- Suppliers
- Purchase requests
- Purchase orders
- Goods receipt
- Supplier invoices
- Purchase returns
- Supplier payments

### POS

POS must support:

- Services
- Products
- Packages
- Memberships
- Gift cards
- Discounts
- Tax
- Loyalty redemption
- Wallet redemption
- Split payment
- Tips

### Invoice

Support: draft, finalized, paid, partially paid, void, refunded.

Finalized financial records must not be silently modified or deleted.

### Payments

Support extensible payment methods.

Initial examples: cash, card, UPI, bank transfer, wallet, gift card.

External payment gateways must be implemented behind provider interfaces/adapters.

### Refunds

Support: full refund, partial refund, credit note.

Refunds must correctly reverse affected:

- Financial entries
- Inventory
- Commission
- Loyalty
- Wallet
- Package/membership usage

where applicable.

### Expenses

- Expense categories
- Branch expenses
- Vendor
- Tax
- Payment method
- Attachment
- Approval where configured

### Cash Register

- Opening cash
- Cash sales
- Cash expense
- Cash refund
- Cash in/out
- Expected closing
- Actual closing
- Difference

### Commission

Support:

- Service commission
- Product commission
- Package commission
- Membership commission
- Fixed commission
- Percentage
- Slab rules
- Targets
- Incentives

### Loyalty

Use a transaction ledger. Do not maintain only a mutable points balance.

### Wallet

Use a transaction ledger. Financial wallet entries must be traceable and reversible.

### Gift Cards / Vouchers

Support: issuance, value, balance, redemption, expiry, cancellation where legally/business appropriate.

### Marketing

- Customer segmentation
- Campaigns
- Templates
- WhatsApp
- SMS
- Email
- Birthday campaigns
- Re-engagement
- Membership renewal
- Package expiry
- Feedback requests

Marketing communication must respect customer consent and applicable communication preferences. (See Section 36, Data Privacy & Retention.)

### Notifications

Support: in-app, email, SMS, WhatsApp.

Providers must remain replaceable.

### Reports

Support: sales, appointments, customers, employees, inventory, purchasing, expenses, tax, commission, membership, packages, loyalty, marketing, branch performance, module performance.

Reports must enforce tenant and branch authorization.

### Audit

Audit security-sensitive and financially significant actions.

## 15. Salon Module

Salon-specific capabilities include:

- Salon service catalogue
- Hair profile
- Hair consultation
- Hair/scalp concerns
- Treatment recommendation
- Hair treatment history
- Color formula
- Color history
- Stylist capability
- Salon chair/station allocation
- Salon product consumption

Possible categories: haircut, styling, wash, hair spa, hair treatment, coloring, straightening, smoothing, keratin, extensions, beard, shaving, grooming, scalp treatment.

## 16. Beauty Parlour Module

Beauty-specific capabilities include:

- Beauty service catalogue
- Skin profile
- Skin consultation
- Treatment recommendation
- Treatment plans
- Treatment sessions
- Session progress
- Bridal management
- Bridal events
- Makeup management
- Before/after records with consent
- Beauty product consumption

Possible categories: facial, cleanup, waxing, threading, bleach, manicure, pedicure, nail care, nail art, makeup, bridal makeup, skin treatment, body polishing, hand/foot care.

Bridal may include: engagement, haldi, mehendi, sangeet, wedding, reception.

Each event may have: date, time, venue, services, staff, travel charges, payments, notes.

> Before/after records fall under Section 36, Data Privacy & Retention — consent, retention period, and deletion workflow apply.

## 17. Spa Module

Spa-specific capabilities include:

- Spa service catalogue
- Spa consultation
- Therapy plan
- Therapy/session records
- Therapist assignment
- Room management
- Room availability
- Room turnaround
- Couple bookings
- Spa product consumption

Possible categories: massage, body therapy, body scrub, body wrap, aromatherapy, hydrotherapy, steam, sauna, reflexology, couple spa, Ayurvedic therapy, wellness package.

Spa scheduling may require:

```
Customer + Therapist + Room/resource + Time slot
```

All required resources must be available before booking confirmation.

## 18. Database Rules

Use:

- Foreign keys where appropriate
- Indexes
- Unique constraints
- Composite indexes
- Transactions
- Explicit relationships

Every tenant-owned table must consider indexes beginning with `tenant_id` based on actual query patterns.

Common examples: `(tenant_id, branch_id)`, `(tenant_id, status)`, `(tenant_id, created_at)`

Appointment-specific indexes may include: `(tenant_id, branch_id, appointment_date)`, `(tenant_id, employee_id, appointment_date)`

Do not add indexes blindly. Indexes must support real queries.

## 19. Primary Keys

Use one consistent primary-key strategy throughout the project. Do not change identifier strategy module by module.

If public identifiers are needed, use separate UUID/ULID/public reference fields where appropriate without casually changing internal relational keys.

> **Proposed default (confirm before Phase 0 begins):** Auto-incrementing unsigned `bigint` internal primary keys for all tables (fast joins/indexes, small footprint, MySQL/shared-hosting friendly), paired with a separate ULID/UUID public-facing reference column on entities exposed externally (invoice numbers, API resources, customer-facing booking references). Internal keys should not be exposed in URLs/APIs for tenant-owned resources.

## 20. Money

Never use FLOAT or DOUBLE for money. Use:

- DECIMAL with documented precision/scale

or

- integer minor units

according to the project's established convention. All calculations must use one consistent strategy.

Never trust financial totals calculated by the browser. Server must calculate:

- Subtotal
- Discounts
- Taxes
- Commissions
- Refunds
- Wallet impact
- Loyalty impact
- Totals

> **Proposed default (confirm before Phase 0 begins):** `DECIMAL(12,2)` for all monetary columns; single currency per tenant. Confirm whether multi-currency per tenant/branch is genuinely required — if yes, revisit this default before Phase 6. Rounding: round-half-up at 2 decimal places, applied only to final calculated totals per line item/invoice, never to intermediate values.

## 21. Tax & Invoice Compliance

The target market(s) for this platform must be explicitly confirmed, since invoicing and tax rules are jurisdiction-specific and directly affect the invoice/tax data model designed in Phase 6.

If India is a target market, the platform must additionally support:

- GST-compliant sequential invoice numbering (unbroken, per branch, per financial year, as legally required)
- CGST/SGST vs IGST determination based on the tenant/branch's registered state vs. the transaction's place of supply
- HSN codes for products and SAC codes for services on invoices
- Per-branch GSTIN, where a tenant's branches are registered in different states
- e-Invoicing/IRN generation if the tenant crosses the applicable turnover threshold (design the schema to allow this later; do not implement until required)
- TDS/TCS considerations for supplier payments where applicable

> **Decision needed:** Confirm target country/countries before Phase 6 (POS/Invoice/Payment/Refund) begins, so invoice numbering and tax fields are modeled correctly from the first migration. Retrofitting statutory invoice numbering after production data exists is high-risk.

## 22. Time and Timezones

Store canonical timestamps consistently. Tenant timezone must be explicitly configured. Convert timestamps for display using tenant timezone.

Appointment scheduling must respect branch timezone if branch-specific timezone support is introduced.

Never rely on server local timezone for business logic.

## 23. Database Transactions

Use DB transactions for multi-record business operations. Examples:

- Checkout
- Invoice finalization
- Payment
- Refund
- Stock transfer
- Package redemption
- Wallet operation
- Loyalty redemption
- Membership purchase
- Commission finalization

If one critical step fails, the transaction must roll back.

Do not perform external API calls inside long-running database transactions unless the architecture explicitly requires it.

## 24. Concurrency

Prevent race conditions for:

- Appointment booking
- Room allocation
- Resource allocation
- Stock decrement
- Package redemption
- Gift card redemption
- Wallet redemption
- Loyalty redemption
- Payment processing
- Invoice numbering

Use appropriate: database transactions, row locking, unique constraints, idempotency, atomic updates.

Do not assume requests occur sequentially.

## 25. Authentication Security

Use Laravel's supported authentication mechanisms.

Requirements:

- Secure password hashing
- CSRF protection
- Session regeneration after login
- Logout invalidation
- Login rate limiting
- Password reset expiry
- Secure cookies in production
- HTTPS
- Email verification where configured

Never implement custom cryptography for authentication.

## 26. Authorization

Use: middleware, policies, gates/permissions, tenant context, branch context.

Controllers must not become the sole authorization layer. Sensitive operations must explicitly authorize the action.

## 27. Validation

Every external input is untrusted. Validate using Form Requests or equivalent structured validation.

Validate: type, format, range, enum, length, ownership, tenant scope, branch scope, business state.

Client-side validation is UX only. Server-side validation is mandatory.

## 28. Mass Assignment

Do not use `request->all()` for model persistence. Use validated and explicitly allowed fields.

Sensitive fields must never be mass assignable from untrusted requests. Examples: `tenant_id`, role, permissions, subscription status, invoice totals, payment status, wallet balance, loyalty balance, commission amount.

## 29. SQL Injection

Use Eloquent/query builder parameter binding. Never concatenate untrusted values into SQL.

Raw SQL requires documented justification and parameter binding.

## 30. XSS

Escape user-generated content by default. Blade output should use escaped rendering unless sanitized trusted HTML is explicitly required.

Do not render arbitrary customer/staff HTML.

## 31. CSRF

All state-changing browser requests must use Laravel CSRF protection.

Do not disable CSRF globally to solve integration problems. External webhook routes must use provider-specific signature verification instead.

## 32. IDOR Prevention

Never assume possession of a record ID grants access. Before returning or modifying a resource verify:

- Tenant ownership
- Branch authorization
- Permission
- Resource-specific policy

This applies to API and web routes.

## 33. File Upload Security

Validate: file type, MIME type, extension, size, authorization, storage destination.

Generate server-controlled filenames. Never trust uploaded filenames.

Sensitive files must not be publicly accessible. Use authorized download endpoints or signed access where appropriate.

## 34. Tenant Storage Quota

Shared hosting environments have finite, often modest disk quotas. Uploaded content (before/after photos, attachments, exports, documents) can grow unpredictably per tenant.

Requirements:

- Track storage consumption per tenant (and optionally per branch)
- Enforce a configurable storage limit per subscription plan
- Warn tenants approaching their quota
- Reject new uploads that would exceed quota with a clear business error, not a generic 500
- Provide Super Admin visibility into per-tenant storage usage

Design the abstraction so storage limits can be relaxed or removed when migrating to object storage (see Section 65, Future Scalability) without an architecture rewrite.

## 35. Sensitive Information

Never log: passwords, password reset tokens, API secrets, payment credentials, access tokens, private keys, full sensitive customer data unnecessarily.

Secrets belong in environment configuration. Never commit secrets to Git.

## 36. Data Privacy & Retention

The platform stores sensitive personal data beyond typical business records, including skin/hair consultation notes and before/after photographs captured with customer consent (see Section 16, Beauty Parlour Module).

Requirements:

- Consent must be captured and stored (who consented, when, for what purpose/scope) before storing before/after images or sensitive consultation detail
- Define a retention period for consultation photos and sensitive notes; do not retain indefinitely by default
- Support a customer data deletion/erasure request workflow, scoped to non-financial, non-audit data
- Deletion requests must **NOT** be permitted to remove or corrupt finalized financial records, invoices, or audit logs (Sections 42, 47) — sensitive media/notes are deletable; financial/audit history is not
- Marketing communications (Section 14, Marketing) must respect explicit customer consent and provide opt-out; where SMS/WhatsApp campaigns are used in India, confirm DLT/TRAI registration and WhatsApp Business API opt-in requirements before Phase 11

> **Decision needed:** Confirm the applicable data protection regime (e.g., India's DPDP Act, or another jurisdiction) so retention periods and the erasure workflow are modeled correctly, ideally before Phase 3 (Customer CRM) and Phase 4 (vertical consultation records) begin.

## 37. Payment Security

Never store raw card details. Use payment provider tokenization/hosted payment flows.

Webhook processing must verify: provider signature, expected event, amount, currency, merchant/account context, idempotency.

Duplicate webhook delivery must not create duplicate payments.

## 38. API Security

APIs must use appropriate authentication.

Enforce: tenant scope, permissions, rate limits, validation, pagination, safe error responses.

Never expose internal exceptions or stack traces through production APIs.

## 39. Error Handling

Errors must be handled intentionally. Categorize errors as:

`VALIDATION ERROR` · `AUTHENTICATION ERROR` · `AUTHORIZATION ERROR` · `NOT FOUND` · `CONFLICT` · `BUSINESS RULE ERROR` · `EXTERNAL SERVICE ERROR` · `SYSTEM ERROR`

Expected business failures must not become generic 500 errors. Examples:

- Slot unavailable → conflict/business error
- Insufficient stock → business error
- Membership expired → business error
- Unauthorized branch → authorization error

## 40. Production Error Responses

Production users must never receive: stack traces, SQL queries, filesystem paths, environment values, secrets, internal class details.

Return a safe message and correlation/request identifier where implemented. Log technical details server-side.

## 41. Logging

Use structured contextual logging where practical.

Useful context: request ID, tenant ID, branch ID, user ID, action, entity type, entity ID.

Never expose sensitive data unnecessarily.

## 42. External Services

Integrations must be behind abstractions/interfaces. Examples: `PaymentProvider`, `WhatsAppProvider`, `SmsProvider`, `EmailProvider`, `StorageProvider`.

External failures must not corrupt local state. Use: timeouts, retries, backoff, queueing where appropriate, idempotency, failure logging.

Never retry irreversible operations blindly.

## 43. Queues

Use queued jobs for slow/non-critical work: email, WhatsApp, SMS, exports, report generation, image processing, non-critical notifications.

Shared hosting must support database-backed queue processing through cron/scheduled execution where persistent workers are unavailable.

Business-critical state changes must not depend exclusively on a queue succeeding.

## 44. Scheduler

Scheduled operations may include: reminders, package expiry checks, membership expiry, birthday campaigns, inactive customer campaigns, low stock alerts, reporting aggregation.

Tasks must be idempotent where practical. Running the same scheduled job twice must not create duplicate financial or communication effects.

## 45. Financial Integrity

Never silently edit/delete finalized financial records. Use: void, reversal, refund, credit note, adjustment.

Every financial change must be traceable.

## 46. Inventory Integrity

Every stock change must create a stock movement. Examples: `PURCHASE`, `SALE`, `SERVICE_CONSUMPTION`, `TRANSFER_IN`, `TRANSFER_OUT`, `ADJUSTMENT`, `RETURN`, `DAMAGE`, `EXPIRY`.

Stock should be reconstructable from its ledger.

## 47. Audit Logging

Audit at minimum: login/security events where useful, role changes, permission changes, tenant module changes, branch module changes, service price changes, invoice finalization, invoice void, payment, refund, stock adjustment, wallet adjustment, loyalty adjustment, subscription changes, impersonation.

Audit logs must not be editable by ordinary tenant users.

## 48. Soft Deletes

Use soft deletion selectively. Do not use deletion as a substitute for proper business lifecycle states.

Financial ledgers and audit history should generally remain immutable.

## 49. Controllers

Controllers should be thin.

Controller responsibility: accept request, authorize, call application/domain service/action, return response.

Do not place large business workflows directly inside controllers.

## 50. Models

Models should define: relationships, casts, scopes, small domain helpers.

Avoid giant models containing unrelated workflows.

## 51. Business Logic

Complex workflows belong in: Actions, Services, Domain classes — according to established project conventions.

Examples: `BookAppointment`, `CompleteAppointment`, `CheckoutSale`, `ProcessRefund`, `RedeemPackage`, `TransferStock`.

## 52. Events

Use events when multiple independent side effects follow a successful domain operation.

Example: `AppointmentCompleted` → inventory consumption → loyalty processing → notification → reporting update.

Do not hide critical business state changes in unpredictable event chains.

## 53. Database Migrations

Never modify an already deployed migration merely to change production schema. Create a new migration.

Migrations must have safe rollback behavior where feasible.

Consider existing production data before adding: non-null columns, unique constraints, foreign keys, enum-like constraints.

## 54. Performance

Avoid: N+1 queries, unbounded queries, loading huge collections into memory, unnecessary joins, repeated settings queries, repeated permission queries.

Use: eager loading, pagination, indexes, aggregation, caching, chunking, queues — when appropriate.

## 55. Reporting Performance

Large reports must not degrade operational screens.

As data grows, use: aggregate queries, summary tables, scheduled aggregation, export jobs.

Do not calculate expensive lifetime analytics on every dashboard request.

## 56. Cache

Good cache candidates include: tenant settings, branch settings, module assignments, plan features, permissions, service configuration, tax settings.

Cache invalidation must occur after relevant configuration changes.

Do not cache authorization-sensitive data without tenant/user-aware keys.

## 57. Search

Global search must enforce tenant isolation.

Searchable entities may include: customers, appointments, invoices, employees, products.

Never return records outside the current tenant/branch authorization scope.

## 58. UI Principles

The application is business software. Priorities:

1. Speed
2. Clarity
3. Consistency
4. Accessibility
5. Responsiveness

Avoid decorative complexity. Critical screens such as POS and appointment calendar must minimize clicks.

## 59. Dynamic Navigation

Navigation must reflect: tenant modules, branch modules, plan features, user permissions.

Hidden navigation is **NOT** authorization. Server-side authorization remains mandatory.

## 60. Mobile Responsiveness

All primary operational screens must work on: desktop, tablet, mobile.

Future native/PWA clients must be possible without rewriting domain logic.

## 61. Testing

Every module requires automated tests.

At minimum test: success path, validation failure, authentication failure, authorization failure, tenant isolation, branch isolation, disabled module, disabled feature, business-rule conflict, transaction rollback, relevant concurrency-sensitive behavior.

Financial modules require stronger test coverage.

## 62. Tenant Isolation Test Requirement

For every tenant-aware resource: create Tenant A, create Tenant B, create resource under Tenant A, authenticate as Tenant B, attempt view/edit/delete/export/API fetch.

All must fail safely. This test is mandatory.

## 63. Error Path Testing

Do not test only successful requests. Test: duplicate request, expired resource, invalid state transition, unavailable employee, unavailable room, insufficient inventory, duplicate payment webhook, external provider timeout, unauthorized branch, module disabled during operation.

## 64. Shared Hosting Compatibility

Before adding infrastructure dependencies, verify they are compatible with shared hosting.

Do not introduce a mandatory dependency on: persistent worker, Redis, WebSocket server, root server access, system daemon — unless explicitly approved.

## 65. Future Scalability

Code must remain portable to VPS/cloud infrastructure. Do not write code dependent on a particular shared-host filesystem layout.

Use Laravel abstractions for: storage, cache, queues, mail, database, filesystem.

## 66. Data Export / Tenant Portability

Architecture should allow future tenant data export. Export must be tenant scoped and permission protected.

Large exports should be queued when infrastructure permits.

## 67. Backup & Recovery

Production deployment documentation must cover: database backups, uploaded files, environment configuration, restore process.

Backups must not be publicly accessible.

## 68. Code Quality

Code must be: readable, typed where appropriate, modular, testable, documented where reasoning is non-obvious, consistent with Laravel conventions.

Avoid premature abstractions. Do not create unnecessary repositories/services simply to increase architecture complexity.

## 69. Claude Code Working Rules

Before modifying code:

1. Read CLAUDE.md.
2. Read relevant /docs specification.
3. Inspect existing implementation.
4. Inspect migrations/models/relationships.
5. Identify tenant boundary.
6. Identify branch boundary.
7. Identify module/feature requirements.
8. Identify authorization requirements.
9. Identify financial/inventory effects.
10. Identify tests required.

Do not begin implementation before understanding these dependencies.

## 70. Never Assume Missing Requirements

If a business rule is ambiguous and could affect: security, tenancy, money, inventory, permissions, subscription, historical data, API compatibility — do not invent behavior silently.

Document the ambiguity and request/record a decision before implementing irreversible architecture.

## 71. Do Not Rewrite Working Architecture Casually

Do not: replace tenant architecture, change primary key strategy, replace authentication, change money representation, change permission architecture, rename core business identifiers, replace queue/storage strategy — without explicit architectural approval.

## 72. Implementation Workflow

For each module:

```
REQUIREMENTS → DATA MODEL → SECURITY MODEL → MIGRATIONS → MODELS → POLICIES →
DOMAIN/ACTIONS → VALIDATION → CONTROLLERS → ROUTES → UI/API → TESTS →
SECURITY REVIEW → PERFORMANCE REVIEW → DOCUMENTATION
```

Do not mark a module complete merely because CRUD pages work.

## 73. Definition of Done

A feature is complete only when:

- Requirements are implemented
- Tenant isolation is verified
- Branch authorization is verified
- Module access is enforced
- Permissions are enforced
- Validation exists
- Errors are handled
- Transactions are correct
- Audit requirements are implemented
- Tests pass
- No known security issue remains
- Relevant documentation is updated

## 74. Development Priority

- **Phase 0:** Architecture and foundations
- **Phase 1:** Authentication, Tenancy, RBAC, Super Admin, Modules, Plans, Subscriptions, Onboarding
- **Phase 2:** Organization, Branches, Employees, Schedules, Resources
- **Phase 3:** Customer CRM
- **Phase 4:** Service catalogue, Pricing, Salon/Beauty/Spa vertical services
- **Phase 5:** Appointment and availability engine
- **Phase 6:** POS, Invoice, Payment, Refund
- **Phase 7:** Products, Inventory, Suppliers, Purchasing, Consumption
- **Phase 8:** Packages, Membership, Loyalty, Wallet, Gift Cards
- **Phase 9:** Attendance, Leave, Commission, Incentives *(payroll processing is out of scope — see Section 14 note)*
- **Phase 10:** Expenses, Cash register
- **Phase 11:** Notifications, Email, SMS, WhatsApp, Marketing
- **Phase 12:** Reports, Dashboards, Exports
- **Phase 13:** Online booking, Customer self-service *(see Section 75, Public-Facing Booking Security, before starting this phase)*
- **Phase 14:** Security hardening, Performance, Backup/restore, Production deployment

## 75. Public-Facing Booking Security

Phase 13 (Online booking, Customer self-service) introduces the platform's first unauthenticated, public-facing surface. This has a different threat model than the authenticated back-office application and needs explicit treatment before implementation begins:

- Rate limit public booking/availability endpoints per IP and per tenant to prevent scraping and abuse
- Never expose more information than a prospective customer needs (e.g., no internal staff cost/commission data, no other customers' bookings, no cross-tenant data)
- Apply bot/spam protection (e.g., honeypot fields, throttling) on public booking form submission, consistent with Section 9 (Access Evaluation) principles
- Treat public booking requests as untrusted input subject to the same validation, tenant/branch/module checks, and availability revalidation as authenticated bookings
- Public endpoints must not leak whether a given email/phone is already a registered customer beyond what the booking flow legitimately requires

This section supplements, not replaces, the general Access Evaluation (Section 9) and API Security (Section 38) requirements.

## 76. Core Principle

Correctness and security take priority over development speed.

Never sacrifice: tenant isolation, authorization, financial integrity, inventory integrity, auditability, data integrity — to make implementation faster.

When unsure: inspect existing architecture, follow Laravel conventions, protect tenant boundaries, use database constraints, use transactions for atomic workflows, validate server-side, authorize server-side, write tests, and preserve historical records.
