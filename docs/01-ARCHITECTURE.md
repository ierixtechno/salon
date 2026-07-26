# Architecture

Full authority: `CLAUDE.md` §2–3, §46–53, §65, §68–76.

## Style

**Modular monolith.** Not microservices. One Laravel application, one deployable unit.

## Tech stack

- Laravel + PHP (version matching the selected Laravel release)
- MySQL
- Blade + Tailwind CSS + Alpine.js (where interactivity is needed) + Vite
- REST/JSON APIs where a non-Blade client needs them
- Laravel Queue (database driver initially) + Laravel Scheduler (cron-driven)
- Pest for tests

No Redis, WebSockets, Docker, Kubernetes, Elasticsearch, RabbitMQ, Kafka, or persistent background processes are mandatory for the initial release. The architecture must not *preclude* migrating to these later (see [10-SHARED-HOSTING.md](10-SHARED-HOSTING.md)).

## Logical layout

```
Platform            (Super Admin only)
├── Core             (shared: customers, appointments, employees, products, invoices, ...)
├── Salon module
├── Beauty Parlour module
└── Spa module
```

Shared business capability → Core. Vertical-specific behavior → the corresponding module. Never duplicate a shared concept per vertical (`customers`, not `salon_customers` / `beauty_customers` / `spa_customers`).

Suggested code layout (folder-based modular monolith — no package like laravel-modules is used; plain namespaces keep this dependency-free per the Dependency Policy in `.claude/skills/beauty-saas-development/SKILL.md` §42):

```
app/
├── Domain/
│   ├── Platform/        (Super Admin: tenants, plans, subscriptions, module mgmt)
│   ├── Core/            (Organization, Branch, User/RBAC, Employee, Customer,
│   │                      Service, Appointment, Resource, Pricing, Package,
│   │                      Membership, Inventory, POS, Invoice, Payment, Refund,
│   │                      Expense, CashRegister, Commission, Loyalty, Wallet,
│   │                      GiftCard, Marketing, Notification, Report, Audit)
│   ├── Salon/
│   ├── BeautyParlour/
│   └── Spa/
├── Http/
│   ├── Controllers/{Platform,Core,Salon,BeautyParlour,Spa}/
│   └── Middleware/
```

Each `Domain/{X}` namespace holds that domain's Models, Actions/Services, Policies, and Form Requests. Controllers stay thin (`CLAUDE.md` §49) and delegate to Actions/Services (§51).

## SaaS hierarchy

```
Platform → Tenant → Branch → Users / Employees → Business Operations
```

Every tenant-owned record belongs to a tenant. Branch-specific records additionally belong to a branch where applicable. Full detail: [02-TENANCY.md](02-TENANCY.md).

## Implementation workflow (per module)

```
REQUIREMENTS → DATA MODEL → SECURITY MODEL → MIGRATIONS → MODELS → POLICIES →
DOMAIN/ACTIONS → VALIDATION → CONTROLLERS → ROUTES → UI/API → TESTS →
SECURITY REVIEW → PERFORMANCE REVIEW → DOCUMENTATION
```

A module is not "done" because CRUD pages work — see Definition of Done, `CLAUDE.md` §73.

## Development priority (phases)

- **Phase 0:** Architecture and foundations
- **Phase 1:** Authentication, Tenancy, RBAC, Super Admin, Modules, Plans, Subscriptions, Onboarding
- **Phase 2:** Organization, Branches, Employees, Schedules, Resources
- **Phase 3:** Customer CRM
- **Phase 4:** Service catalogue, Pricing, Salon/Beauty/Spa vertical services
- **Phase 5:** Appointment and availability engine
- **Phase 6:** POS, Invoice, Payment, Refund
- **Phase 7:** Products, Inventory, Suppliers, Purchasing, Consumption
- **Phase 8:** Packages, Membership, Loyalty, Wallet, Gift Cards
- **Phase 9:** Attendance, Leave, Commission, Incentives (payroll processing out of scope — `CLAUDE.md` §14)
- **Phase 10:** Expenses, Cash register
- **Phase 11:** Notifications, Email, SMS, WhatsApp, Marketing
- **Phase 12:** Reports, Dashboards, Exports
- **Phase 13:** Online booking, Customer self-service (see `CLAUDE.md` §75, Public-Facing Booking Security)
- **Phase 14:** Security hardening, Performance, Backup/restore, Production deployment

## Primary keys, money

Foundational, hard-to-reverse choices — see [decisions/README.md](decisions/README.md) and `CLAUDE.md` §19–20 for the proposed defaults pending confirmation.

## Code quality

Readable, typed where appropriate, modular, testable, documented only where reasoning is non-obvious, consistent with Laravel conventions. No premature abstractions, no unnecessary repository/service layers for their own sake (`CLAUDE.md` §68).
