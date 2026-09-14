# Architecture

## 1. Tenancy model

Single deployment, shared database, `tenant_id` on every row (see `database/schema.sql`). A `tenants` row represents one restaurant *brand*; `branches` are its physical locations. Resolution order for "which tenant is this request for":

1. Subdomain (`brewhouse.posapp.io` → tenant `brewhouse`) for the public site and admin dashboard.
2. `X-Device-Token` header for POS terminals / hardware bridge, resolved to a `devices` row → `branch_id` → `tenant_id`.
3. Bearer token claims for authenticated staff/customer API calls.

This is implemented as Laravel middleware (`app/Http/Middleware/ResolveTenant.php`) that sets the tenant on a request-scoped singleton, which an Eloquent global scope (`BelongsToTenant` trait) then applies to every tenant-scoped model automatically — so a controller can never accidentally leak another tenant's rows just by forgetting a `where()`.

## 2. Order lifecycle & real-time updates

```
pending → confirmed → preparing → ready → served/completed
                                      ↘ cancelled (any stage before completed)
```

- Order created (POS, kiosk, web, or waiter app) → `OrderCreated` event.
- Kitchen marks items ready → `OrderItemStatusChanged` → `OrderStatusChanged` when all items ready.
- Every event broadcasts on a private channel `tenant.{tenantId}.branch.{branchId}.orders` via Laravel Reverb (self-hosted WebSocket server, Pusher-protocol compatible).
- Subscribers: Kitchen Display (admin-web `/kds` route), customer order-tracking page (public-web), waiter's phone (mobile-app) — all get pushed the same event, no polling.
- Offline devices (desktop POS) queue orders locally and POST with an `idempotency_key` when connectivity returns; the server treats a repeated key as a no-op success rather than a duplicate order.

## 3. Inventory deduction

On `OrderStatusChanged → completed`, a queued job (`DeductInventoryForOrder`) walks each `order_item` → `recipe_ingredients` → decrements `inventory_items.quantity_on_hand` and writes a `stock_movements` row per ingredient. Running this as a queued job (not inline in the request) keeps checkout fast and makes the deduction retry-safe if it fails partway.

## 4. Hardware integration

Browsers and even Electron's renderer process can't safely do raw USB/serial I/O. So:

- **Hardware Bridge** (Spring Boot, `hardware-bridge/`) runs on a small PC/NUC at the branch (or on the POS terminal itself) and exposes a local HTTP + WebSocket API on `localhost:8088` (also reachable on the LAN for other terminals).
- It owns the actual printer sockets/serial ports/USB HID listeners. The cloud never talks to it directly — the **desktop POS app** or the **admin-web POS screen** (when running on that machine) calls it.
- Barcode scanners in keyboard-wedge mode need no bridge at all — they just "type" into whatever input is focused. The bridge additionally supports serial-mode scanners for kiosks that shouldn't have a focus-stealing keyboard listener.
- Cash drawers are almost always wired through the receipt printer (RJ11 "kick" cable) and opened by sending an ESC/POS pulse command — see `PrinterController` / `EscPosService` in the bridge.
- Card payment terminals are provider-specific (Stripe Terminal, PAX, Ingenico). The bridge exposes a generic `PaymentTerminalController` interface; swap the implementation for your provider's SDK.
- If a branch has no on-site hardware at all (e.g. a takeout counter that just shows QR codes), none of this is required — `admin-web`'s POS screen and `public-web` work with zero hardware dependency.

## 5. Offline-first desktop POS

`desktop-pos` keeps a local SQLite mirror of menu/inventory/open orders. Writes go to SQLite first (instant UI), then an outbox pattern pushes them to the cloud API in the background. If offline for an extended period, the register keeps working off the last-synced menu and prices; it reconciles and flags conflicts (e.g., an item was 86'd on another terminal) once reconnected.

## 6. Scaling notes

- Stateless Laravel app servers behind a load balancer; sessions/cache/queues in Redis so any instance can serve any request.
- Read-heavy dashboard queries hit `daily_sales_summary` (pre-aggregated), not raw `orders`.
- Horizontally partition by `tenant_id` if a single tenant outgrows shared infra — the schema already isolates tenant data cleanly enough to migrate one tenant to dedicated infrastructure without a redesign.
- Queue workers (Redis + Laravel Horizon) handle inventory deduction, receipt emails, loyalty point calculation, and nightly analytics rollups — nothing slow runs in the request/response cycle.

## 7. What's implemented vs. what's scaffolded

To keep this a genuinely useful starting point rather than a shallow skeleton, the **order → payment → kitchen → analytics** flow, auth, and the DB schema are built out with real logic end-to-end. Peripheral CRUD (e.g. every settings screen, every report variant, full reservation management UI) follows the same patterns shown and is intentionally left as extension work — trying to fully flesh out 6 platforms' worth of every screen would trade real depth on the core flow for shallow stubs everywhere.
