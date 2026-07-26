# UI/UX Standards

Full authority: `CLAUDE.md` §58–60.

## Priorities, in order

1. Speed
2. Clarity
3. Consistency
4. Accessibility
5. Responsiveness

This is business software, not a marketing site — avoid decorative complexity. POS and the appointment calendar are the highest-frequency screens in the whole app and must minimize clicks above all else.

## Full responsiveness (explicit project requirement)

Every primary operational screen — POS, calendar/appointment book, customer profile, invoice, inventory, dashboards, Super Admin console — must work correctly on **desktop, tablet, and mobile**, not just "not break." Concretely:

- Build mobile-first with Tailwind's responsive utilities; do not ship a desktop-only layout and patch breakpoints in afterward.
- Data-dense screens (POS cart, appointment calendar, inventory tables) need an explicit mobile layout strategy (e.g., stacked cards instead of wide tables) rather than relying on horizontal scroll as the only fallback.
- Touch targets, tap zones, and modal/drawer patterns are sized for tablet use at the front desk, since that's a realistic primary device for salon/spa staff.
- Test each screen at mobile/tablet/desktop breakpoints before considering it done — this is part of Definition of Done (`CLAUDE.md` §73), not a nice-to-have.

## Navigation

Reflects tenant modules, branch modules, plan features, and user permissions — but hidden navigation is never itself authorization. The server enforces access regardless of what the menu shows (see [02-TENANCY.md](02-TENANCY.md), [04-RBAC.md](04-RBAC.md)).

## Forward compatibility

Native/PWA clients must be possible later without rewriting domain logic — keep business rules in Actions/Services (`app/Domain/**`), not in Blade/controllers, so a future API-driven client can reuse them.
