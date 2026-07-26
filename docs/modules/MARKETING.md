# Marketing Module

**Phase:** 11 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Customer segmentation and outbound campaigns.

## Core entities

Customer segmentation, campaigns, templates, WhatsApp, SMS, email, birthday campaigns, re-engagement, membership renewal, package expiry, feedback requests.

## Key business rules

- Must respect customer consent and communication preferences (`CLAUDE.md` §14) — ties directly to [05-SECURITY.md](../05-SECURITY.md) Data Privacy & Retention and decision D-004.
- Notification/messaging providers stay behind replaceable interfaces (`EmailProvider`, `SmsProvider`, `WhatsAppProvider` — [08-API-STANDARDS.md](../08-API-STANDARDS.md)).
- If targeting India: confirm DLT/TRAI SMS registration and WhatsApp Business API opt-in requirements before this phase ships anything that actually sends messages.

## Expand when Phase 11 begins.
