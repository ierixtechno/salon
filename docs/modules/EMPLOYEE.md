# Employee Module

**Phase:** 2 (profile/branch/role/schedule) + Phase 9 (attendance/leave/commission/incentives) · **Domain:** Core · **Tenant scope:** tenant-scoped, branch-assigned

## Purpose

Staff who deliver services: stylists, therapists, front-desk, managers.

## Core entities

- Employee profile, branch assignment, role, skills, services capable of performing
- Shifts, attendance, leave
- Commission, incentives, performance (Phase 9)

## Key business rules

- Employee capability (which services they can perform) and schedule (shifts/leave/working hours) feed directly into the Appointment Engine's availability checks — see [APPOINTMENT.md](APPOINTMENT.md) and `.claude/skills/beauty-saas-development/SKILL.md` §16.
- **Payroll processing is out of scope** — see [decisions/README.md](../decisions/README.md) D-005. Attendance/leave/commission are operational and incentive tracking only, not a payroll engine.
- Commission must be calculated from authoritative finalized transaction data, never trusted from the UI (`.claude/skills/beauty-saas-development/SKILL.md` §22).

## Expand when Phase 2 (profile/schedule) and Phase 9 (attendance/commission) begin.
