# Deployment

## Cloud components

| Component | Suggested hosting | Notes |
|---|---|---|
| `backend-laravel` | Any container platform (ECS, Cloud Run, Fly.io) or a VPS behind Nginx + PHP-FPM | Stateless — scale horizontally behind a load balancer |
| PostgreSQL | Managed (RDS, Cloud SQL, Supabase) | Enable automated backups + point-in-time recovery |
| Redis | Managed (ElastiCache, Upstash) | Cache, queues, session store, Reverb's scaling backplane |
| Reverb (WebSockets) | Same container as Laravel or a dedicated small instance | Needs a sticky/long-lived connection path through your LB |
| `admin-web` / `public-web` | Static hosting + CDN (Vercel, Netlify, Cloudflare Pages) for the built assets; `public-web` benefits from Next.js's SSR if deployed on a Node-capable platform | Point both at the API's public URL via env var |
| Queue workers | Same image as the API, run with `php artisan queue:work`, supervised (Horizon dashboard recommended) | Handles inventory deduction, emails, rollups |

`docker-compose.yml` at the repo root brings up Postgres, Redis, the Laravel API, and both web frontends for local development in one command.

## On-premise components (per restaurant branch)

- **Hardware Bridge** (`hardware-bridge/`): install as a small Spring Boot JAR + systemd service (Linux) or Windows service on a NUC/mini-PC connected to the same LAN as the printers/scanners, or directly on the POS terminal if it's a full PC. Runs `java -jar hardware-bridge.jar` (packaged via `mvn package`).
- **Desktop POS** (`desktop-pos/`): packaged as a native installer per OS via `electron-builder` (`npm run build` → `.exe`/`.dmg`/`.AppImage`). Auto-update can point at a release feed once you're distributing to real sites.

## Environments

Use separate `tenants` isolation, not separate deployments, for most cases — one production deployment serves all restaurant tenants. Reserve a genuinely separate deployment for: a large enterprise customer requiring data residency in a specific region, or your own staging/QA environment.

## CI/CD (not included in this scaffold)

Wire up, at minimum:
1. Lint + type-check + unit tests on every PR (`php artisan test`, `npm test`/`vitest`, ESLint/TypeScript checks) for each app.
2. Build the Docker image for `backend-laravel` and the installers for `desktop-pos` on tag/release.
3. Run `php artisan migrate --force` as a release step, never manually against production.
4. Dependency vulnerability scanning (Dependabot/Snyk) given how many package ecosystems this repo spans (Composer, npm ×4, Maven).

## Observability

- Application logs → structured JSON → your log aggregator of choice (CloudWatch, Loki, Datadog).
- APM on the Laravel API (response times, queue lag) — queue lag specifically matters here since inventory deduction and receipt handling go through it.
- Uptime checks on the public API and on each branch's hardware bridge (`GET /health`) so a printer/network outage at a specific site surfaces before a manager has to report it.
