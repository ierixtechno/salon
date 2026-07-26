# Reporting Module

**Phase:** 12 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Sales, appointments, customers, employees, inventory, purchasing, expenses, tax, commission, membership, packages, loyalty, marketing, branch performance, module performance.

## Key business rules

- Every report enforces tenant and branch authorization (`CLAUDE.md` §14) — a report is not a backdoor around the standard access evaluation order in [02-TENANCY.md](../02-TENANCY.md).
- Large/expensive reports must not degrade operational screens as data grows — aggregate queries, summary tables, scheduled aggregation, export jobs, not live lifetime analytics on every dashboard load (`CLAUDE.md` §55).

## Expand when Phase 12 begins.
