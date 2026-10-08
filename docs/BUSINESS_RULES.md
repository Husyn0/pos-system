# Business Rules — Orders, Payments, Inventory

This documents the exact contracts `OrderService`, `PaymentService`, and `InventoryService` enforce, and why. Each rule has a corresponding test in `tests/Feature/` — treat a rule listed here without a passing test as a bug.

## Order state machine

```
pending --> confirmed --> preparing --> ready --> completed
   \            \             \           \
    ------------+-------------+-----------+---> cancelled
```

- Enforced centrally by `App\Services\OrderStateMachine` — every write path (POS, KDS, mobile, public tracking) goes through it. There is no code path that sets `orders.status` directly.
- `completed` and `cancelled` are terminal. You cannot "un-complete" an order — getting money back out of one is a **refund** (`PaymentService::refundPayment`), which does not touch order status at all.
- Cancellation is its own method (`OrderService::cancelOrder`), not a status transition, because it has extra rules money-related transitions need (see below). Calling `transitionStatus($order, 'cancelled')` throws on purpose — it exists to catch a future contributor trying to shortcut this.

## Inventory: reserve-at-creation, not reserve-at-completion

**The decision:** ingredients are deducted the moment an order is *created*, inside the same database transaction as the order and order_items rows — not later when the order is marked "completed." If the ingredients aren't there, the order is never created at all (the whole transaction rolls back).

**Why:** the alternative (deduct on completion) lets two terminals both accept an order for the last croissant, since neither has "used" it yet at accept-time — you find out you oversold only when the kitchen physically runs out, which is a customer-facing failure, not a database one. Deducting at creation moves that failure to before you tell the customer "confirmed."

**How the concurrency safety actually works** (`InventoryService::reserveForOrderLines`):
1. Every ingredient an order's lines require is aggregated into one demand map first — one lock acquisition per ingredient per order, not one per line.
2. Rows are locked with `SELECT ... FOR UPDATE`, **in a fixed sort order (by id) that every call site uses** — this is what prevents two concurrent orders that both need "milk" and "beans" from deadlocking each other by locking them in opposite order.
3. Sufficiency is checked only after every needed row is locked, and *all* shortages are collected and reported together — not just the first one found — so a client gets one complete answer instead of a frustrating retry loop.
4. Decrement and the corresponding `stock_movements` row are written in the same transaction as the check, so nothing can observe a state where the check passed but the decrement hasn't happened yet.

**Recipe resolution is merged, not either/or:** an order line's total ingredient demand is its menu item's *base* recipe **plus** the selected variant's recipe, if any — e.g. "Latte" has a base recipe (milk, espresso), and "Large" adds an extra shot on top of it, rather than replacing the base recipe entirely. See `InventoryService::resolveRecipe`.

**Restocking is explicit and state-dependent:**
- Cancelling an order that hasn't reached `completed` restocks everything it reserved (`OrderStateMachine::inventoryIsRestockableFrom`) — the ingredients were committed but never actually used.
- Voiding a single line item works the same way at the line level (`OrderService::voidItem` → `InventoryService::restoreForOrderItem`) and is idempotent — voiding an already-voided item is a no-op, and a retried void request can't double-restock.
- A refund issued **after** an order is `completed` does **not** restock. The coffee was made; the milk is gone. This is a deliberate business decision, not an oversight — see `PurchasingService`/`InventoryController::adjust` for the correct tool (a manual `waste` adjustment) if inventory genuinely needs correcting after the fact.

**Purchasing (the inbound side) gets the same treatment:** `PurchasingService::receive` locks the same `inventory_items` rows (same sort-order discipline), and is idempotent — receiving the same purchase order twice (double-click, retried request) credits stock once, not twice, because it checks `status === 'received'` under the lock before doing anything.

## Payments

- **Idempotency by design, not by luck:** every payment attempt can carry a client-generated `idempotency_key`; a retried request with the same `(tenant, order, key)` returns the original payment rather than creating a second one. `payments` has a real unique constraint on that triple, not just an application-level check.
- **A card/wallet payment always requires a `provider_reference`** — this endpoint never marks a card payment "succeeded" because the client said so. If you don't have a reference yet, you're using the wrong endpoint: online/gateway payments go through `POST /orders/{order}/payment-intent`, which creates a `pending` payment, and only the **webhook** (`StripeWebhookController`, driven by the gateway's own confirmation) is allowed to flip it to `succeeded`/`failed`. This is the line between "the client claims this worked" and "the payment processor confirms this worked," and it's enforced in code, not just convention.
- **Cash overtender computes change without overcrediting the order:** if $20 is tendered against a $4.50 balance, the payment row records `amount = 4.50` (what actually applies to the order) and `tendered_amount = 20.00` (for drawer reconciliation), and the API response reports `change_due = 15.50`. The order can never end up "overpaid" through this path.
- **Refunds are checked against the cumulative sum of prior refunds, not the original amount.** Three separate $10 refund requests against a $12 payment: the third is rejected, because $10 + $10 = $20 > $12 remaining, even though each individual request is "under $12." This is the single most common off-by-one in naive refund implementations, and it's exactly what `PaymentTest::test_refunds_cannot_cumulatively_exceed_the_original_payment` locks in.
- **Cancelling a paid order is blocked unless refund is explicit.** `OrderService::cancelOrder($order, $reason, $autoRefund, ...)` — if there's money on the order and `$autoRefund` is false, it throws rather than silently voiding a paid order. Pass `auto_refund: true` (surfaced as a checkbox/confirmation in the UI, not a default) and it refunds every succeeded payment first, then cancels and restocks.
- **Currency mismatch, paying a cancelled order, and paying an already-fully-paid order** are all rejected with a specific `error_code` in the response body (`currency_mismatch`, `order_cancelled`, `already_paid`) so a client can branch on the reason instead of parsing a message string.
- **Cash drawer reconciliation is real, not inferred.** A successful cash payment writes a `cash_drawer_transactions` row against whatever `cash_drawer_session` is currently open for that order's device — so end-of-shift reconciliation compares against an actual ledger, not a `SUM(payments.amount)` query that has no idea which drawer the cash physically went into.

## Multi-tenant request isolation (the fix made while hardening payments)

A gap surfaced while auditing this: `ResolveTenant` (which scopes every query) runs before `auth:sanctum` (which identifies the user) in the middleware stack — it has to, since query scoping needs to be active before Sanctum's own token lookup touches the database. That means tenant resolution **cannot** fall back to "the authenticated user's tenant" the way an initial version of this did, and for non-subdomain clients (admin-web, mobile-app, desktop-pos — none of which are hosted on a tenant's subdomain) there was no other signal to resolve against.

The fix: those clients send an explicit `X-Tenant-Id` header. Because that header is self-reported, a second middleware (`EnsureUserBelongsToTenant`) runs immediately after `auth:sanctum` and rejects the request if the authenticated user's own `tenant_id` doesn't match the tenant the header claimed — closing the gap where a valid token for Tenant A plus a forged header could otherwise read Tenant B's data. See the docblocks on `ResolveTenant` and `EnsureUserBelongsToTenant` for the full reasoning.

## What's intentionally still simplified

Being direct about the edges of this pass:

- **Tax calculation resolves to 0%** (`OrderService::resolveTaxRate` is a named stub) — the `tax_classes` table and per-item `tax_class_id` exist in the schema, but wiring branch/item-specific rates through order creation and `recalculateTotals` is left as the next piece of work; it's flagged in code rather than silently wrong.
- **True concurrent-load testing** (two real parallel DB connections racing for the same lock) isn't something a single-process PHPUnit run can prove — the test suite proves the *logic* is correct sequentially (the second of two requests for the last unit correctly fails); proving it holds under actual concurrent load needs an integration/load test against a running Postgres instance, noted inline in `OrderLifecycleTest`.
- **Weighted-average vs. last-cost costing** on inventory: `PurchasingService::receive` uses last-cost (the most recently received unit cost overwrites `cost_per_unit`) — stated explicitly in code rather than picked silently, since it's a real accounting decision a finance-minded owner might want changed.
