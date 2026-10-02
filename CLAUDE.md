# Beauty Business SaaS — Project Constitution (condensed)

Full original text: `docs/CLAUDE-full.md`. Section numbers (§) are stable — code comments cite them. Detailed specs live in `/docs`.

## 1. Purpose
Production multi-tenant SaaS for Salon, Beauty Parlour and Spa. One platform: shared Core + independently assignable vertical modules. A tenant may run any combination; Super Admin controls which modules each tenant gets. Built for long-term scalability, security, maintainability, multi-branch operation, future mobile/API clients.

## 2. Technology
Laravel (+ compatible PHP), MySQL, Blade, Tailwind, Alpine.js, Vite, REST/JSON where needed, Scheduler, Queue, PHPUnit/Pest.
Initial target: shared hosting, MySQL, cron, database queue (no workers), file/database cache. Must allow later move to VPS/cloud, Redis, workers, object storage, multiple instances, load balancer, managed DB.
Do NOT make Redis, WebSockets, Docker, Kubernetes, Elasticsearch, RabbitMQ, Kafka or persistent background processes mandatory.

## 3. Architecture
Modular monolith, NO microservices. Platform → Core, Salon, Beauty Parlour, Spa. Shared capabilities in Core; vertical behaviour in its module. Never duplicate shared concepts per vertical (`customers`, not `salon_customers`).

## 4. SaaS hierarchy
Platform → Tenant → Branch → Users/Employees → Business operations. Every tenant-owned record has `tenant_id`; branch-specific records also `branch_id`.

## 5. Platform layer (Super Admin)
Auth, dashboard, tenant/module/plan/feature/subscription management, activation/suspension, trials, usage limits, config, audit logs, reporting. Super Admin and tenant admin stay logically separated.

## 6. Modules
`salon`, `beauty`, `spa` — stable machine codes, never display labels as identifiers.

## 7. Tenant module assignment
Super Admin enables/disables any module per tenant. Database-driven; never hard-code. Disabling must NOT delete history (appointments, invoices, payments, treatments, sessions, customer history, inventory transactions, audit).

## 8. Branch module assignment
Branches may run different modules. Tenant enablement is the upper bound; a branch cannot enable a module its tenant lacks.

## 9. Access evaluation
Check in order: authentication, account status, tenant status, subscription status, tenant module, branch module, plan feature, role/permission, resource ownership/scope. Hidden menus are not authorization — unauthorized routes/controllers/APIs must be inaccessible when requested manually.

## 10. Plans, modules, features, permissions — keep separate
MODULE = business vertical. FEATURE = system capability (`inventory`, `online_booking`, `loyalty`, `advanced_reports`, `whatsapp`). PLAN = commercial package of features + limits. PERMISSION = user action (`appointments.view`). Never substitute one for another.

## 11. Multi-tenancy
Single app + single MySQL DB + shared schema + `tenant_id`. Tenant isolation is a SECURITY BOUNDARY. Every tenant-owned record has `tenant_id` unless a documented exception exists. Never trust `tenant_id` from request body, query, route, hidden field, JS or API client — it comes from authenticated server-side context.

## 12. Tenant isolation
Tenant A must never reach Tenant B data via URL manipulation, API, exports, reports, autocomplete, search, downloads, attachments, queued jobs, notifications or background tasks. Every tenant-aware module needs automated cross-tenant tests.

## 13. Branch isolation
Users may have all, selected, or one branch. Check branch access server-side; never trust a submitted `branch_id`.

## 14. Common Core (capabilities)
- **Organization:** profile, settings, tax, currency, timezone, hours, policies. **Branches:** management, hours, holidays, module availability.
- **Users & RBAC:** users, roles, permissions, branch/module access, sessions.
- **Employees:** profile, branches, role, skills, services, shifts, attendance, leave, commission, incentives, performance. Full payroll (salary, PF/ESI, payslips) is OUT of scope — confirm before Phase 9.
- **Customers:** profile, contact, preferences, tags, notes, visit/appointment/purchase history, membership, packages, loyalty, wallet, feedback. Identity shared across modules.
- **Services:** categories, services, variants, add-ons, durations, pricing, taxes, staff capability, branch availability; identify originating module.
- **Appointment engine:** booking, calendar, availability, staff/resource allocation, walk-ins, waitlist, check-in, reschedule, cancel, no-show, complete, rebook, recurring. Conflict checks server-side.
- **Resources:** chairs, rooms, beds, stations, steam rooms, saunas — belong to branches.
- **Pricing:** base, branch, variants, membership benefits, package, promo, discount, tax — all server-side.
- **Packages:** multi-module; track validity, purchased/redeemed/remaining qty, expiry, redemption history. **Membership:** plans, validity, module/branch/service applicability, benefits, discounts, usage limits, renewal.
- **Inventory:** products, categories, brands, units, branch stock, movements, service consumption, transfers, adjustments, damage, expiry, low-stock alerts. Use a stock LEDGER, not a mutable quantity column.
- **Suppliers & purchasing:** suppliers, requests, POs, goods receipt, supplier invoices, returns, payments.
- **POS:** services, products, packages, memberships, gift cards, discounts, tax, loyalty/wallet redemption, split payment, tips.
- **Invoice:** draft, finalized, paid, partially paid, void, refunded. Finalized records are never silently edited/deleted.
- **Payments:** extensible methods (cash, card, UPI, bank, wallet, gift card); gateways behind provider interfaces/adapters.
- **Refunds:** full, partial, credit note — must reverse financial entries, inventory, commission, loyalty, wallet, package/membership usage where applicable.
- **Expenses**, **Cash register** (opening, sales, expense, refund, cash in/out, expected vs actual closing, difference), **Commission** (service/product/package/membership, fixed, %, slabs, targets, incentives).
- **Loyalty** and **Wallet:** transaction ledgers (traceable, reversible), never just a mutable balance. **Gift cards:** issue, value, balance, redeem, expire, cancel.
- **Marketing:** segmentation, campaigns, templates, WhatsApp/SMS/email, birthday, re-engagement, renewal, expiry, feedback — respect consent (§36).
- **Notifications:** in-app, email, SMS, WhatsApp; replaceable providers. **Reports:** sales, appointments, customers, employees, inventory, purchasing, expenses, tax, commission, membership, packages, loyalty, marketing, branch/module performance — enforce tenant + branch authorization. **Audit:** security-sensitive and financially significant actions.

## 15. Salon module
Service catalogue, hair profile/consultation/concerns, treatment recommendation/history, color formula/history, stylist capability, chair/station allocation, product consumption. Categories: haircut, styling, wash, hair spa/treatment, coloring, straightening, smoothing, keratin, extensions, beard, shaving, grooming, scalp.

## 16. Beauty Parlour module
Service catalogue, skin profile/consultation, treatment plans/sessions/progress, bridal management/events (engagement, haldi, mehendi, sangeet, wedding, reception — each with date, time, venue, services, staff, travel charges, payments, notes), makeup, before/after records WITH consent (§36), product consumption. Categories: facial, cleanup, waxing, threading, bleach, manicure, pedicure, nail care/art, makeup, bridal, skin treatment, body polishing, hand/foot care.

## 17. Spa module
Service catalogue, consultation, therapy plan/sessions, therapist assignment, rooms/availability/turnaround, couple bookings, product consumption. Booking may need Customer + Therapist + Room/resource + Time slot — ALL available before confirmation. Categories: massage, body therapy/scrub/wrap, aromatherapy, hydrotherapy, steam, sauna, reflexology, couple spa, Ayurvedic, wellness packages.

## 18. Database rules
Foreign keys, indexes, unique constraints, composite indexes, transactions, explicit relationships. Indexes begin with `tenant_id` where queries need it (`(tenant_id, branch_id)`, `(tenant_id, status)`, `(tenant_id, created_at)`; appointments: `(tenant_id, branch_id, appointment_date)`, `(tenant_id, employee_id, appointment_date)`). Don't add indexes blindly.

## 19. Primary keys
One strategy project-wide. Default: auto-increment unsigned bigint internal keys + separate ULID/UUID public reference column for externally exposed entities. Don't expose internal keys in URLs/APIs for tenant-owned resources.

## 20. Money
Never FLOAT/DOUBLE. Default `DECIMAL(12,2)`, single currency per tenant; round half-up at 2 dp only on final line/invoice totals. Server calculates all subtotals, discounts, taxes, commissions, refunds, wallet/loyalty impact, totals — never trust browser totals. (Confirm multi-currency need before Phase 6.)

## 21. Tax & invoice compliance
Confirm target countries before Phase 6. If India: GST sequential invoice numbering (unbroken, per branch, per financial year); CGST/SGST vs IGST by place of supply; HSN (products) / SAC (services); per-branch GSTIN; e-invoicing/IRN schema-ready but not built until required; TDS/TCS for supplier payments where applicable.

## 22. Time and timezones
Store canonical timestamps consistently; tenant timezone configured explicitly; display in tenant (or branch) timezone; never use server local timezone for business logic.

## 23. Transactions
Use DB transactions for checkout, invoice finalization, payment, refund, stock transfer, package redemption, wallet/loyalty operations, membership purchase, commission finalization. Critical step fails → roll back. No external API calls inside long transactions.

## 24. Concurrency
Prevent races on appointment/room/resource booking, stock decrement, package/gift-card/wallet/loyalty redemption, payment processing, invoice numbering — via transactions, row locks, unique constraints, idempotency, atomic updates. Never assume sequential requests.

## 25. Authentication security
Laravel's mechanisms only: secure hashing, CSRF, session regeneration on login, logout invalidation, login rate limiting, reset expiry, secure cookies in production, HTTPS, email verification where configured. No custom crypto.

## 26. Authorization
Middleware, policies, gates/permissions, tenant + branch context. Controllers are not the only layer; sensitive operations authorize explicitly.

## 27. Validation
All input untrusted; Form Requests. Validate type, format, range, enum, length, ownership, tenant scope, branch scope, business state. Client-side validation is UX only.

## 28. Mass assignment
Never `request->all()` for persistence. Sensitive fields (`tenant_id`, role, permissions, subscription status, invoice totals, payment status, wallet/loyalty balance, commission amount) are never mass-assignable from untrusted input.

## 29–31. SQL injection, XSS, CSRF
Eloquent/query-builder bindings only; raw SQL needs justification + bindings. Blade escaped output by default; no arbitrary HTML. All state-changing browser requests use CSRF; never disable globally — webhooks use provider signature verification.

## 32. IDOR
Possessing an ID grants nothing. Verify tenant ownership, branch authorization, permission and resource policy on every web and API route.

## 33. File uploads
Validate type, MIME, extension, size, authorization, destination; server-generated filenames; sensitive files not public (authorized/signed downloads).

## 34. Tenant storage quota
Track per-tenant (optionally per-branch) usage; per-plan limit; warn near quota; reject over-quota uploads with a clear business error (not 500); Super Admin visibility; abstraction removable on object storage. (Not yet implemented.)

## 35. Sensitive information
Never log passwords, reset tokens, API secrets, payment credentials, access tokens, private keys, or unnecessary customer data. Secrets in env config, never in Git.

## 36. Data privacy & retention
Capture consent (who, when, scope) before storing before/after images or sensitive consultation detail; define retention periods; support customer erasure workflow for non-financial, non-audit data; erasure must never remove/corrupt finalized financial records or audit logs; marketing respects consent + opt-out (India SMS/WhatsApp: confirm DLT/TRAI and WhatsApp opt-in before Phase 11). Confirm data-protection regime (e.g. DPDP) before Phase 3/4.

## 37. Payment security
Never store raw card data; use provider tokenization/hosted flows. Webhooks verify signature, event, amount, currency, merchant context, idempotency; duplicate delivery must not duplicate payments.

## 38. API security
Appropriate auth; enforce tenant scope, permissions, rate limits, validation, pagination, safe errors; no stack traces in production APIs.

## 39–40. Error handling and production responses
Categories: validation, authentication, authorization, not found, conflict, business rule, external service, system. Expected business failures (slot unavailable, insufficient stock, membership expired, unauthorized branch) are not generic 500s. Production users never see stack traces, SQL, paths, env values, secrets or class details — safe message + correlation ID; technical detail logged server-side.

## 41. Logging
Structured, contextual (request ID, tenant, branch, user, action, entity). No sensitive data.

## 42. External services
Behind interfaces (`PaymentProvider`, `WhatsAppProvider`, `SmsProvider`, `EmailProvider`, `StorageProvider`). External failure must not corrupt local state: timeouts, retries, backoff, queueing, idempotency, failure logging. Never blindly retry irreversible operations.

## 43–44. Queues and scheduler
Queue slow/non-critical work (email, WhatsApp, SMS, exports, reports, image processing). Database queue via cron on shared hosting. Business-critical state must not depend solely on a queue. Scheduled tasks are idempotent — running twice never duplicates financial or communication effects.

## 45. Financial integrity
Never silently edit/delete finalized financial records — use void, reversal, refund, credit note, adjustment. Every financial change is traceable.

## 46. Inventory integrity
Every stock change creates a movement (`PURCHASE`, `SALE`, `SERVICE_CONSUMPTION`, `TRANSFER_IN/OUT`, `ADJUSTMENT`, `RETURN`, `DAMAGE`, `EXPIRY`). Stock must be reconstructable from the ledger.

## 47. Audit logging
Audit at least: security events, role/permission changes, tenant/branch module changes, service price changes, invoice finalization/void, payment, refund, stock/wallet/loyalty adjustments, subscription changes, impersonation. Tenant users cannot edit audit logs.

## 48. Soft deletes
Use selectively; lifecycle states, not deletion, represent business state. Financial ledgers and audit history stay immutable.

## 49–51. Code structure
Thin controllers (accept, authorize, call action/service, respond). Models: relationships, casts, scopes, small helpers. Complex workflows in Actions/Services/Domain classes (`BookAppointment`, `CheckoutSale`, `ProcessRefund`, `RedeemPackage`, `TransferStock`).

## 52. Events
Use for multiple independent side effects after a successful operation; don't hide critical state changes in event chains.

## 53. Migrations
Never edit a deployed migration — add a new one. Safe rollback where feasible; consider existing data for non-null columns, unique constraints, foreign keys.

## 54–57. Performance, reporting, cache, search
Avoid N+1, unbounded queries, huge in-memory collections, repeated settings/permission queries. Large reports must not slow operational screens (aggregates, summary tables, scheduled aggregation, export jobs). Cache tenant/branch settings, module assignments, plan features, permissions, tax config — with tenant/user-aware keys and invalidation on change. Global search enforces tenant + branch scope.

## 58–60. UI
Business software: speed, clarity, consistency, accessibility, responsiveness. POS and calendar minimize clicks. Navigation reflects tenant/branch modules, plan features and permissions (hidden nav ≠ authorization). Primary screens work on desktop, tablet, mobile; PWA/native clients possible without rewriting domain logic.

## 61–63. Testing
Every module needs automated tests: success, validation failure, authentication failure, authorization failure, tenant isolation, branch isolation, disabled module/feature, business-rule conflict, transaction rollback, concurrency. Financial modules need stronger coverage. Mandatory isolation test: create Tenant A and B, resource under A, authenticate as B, attempt view/edit/delete/export/API fetch — all fail safely. Also test duplicate requests, expired resources, invalid state transitions, unavailable employee/room, insufficient inventory, duplicate payment webhooks, provider timeouts, unauthorized branch, module disabled mid-operation.

## 64–67. Hosting, scalability, export, backup
Verify shared-hosting compatibility before adding infrastructure; no mandatory persistent worker/Redis/WebSocket/root access without approval. Stay portable via Laravel abstractions (storage, cache, queue, mail, DB); no host-specific filesystem assumptions. Tenant data export is tenant-scoped, permission-protected, queued when possible. Deployment docs cover DB backups, uploaded files, env config, restore; backups never public.

## 68. Code quality
Readable, typed where appropriate, modular, testable, Laravel conventions. No premature abstractions or needless repositories.

## 69. Working rules (before modifying code)
Read this file and the relevant `/docs` spec; inspect existing code, migrations, models, relationships; identify tenant boundary, branch boundary, module/feature and authorization requirements, financial/inventory effects, and tests required. Don't implement before understanding these.

## 70. Never assume missing requirements
If a business rule is ambiguous and affects security, tenancy, money, inventory, permissions, subscriptions, historical data or API compatibility — don't invent behaviour; document the ambiguity and get a decision before irreversible architecture.

## 71. Don't rewrite working architecture
No changes to tenant architecture, primary-key strategy, authentication, money representation, permission architecture, core identifiers, or queue/storage strategy without explicit architectural approval.

## 72. Implementation workflow
REQUIREMENTS → DATA MODEL → SECURITY MODEL → MIGRATIONS → MODELS → POLICIES → DOMAIN/ACTIONS → VALIDATION → CONTROLLERS → ROUTES → UI/API → TESTS → SECURITY REVIEW → PERFORMANCE REVIEW → DOCUMENTATION. CRUD working ≠ module complete.

## 73. Definition of done
Requirements implemented; tenant isolation, branch authorization, module access, permissions verified; validation, error handling, transactions, audit in place; tests pass; no known security issue; docs updated.

## 74. Development phases
0 Foundations · 1 Auth/Tenancy/RBAC/Super Admin/Modules/Plans/Subscriptions/Onboarding · 2 Organization/Branches/Employees/Schedules/Resources · 3 Customer CRM · 4 Services/Pricing/vertical services · 5 Appointments/availability · 6 POS/Invoice/Payment/Refund · 7 Products/Inventory/Suppliers/Purchasing · 8 Packages/Membership/Loyalty/Wallet/Gift cards · 9 Attendance/Leave/Commission/Incentives (no payroll) · 10 Expenses/Cash register · 11 Notifications/Marketing · 12 Reports/Dashboards/Exports · 13 Online booking/Customer self-service (see §75) · 14 Security hardening/Performance/Backup/Deployment.

## 75. Public-facing booking security (Phase 13)
Rate limit public endpoints per IP and tenant; expose only what a prospective customer needs (no staff cost/commission, no other customers' bookings, no cross-tenant data); bot/spam protection (honeypot, throttling); treat requests as untrusted with the same validation, tenant/branch/module checks and availability revalidation; don't leak whether an email/phone is already a customer.

## 77. Token economy (applies to every session)
- Keep replies short; no recaps of what the user already knows. Don't print whole files or long logs — read only the needed lines.
- Run only the tests for the area changed. Run the full suite ONLY when the user asks or before a release they requested.
- Never poll or loop-wait on long runs; start them in the background once and check the result once.
- Don't commit, push, build zips/patches or write extra test files unless the user asks.
- Batch related edits into one call; don't re-read files already in context.
- If a conversation gets long, say so once and suggest a new chat or /compact.

## 76. Core principle
Correctness and security outrank speed. Never sacrifice tenant isolation, authorization, financial/inventory integrity, auditability or data integrity. When unsure: inspect existing architecture, follow Laravel conventions, protect tenant boundaries, use DB constraints and transactions, validate and authorize server-side, write tests, preserve history.
