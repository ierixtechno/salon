# Inventory Module

**Phase:** 7 · **Domain:** Core · **Tenant scope:** tenant + branch scoped

## Purpose

Products, stock, suppliers, purchasing, and consumption tracking.

## Core entities

Products, categories, brands, units, branch stock, stock movements, service consumption, transfers, adjustments, damage, expiry, low-stock alerts, suppliers, purchase requests, purchase orders, goods receipt, supplier invoices, purchase returns, supplier payments.

## Key business rules

- Ledger-based, always: every stock change produces a stock movement (`PURCHASE`, `SALE`, `SERVICE_CONSUMPTION`, `TRANSFER_IN`, `TRANSFER_OUT`, `ADJUSTMENT`, `RETURN`, `DAMAGE`, `EXPIRY`). Stock balance must be reconstructable from the ledger, never treated as an independently authoritative mutable column (`CLAUDE.md` §46).
- Stock-changing operation order: authorize → validate item/branch → start transaction → lock stock state → validate availability → create stock movement → update derived balance if maintained → commit (`.claude/skills/beauty-saas-development/SKILL.md` §15).
- No unexplained negative stock unless a tenant has explicitly configured that allowance.

## Expand when Phase 7 begins.
