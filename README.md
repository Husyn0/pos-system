# ☕ Coffee & Restaurant POS — Full-Stack, Cross-Platform

A complete point-of-sale platform for coffee shops and restaurants: cloud backend, admin dashboard, public ordering site, staff mobile app, offline-capable desktop POS terminal, and a local hardware-control service for receipt printers, cash drawers, barcode scanners, and card terminals.

## Repository layout

| Folder | What it is | Stack |
|---|---|---|
| `backend-laravel/` | Cloud API — auth, orders, menu, inventory, payments, analytics, real-time broadcasting | Laravel 11 (PHP 8.3), PostgreSQL, Redis, Sanctum, Reverb (WebSockets) |
| `admin-web/` | Owner/manager dashboard + in-store POS screen (browser-based register) | React 18, TypeScript, Vite, Tailwind, React Query |
| `public-web/` | Customer-facing ordering site: menu, cart, checkout, order tracking, reservations, "scan QR at table to order" | Next.js 14 (App Router) |
| `mobile-app/` | Cross-platform app for customers (order & track) and staff (waiter order entry, table management) | React Native + Expo |
| `desktop-pos/` | Installable cashier terminal app — works offline, talks to local hardware | Electron + React + SQLite |
| `hardware-bridge/` | On-premise service that actually talks to receipt printers, cash drawers, barcode scanners, card terminals | Spring Boot (Java 21) |
| `database/` | Full schema (`schema.sql`) + ER diagram | PostgreSQL DDL |
| `docs/` | Security, analytics, and deployment write-ups | Markdown |

## Why this shape

A POS system has two very different environments to serve, and trying to force one stack to do both is where most homegrown POS projects go wrong:

1. **The cloud side** — dashboards, reporting, the public menu, multi-branch management — is a normal multi-tenant SaaS problem. Laravel + Postgres + a React/Next.js frontend is a well-trodden, boring-in-a-good-way choice here.
2. **The counter side** — the actual register — has to work when the internet doesn't, and it has to talk to USB/serial/network hardware that a browser sandbox will never be allowed to touch. That's why there's a **local hardware bridge** (Spring Boot) running on a small machine or the POS terminal itself, and why the **desktop app is Electron with a local SQLite cache**, not just a browser tab. The web-based POS screen in `admin-web` still works for stores that don't need hardware (e.g. a tablet just taking orders that a runner brings to a shared printer).

```
┌─────────────────────────────┐        ┌──────────────────────────────┐
│         CLOUD (VPC)         │        │      EACH RESTAURANT SITE     │
│                              │        │                                │
│  Laravel API  ──  Postgres  │◄──────►│  Desktop POS (Electron)        │
│      │            Redis     │  HTTPS │      │                          │
│  Reverb (WS)                │  sync  │      │ localhost                │
└──────┬───────────────────────┘        │      ▼                          │
       │ HTTPS / WSS                    │  Hardware Bridge (Spring Boot) │
       │                                │      │        │        │       │
┌──────┴─────┐ ┌───────────┐ ┌──────────┐   USB/Net   USB/Net  Serial   │
│ admin-web  │ │public-web │ │mobile-app│    │            │        │     │
│ (dashboard │ │(customer  │ │(staff +  │  Receipt   Cash     Barcode   │
│  + web POS)│ │ ordering) │ │ customer)│  Printer   Drawer   Scanner   │
└────────────┘ └───────────┘ └──────────┘                               │
                                          └──────────────────────────────┘
```

## Getting started

Each app has its own README-equivalent inline (see the top of its `package.json`/`composer.json`). Broad strokes:

```bash
# 1. Cloud backend
cd backend-laravel
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve

# 2. Admin dashboard
cd admin-web && npm install && npm run dev

# 3. Public ordering site
cd public-web && npm install && npm run dev

# 4. Mobile app (Expo)
cd mobile-app && npm install && npx expo start

# 5. Desktop POS
cd desktop-pos && npm install && npm run dev

# 6. Hardware bridge (runs on-site, one per branch)
cd hardware-bridge && mvn spring-boot:run
```

Or bring up the cloud pieces together:

```bash
docker compose up -d
```

> This sandbox has no network access, so none of the installs/builds above were actually run here — the code is written and internally consistent, but do a normal `install` pass and smoke-test before relying on it. Treat this as a strong, opinionated starting point covering the core flows (auth, menu, order lifecycle, payments, inventory deduction, real-time updates, hardware control) rather than a finished, fully-tested product — extend the remaining CRUD/edge cases the same way.

## Where to look first

- **Database design**: [`database/schema.sql`](./database/schema.sql) and [`database/ERD.md`](./database/ERD.md)
- **System architecture, scaling, offline strategy**: [`ARCHITECTURE.md`](./ARCHITECTURE.md)
- **Security model**: [`docs/SECURITY.md`](./docs/SECURITY.md)
- **Analytics design**: [`docs/ANALYTICS.md`](./docs/ANALYTICS.md)
- **Deployment**: [`docs/DEPLOYMENT.md`](./docs/DEPLOYMENT.md)
