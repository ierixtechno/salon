# POS Module

**Phase:** 6 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Point of sale checkout — the second highest-stakes concurrency/financial surface after Appointments.

## Core entities / must support

Services, products, packages, memberships, gift cards, discounts, tax, loyalty redemption, wallet redemption, split payment, tips.

## Key business rules

Financial workflow order (`.claude/skills/beauty-saas-development/SKILL.md` §10):

```
authorize → validate → resolve authoritative prices → calculate totals server-side →
begin transaction → lock records where necessary → write financial records →
write dependent ledgers → write audit event → commit → dispatch non-critical side effects
```

Never send a notification before the financial transaction commits. Never trust browser-calculated totals — see [03-DATABASE-STANDARDS.md](../03-DATABASE-STANDARDS.md) and [FINANCE.md](FINANCE.md) for invoice/payment/refund detail.

## Expand when Phase 6 begins — alongside [FINANCE.md](FINANCE.md) and D-002/D-003 in [decisions/README.md](../decisions/README.md), which must be resolved first.
