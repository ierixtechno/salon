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

## Implemented (Phase 7)

Schema: `product_categories`, `products`, `branch_stock` (cached balance, never authoritative), `stock_movements` (the ledger — signed `decimal(12,3)` quantity, `type` in `purchase|sale|service_consumption|transfer_in|transfer_out|adjustment|return|damage|expiry`), `suppliers`, `purchase_orders` + `purchase_order_lines`, `goods_receipts`, `purchase_returns`, `supplier_payments`, `stock_transfers`, `service_consumables`.

`GoodsReceipt`, `PurchaseReturn`, and `StockTransfer` are header-only records — their per-product detail is simply the `StockMovement` rows tagged with `reference_type`/`reference_id` pointing back at the header, rather than separate line-item tables. This keeps every stock-changing document's detail reconstructable from the same single ledger (§46) instead of duplicating it into a second table per document type.

`RecordStockMovement` is the single choke point every stock-changing action goes through: insert-if-not-exists then `lockForUpdate()` on the `branch_stock` row (same "lock a proxy row" concurrency pattern as `BookAppointment`/`CheckoutSale`), reject if the result would go negative unless the caller explicitly allows it (only `service_consumption` does — see below).

Purchase order lifecycle: `draft → ordered → {partially_received, received} → received`, `cancelled` only reachable from `draft`/`ordered` before any goods are received (mirrors Invoice's void-only-while-unpaid structure). Receiving goods accumulates `quantity_received` per line and moves stock in the same transaction as the `GoodsReceipt` row.

`RecordServiceConsumption` is a manual, staff-triggered action (a "Record product usage" button on a completed appointment) — deliberately **not** wired automatically into Phase 5's `CompleteAppointment`, since silently changing that already-shipped action's behavior wasn't something to invent without asking (CLAUDE.md §70). It is the one place `allowNegative` is set on `RecordStockMovement`, since refusing to record real product usage because the recorded balance is already low would be worse than a temporarily negative ledger entry.

**Deliberately deferred, not silently dropped:**
- Standalone Purchase Requests as a pre-PO approval stage — POs are created directly by an authorized user instead.
- TDS/TCS on supplier payments — a jurisdiction-specific compliance decision of the same weight as the GST decision in [FINANCE.md](FINANCE.md), not to be invented without a similar confirmation gate.
- Automated low-stock alerts — the stock report flags low-stock rows visually now; scheduled notifications belong to Phase 11 (Notifications).
- HSN codes on products — not needed yet, since products aren't sold through POS until packages/gift cards/etc. exist (see [POS.md](POS.md)).

Permissions: `products.view/create/update/delete`, `inventory.view/adjust`, `suppliers.view/create/update`, `purchase-orders.view/create/update`, `supplier-payments.create`. Manager gets the full operational set (products, inventory, suppliers, purchase-orders) but not `products.delete` or `supplier-payments.create` — money-leaving-the-business actions stay Owner-only, mirroring `invoices.void`/`refunds.create`. Staff gets `products.view`/`inventory.view` only.
