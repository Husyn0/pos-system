# Security Model

## Authentication

- **Staff (admin-web, desktop-pos, mobile staff mode)**: email + password via Laravel Sanctum, issuing short-lived bearer tokens. Optional TOTP-based 2FA (`users.two_factor_secret`), required by policy for `owner`/`manager` roles. POS terminals also support a fast **PIN login** (`users.pin_hash`, bcrypt) for cashiers switching shifts on a shared terminal — the PIN only works from a paired, trusted device token, never from the open internet.
- **Customers (public-web, mobile customer mode)**: email/password or OTP-over-SMS/email; guest checkout is allowed but the resulting `customers` row has no `password_hash`, so it can never be used to log in later — a customer has to explicitly "create an account" to convert a guest order history into a login.
- **Devices (POS terminals, KDS, hardware bridge)**: paired once via a short-lived `pairing_code` shown on the admin dashboard, exchanged for a long-lived `device_token`. All requests from a terminal carry this token; it can be revoked instantly from the dashboard if a device is lost or decommissioned.

## Authorization

Role-based access control: `roles` (owner, manager, cashier, waiter, kitchen, accountant — customizable per tenant) each map to a set of `permissions` (`orders.refund`, `menu.edit`, `reports.view`, `staff.manage`, ...). Every API endpoint is guarded by a permission check (`can:` middleware / policy), not just a role name — this lets a tenant create a custom role like "shift lead" with exactly the permissions they want without code changes.

Multi-tenant isolation is enforced twice: an Eloquent global scope adds `WHERE tenant_id = ?` automatically, and Postgres row-level security policies (commented in `schema.sql`) provide a second, database-level backstop so a bug in application code can't leak cross-tenant data.

## Payment data — staying out of PCI scope

The database **never stores card numbers, CVVs, or track data** — only `card_brand` and `card_last4` for display, plus an opaque `provider_reference` (a Stripe PaymentIntent ID, for example). Actual card capture happens either:
- In the payment gateway's hosted fields/SDK (public-web, mobile checkout), or
- On the physical card terminal itself (EMV dip/tap), which talks to the payment network directly and only reports back a success/decline + reference to the hardware bridge.

This keeps the POS's own PCI DSS scope minimal (SAQ A / A-EP style) instead of full scope, which would apply if raw card data ever touched this codebase.

## Transport & infrastructure

- TLS everywhere in the cloud (HTTPS, WSS for Reverb).
- The hardware bridge on-site listens on the local LAN only (no port-forwarding to the public internet); the desktop POS talks to it over `http://localhost:8088` or the LAN IP, secured with a bridge-local shared secret set at install time.
- Secrets (DB credentials, payment gateway keys, JWT signing keys) live in environment variables / a secrets manager, never in the repo. `.env.example` documents required variables without values.
- Rate limiting on auth endpoints (Laravel's built-in throttle middleware) and on the public ordering API to blunt scraping/abuse.

## Data protection

- Passwords/PINs: bcrypt (Laravel default), never reversible.
- PII (customer email/phone/address): standard column-level access via the ORM only; no raw SQL string interpolation anywhere in the codebase (prevents SQL injection by construction).
- `audit_logs` is an append-only table capturing who changed what (old/new values as JSON) for every sensitive mutation — refunds, voids, price changes, role changes, login attempts. It's designed to answer "who did this and when" during a dispute, not just for compliance box-ticking.
- Soft deletes (`deleted_at`) on tenants, branches, users, menu items, customers — accidental deletion is recoverable, and historical orders referencing a "deleted" menu item still resolve correctly.

## Application-layer hardening

- All input validated through Laravel Form Requests before it reaches business logic.
- CSRF protection on any cookie-authenticated web routes; API routes use bearer tokens instead, which are inherently CSRF-immune.
- CORS locked down to known frontend origins per tenant (or the tenant's custom domain, if configured).
- Idempotency keys on order creation prevent duplicate charges/orders from network retries or offline-sync replays.
- Dependency and container image scanning should run in CI (not included here since there's no CI runner in this sandbox — see `docs/DEPLOYMENT.md` for where to add it).

## A note on scope

This document describes the intended security posture and where the schema/code already supports it. It is not a substitute for a real penetration test, a PCI QSA review if you're handling more payment flow than described above, or a security audit before going live with real customer payment data.
