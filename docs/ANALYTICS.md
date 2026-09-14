# Analytics

## Two-tier design

**Raw event stream — `analytics_events`.** Every meaningful thing that happens (order placed, item 86'd, staff clocked in, discount applied, app opened) writes one JSONB row here. Cheap to write from any surface (API, mobile, kiosk, hardware bridge), and flexible enough to answer questions you haven't thought to build a report for yet — you can always reprocess history because nothing was thrown away.

**Aggregated rollups — `daily_sales_summary`.** A scheduled job (`php artisan analytics:rollup`, run nightly per branch, plus incrementally through the current day) walks completed orders and writes one row per branch per day: gross/net sales, discount and tax totals, order count, average order value, top-selling item. The dashboard queries **this** table, not raw orders — so "show me this quarter's revenue trend" is a sum over ~90 small rows instead of scanning tens of thousands of orders on every page load.

## What ships in the admin dashboard

- **Today at a glance**: gross sales, order count, average order value, live vs. yesterday comparison.
- **Sales trend**: line chart over a selectable range (7/30/90 days), broken down by branch.
- **Top items**: bar chart of best-sellers by revenue and by quantity — separately, since a $2 pastry sold 500 times and a $40 platter sold 20 times both matter differently.
- **Peak hours**: order volume by hour-of-day/day-of-week, to inform staffing.
- **Inventory turnover & waste**: which ingredients are moving fastest, which stock movements are tagged `waste`, and a low-stock alert list from `inventory_items.reorder_level`.
- **Staff performance**: orders handled and average ticket time per staff member per shift (from `shifts` + `orders.created_by`).
- **Customer retention**: repeat-purchase rate and loyalty tier distribution from `customers`/`loyalty_transactions`.

## Implementation notes

- Rollups are idempotent (upsert on `(branch_id, summary_date)`), so re-running the job for a corrected day is safe.
- Real-time tiles (today's running total) are computed live from `orders` filtered to `placed_at::date = today` — a small enough table scan at the daily grain to skip pre-aggregation, refreshed via the same WebSocket channel that powers the kitchen display, so the number updates the moment an order completes without polling.
- CSV/PDF export of any report reuses the same query as the on-screen chart, so exports and the UI can never drift apart.
- All analytics queries are tenant- and branch-scoped by the same middleware/global-scope mechanism as everything else — there's no separate "analytics service" with its own auth model to keep in sync.
