-- =====================================================================
-- COFFEE & RESTAURANT POS — DATABASE SCHEMA (PostgreSQL 15+)
-- Multi-tenant (one deployment can serve many restaurant brands),
-- multi-branch, offline-sync friendly, audit-logged.
-- Portable to MySQL 8 with: UUID -> CHAR(36), JSONB -> JSON,
-- TIMESTAMPTZ -> DATETIME, generated columns syntax differences.
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ---------------------------------------------------------------------
-- 1. TENANCY & ORGANIZATION
-- ---------------------------------------------------------------------

CREATE TABLE tenants (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(60)  NOT NULL UNIQUE,          -- subdomain: {slug}.posapp.io
    plan            VARCHAR(30)  NOT NULL DEFAULT 'starter',-- starter | pro | enterprise
    default_currency CHAR(3)     NOT NULL DEFAULT 'USD',
    timezone        VARCHAR(60)  NOT NULL DEFAULT 'UTC',
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    trial_ends_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    deleted_at      TIMESTAMPTZ
);

CREATE TABLE branches (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(20)  NOT NULL,                 -- short code for receipts, e.g. "DT01"
    address_line    VARCHAR(255),
    city            VARCHAR(100),
    country         VARCHAR(100),
    latitude        DECIMAL(9,6),
    longitude       DECIMAL(9,6),
    phone           VARCHAR(30),
    timezone        VARCHAR(60)  NOT NULL DEFAULT 'UTC',
    opening_hours   JSONB,                                 -- {"mon":["07:00","19:00"], ...}
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    deleted_at      TIMESTAMPTZ,
    UNIQUE (tenant_id, code)
);

-- ---------------------------------------------------------------------
-- 2. IDENTITY, ROLES & ACCESS (staff side)
-- ---------------------------------------------------------------------

CREATE TABLE users (                                       -- staff: owners, managers, cashiers, waiters, kitchen
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id           UUID REFERENCES branches(id) ON DELETE SET NULL, -- null = access to all branches
    name                VARCHAR(150) NOT NULL,
    email               VARCHAR(150) NOT NULL,
    phone               VARCHAR(30),
    password_hash       VARCHAR(255) NOT NULL,
    pin_hash            VARCHAR(255),                       -- fast POS login (4-6 digit PIN, hashed)
    avatar_url          VARCHAR(255),
    status              VARCHAR(20) NOT NULL DEFAULT 'active', -- active | suspended | invited
    two_factor_secret   VARCHAR(255),
    two_factor_enabled  BOOLEAN NOT NULL DEFAULT FALSE,
    last_login_at       TIMESTAMPTZ,
    last_login_ip       INET,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at          TIMESTAMPTZ,
    UNIQUE (tenant_id, email)
);

CREATE TABLE roles (
    id          SERIAL PRIMARY KEY,
    tenant_id   UUID REFERENCES tenants(id) ON DELETE CASCADE, -- null = system-wide role template
    name        VARCHAR(60) NOT NULL,        -- owner | manager | cashier | waiter | kitchen | accountant
    is_system   BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE (tenant_id, name)
);

CREATE TABLE permissions (
    id      SERIAL PRIMARY KEY,
    key     VARCHAR(100) NOT NULL UNIQUE,   -- e.g. "orders.refund", "menu.edit", "reports.view"
    label   VARCHAR(150) NOT NULL,
    category VARCHAR(60)
);

CREATE TABLE role_permissions (
    role_id       INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_roles (
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id INT  NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

-- ---------------------------------------------------------------------
-- 3. DEVICES & HARDWARE (POS terminals, KDS screens, printers)
-- ---------------------------------------------------------------------

CREATE TABLE devices (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id           UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id           UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    name                VARCHAR(100) NOT NULL,          -- "Front Register", "Kitchen Screen 1"
    type                VARCHAR(30)  NOT NULL,          -- pos_terminal | kds | kiosk | tablet_waiter
    pairing_code        VARCHAR(10),                    -- shown once to pair; consumed after use
    device_token        VARCHAR(255) UNIQUE,            -- long-lived auth token issued after pairing
    hardware_bridge_url VARCHAR(255),                   -- e.g. http://192.168.1.50:8088 (local Spring Boot bridge)
    app_version         VARCHAR(30),
    last_seen_at        TIMESTAMPTZ,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE printers (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id       UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    device_id       UUID REFERENCES devices(id) ON DELETE SET NULL, -- which bridge manages it
    name            VARCHAR(100) NOT NULL,             -- "Receipt Printer", "Kitchen - Hot Station"
    role            VARCHAR(20) NOT NULL,              -- receipt | kitchen | bar | label
    connection_type VARCHAR(20) NOT NULL,              -- network | usb | bluetooth
    ip_address      VARCHAR(45),
    port            INT,
    usb_identifier  VARCHAR(100),
    paper_width_mm  INT DEFAULT 80,
    has_cash_drawer BOOLEAN NOT NULL DEFAULT FALSE,     -- drawer wired through this printer's RJ11
    kitchen_station VARCHAR(50),                        -- grill | drinks | dessert (routes tickets)
    is_active       BOOLEAN NOT NULL DEFAULT TRUE
);

-- ---------------------------------------------------------------------
-- 4. CUSTOMERS & LOYALTY (public side)
-- ---------------------------------------------------------------------

CREATE TABLE customers (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name            VARCHAR(150),
    email           VARCHAR(150),
    phone           VARCHAR(30),
    password_hash   VARCHAR(255),                       -- null for guest checkouts
    loyalty_points  INT NOT NULL DEFAULT 0,
    loyalty_tier    VARCHAR(30) DEFAULT 'bronze',
    total_spent     DECIMAL(12,2) NOT NULL DEFAULT 0,
    marketing_opt_in BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at      TIMESTAMPTZ,
    UNIQUE (tenant_id, email)
);

CREATE TABLE customer_addresses (
    id           UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    customer_id  UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    label        VARCHAR(50),                           -- Home, Work
    address_line VARCHAR(255) NOT NULL,
    city         VARCHAR(100),
    latitude     DECIMAL(9,6),
    longitude    DECIMAL(9,6),
    is_default   BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE loyalty_transactions (
    id            BIGSERIAL PRIMARY KEY,
    customer_id   UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    order_id      UUID,                                  -- FK added after orders table
    points_change INT NOT NULL,
    reason        VARCHAR(100) NOT NULL,                 -- earn_purchase | redeem | manual_adjust | expire
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 5. MENU CATALOG
-- ---------------------------------------------------------------------

CREATE TABLE menu_categories (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE tax_classes (
    id          SERIAL PRIMARY KEY,
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        VARCHAR(60) NOT NULL,          -- "Standard VAT", "Zero Rated"
    rate        DECIMAL(5,2) NOT NULL,         -- 15.00 = 15%
    is_inclusive BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE menu_items (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    category_id     UUID NOT NULL REFERENCES menu_categories(id) ON DELETE RESTRICT,
    tax_class_id    INT REFERENCES tax_classes(id),
    name            VARCHAR(150) NOT NULL,
    description     TEXT,
    sku             VARCHAR(60),
    base_price      DECIMAL(10,2) NOT NULL,
    cost_price      DECIMAL(10,2) DEFAULT 0,    -- for margin/COGS reporting
    image_url       VARCHAR(255),
    calories        INT,
    prep_time_minutes INT DEFAULT 5,
    track_inventory BOOLEAN NOT NULL DEFAULT FALSE,
    is_available    BOOLEAN NOT NULL DEFAULT TRUE,   -- 86'd / sold out toggle
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at      TIMESTAMPTZ
);

CREATE TABLE item_variants (                    -- e.g. Size: Small/Medium/Large
    id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    menu_item_id  UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
    name          VARCHAR(60) NOT NULL,
    price_delta   DECIMAL(10,2) NOT NULL DEFAULT 0,
    sku           VARCHAR(60),
    sort_order    INT NOT NULL DEFAULT 0
);

CREATE TABLE modifier_groups (                  -- e.g. "Milk type", "Extras"
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    min_select  INT NOT NULL DEFAULT 0,
    max_select  INT NOT NULL DEFAULT 1,
    is_required BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE modifiers (
    id                 UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    modifier_group_id  UUID NOT NULL REFERENCES modifier_groups(id) ON DELETE CASCADE,
    name               VARCHAR(100) NOT NULL,        -- "Oat milk", "Extra shot"
    price_delta        DECIMAL(10,2) NOT NULL DEFAULT 0,
    sort_order         INT NOT NULL DEFAULT 0
);

CREATE TABLE menu_item_modifier_groups (        -- pivot: which items offer which modifier groups
    menu_item_id      UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
    modifier_group_id UUID NOT NULL REFERENCES modifier_groups(id) ON DELETE CASCADE,
    PRIMARY KEY (menu_item_id, modifier_group_id)
);

-- ---------------------------------------------------------------------
-- 6. INVENTORY & SUPPLY CHAIN
-- ---------------------------------------------------------------------

CREATE TABLE suppliers (
    id           UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id    UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name         VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150),
    email        VARCHAR(150),
    phone        VARCHAR(30)
);

CREATE TABLE inventory_items (                  -- raw ingredients / stock-keeping units
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id       UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    supplier_id     UUID REFERENCES suppliers(id) ON DELETE SET NULL,
    name            VARCHAR(150) NOT NULL,       -- "Espresso Beans", "Oat Milk 1L"
    unit            VARCHAR(20) NOT NULL,        -- kg | g | l | ml | pcs
    quantity_on_hand DECIMAL(12,3) NOT NULL DEFAULT 0,
    reorder_level   DECIMAL(12,3) NOT NULL DEFAULT 0,
    cost_per_unit   DECIMAL(10,4) NOT NULL DEFAULT 0,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE recipe_ingredients (                -- links a sellable item to what it consumes
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    menu_item_id     UUID REFERENCES menu_items(id) ON DELETE CASCADE,
    item_variant_id  UUID REFERENCES item_variants(id) ON DELETE CASCADE,
    inventory_item_id UUID NOT NULL REFERENCES inventory_items(id) ON DELETE RESTRICT,
    quantity_required DECIMAL(12,4) NOT NULL,
    CHECK (menu_item_id IS NOT NULL OR item_variant_id IS NOT NULL)
);

CREATE TABLE purchase_orders (
    id           UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id    UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id    UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    supplier_id  UUID REFERENCES suppliers(id),
    status       VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft | ordered | received | cancelled
    ordered_at   TIMESTAMPTZ,
    received_at  TIMESTAMPTZ,
    total_cost   DECIMAL(12,2) DEFAULT 0,
    created_by   UUID REFERENCES users(id)
);

CREATE TABLE purchase_order_items (
    id                 UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    purchase_order_id  UUID NOT NULL REFERENCES purchase_orders(id) ON DELETE CASCADE,
    inventory_item_id  UUID NOT NULL REFERENCES inventory_items(id),
    quantity           DECIMAL(12,3) NOT NULL,
    unit_cost          DECIMAL(10,4) NOT NULL
);

CREATE TABLE stock_movements (                   -- immutable audit trail of every stock change
    id                BIGSERIAL PRIMARY KEY,
    inventory_item_id UUID NOT NULL REFERENCES inventory_items(id) ON DELETE CASCADE,
    type              VARCHAR(20) NOT NULL,       -- purchase | sale | waste | adjustment | transfer
    quantity          DECIMAL(12,3) NOT NULL,     -- negative for deductions
    reference_type    VARCHAR(30),                -- 'order', 'purchase_order', etc.
    reference_id      UUID,
    note              VARCHAR(255),
    created_by        UUID REFERENCES users(id),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 7. DINE-IN: TABLES & RESERVATIONS
-- ---------------------------------------------------------------------

CREATE TABLE dining_tables (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id   UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    label       VARCHAR(20) NOT NULL,            -- "T1", "Patio 3"
    capacity    INT NOT NULL DEFAULT 2,
    status      VARCHAR(20) NOT NULL DEFAULT 'available', -- available | occupied | reserved | cleaning
    qr_token    VARCHAR(64) UNIQUE,               -- for "scan to order" on the public web app
    UNIQUE (branch_id, label)
);

CREATE TABLE reservations (
    id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id     UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    table_id      UUID REFERENCES dining_tables(id) ON DELETE SET NULL,
    customer_id   UUID REFERENCES customers(id) ON DELETE SET NULL,
    guest_name    VARCHAR(150),
    guest_phone   VARCHAR(30),
    party_size    INT NOT NULL DEFAULT 2,
    reserved_at   TIMESTAMPTZ NOT NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'confirmed', -- confirmed | seated | cancelled | no_show
    notes         VARCHAR(255),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 8. ORDERS — the core transactional entity
-- ---------------------------------------------------------------------

CREATE TABLE discounts (
    id             UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id      UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    code           VARCHAR(40),                    -- null = manager-applied manual discount
    name           VARCHAR(100) NOT NULL,
    type           VARCHAR(20) NOT NULL,            -- percentage | fixed
    value          DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    starts_at      TIMESTAMPTZ,
    ends_at        TIMESTAMPTZ,
    usage_limit    INT,
    times_used     INT NOT NULL DEFAULT 0,
    is_active      BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (tenant_id, code)
);

CREATE TABLE orders (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id       UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    order_number    VARCHAR(20) NOT NULL,           -- human-friendly, per-branch daily sequence e.g. "A-0231"
    customer_id     UUID REFERENCES customers(id) ON DELETE SET NULL,
    table_id        UUID REFERENCES dining_tables(id) ON DELETE SET NULL,
    device_id       UUID REFERENCES devices(id) ON DELETE SET NULL,
    created_by      UUID REFERENCES users(id) ON DELETE SET NULL,   -- staff member (null if self-service)
    order_type      VARCHAR(20) NOT NULL,           -- dine_in | takeaway | delivery | online_pickup
    source          VARCHAR(20) NOT NULL DEFAULT 'pos', -- pos | web | mobile | kiosk
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
                    -- pending -> confirmed -> preparing -> ready -> served/completed ; or cancelled
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_total  DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total       DECIMAL(12,2) NOT NULL DEFAULT 0,
    tip_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    idempotency_key VARCHAR(80),                    -- prevents duplicate order creation from offline sync/retries
    placed_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    confirmed_at    TIMESTAMPTZ,
    ready_at        TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    cancelled_reason VARCHAR(255),
    notes           VARCHAR(255),
    synced_at       TIMESTAMPTZ,                    -- when an offline-created order reached the cloud
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, idempotency_key),
    UNIQUE (branch_id, order_number)
);

CREATE INDEX idx_orders_branch_status ON orders(branch_id, status);
CREATE INDEX idx_orders_placed_at ON orders(placed_at);
CREATE INDEX idx_orders_customer ON orders(customer_id);

CREATE TABLE order_items (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    order_id        UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    menu_item_id    UUID NOT NULL REFERENCES menu_items(id) ON DELETE RESTRICT,
    item_variant_id UUID REFERENCES item_variants(id) ON DELETE RESTRICT,
    quantity        INT NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL,          -- price at time of sale (never recompute historically)
    line_total      DECIMAL(10,2) NOT NULL,
    kitchen_station VARCHAR(50),
    status          VARCHAR(20) NOT NULL DEFAULT 'queued', -- queued | preparing | ready | served | voided
    notes           VARCHAR(255)                     -- "no sugar", "extra hot"
);

CREATE TABLE order_item_modifiers (
    id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    order_item_id UUID NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
    modifier_id   UUID NOT NULL REFERENCES modifiers(id),
    price_delta   DECIMAL(10,2) NOT NULL DEFAULT 0
);

CREATE TABLE order_discounts (
    order_id      UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    discount_id   UUID NOT NULL REFERENCES discounts(id),
    amount_applied DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (order_id, discount_id)
);

-- ---------------------------------------------------------------------
-- 9. PAYMENTS (PCI-safe: only gateway tokens/references stored, never PAN)
-- ---------------------------------------------------------------------

CREATE TABLE payments (
    id                     UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id              UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    order_id               UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    method                 VARCHAR(20) NOT NULL,      -- cash | card | wallet | online
    provider               VARCHAR(30),               -- stripe | manual | pax_terminal
    provider_reference     VARCHAR(150),               -- gateway's payment_intent / charge id (opaque token)
    card_brand             VARCHAR(20),                -- "visa" (display only, no PAN/CVV ever stored)
    card_last4             CHAR(4),
    amount                 DECIMAL(12,2) NOT NULL,
    status                 VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|succeeded|failed|refunded|partially_refunded
    processed_by           UUID REFERENCES users(id),
    paid_at                TIMESTAMPTZ,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE refunds (
    id           UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    payment_id   UUID NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
    amount       DECIMAL(12,2) NOT NULL,
    reason       VARCHAR(255),
    processed_by UUID REFERENCES users(id),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 10. SHIFTS & CASH MANAGEMENT
-- ---------------------------------------------------------------------

CREATE TABLE shifts (
    id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    branch_id     UUID NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    user_id       UUID NOT NULL REFERENCES users(id),
    clock_in      TIMESTAMPTZ NOT NULL DEFAULT now(),
    clock_out     TIMESTAMPTZ,
    status        VARCHAR(20) NOT NULL DEFAULT 'open'  -- open | closed
);

CREATE TABLE cash_drawer_sessions (
    id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    shift_id         UUID NOT NULL REFERENCES shifts(id) ON DELETE CASCADE,
    device_id        UUID REFERENCES devices(id),
    opening_amount   DECIMAL(10,2) NOT NULL DEFAULT 0,
    closing_amount   DECIMAL(10,2),
    expected_amount  DECIMAL(10,2),
    difference       DECIMAL(10,2),
    opened_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    closed_at        TIMESTAMPTZ
);

CREATE TABLE cash_drawer_transactions (
    id                     BIGSERIAL PRIMARY KEY,
    cash_drawer_session_id UUID NOT NULL REFERENCES cash_drawer_sessions(id) ON DELETE CASCADE,
    type                   VARCHAR(20) NOT NULL,   -- sale | payout | payin | refund | opening
    amount                 DECIMAL(10,2) NOT NULL,
    reference_order_id     UUID REFERENCES orders(id),
    note                   VARCHAR(255),
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 11. ANALYTICS (event stream + pre-aggregated rollups for fast dashboards)
-- ---------------------------------------------------------------------

CREATE TABLE analytics_events (                  -- flexible raw event log
    id          BIGSERIAL PRIMARY KEY,
    tenant_id   UUID NOT NULL,
    branch_id   UUID,
    event_type  VARCHAR(60) NOT NULL,             -- order_placed | item_86ed | staff_clockin | app_opened...
    payload     JSONB,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_analytics_tenant_type_time ON analytics_events(tenant_id, event_type, occurred_at);

CREATE TABLE daily_sales_summary (                -- rebuilt nightly by a scheduled job for instant dashboards
    id              BIGSERIAL PRIMARY KEY,
    tenant_id       UUID NOT NULL,
    branch_id       UUID NOT NULL,
    summary_date    DATE NOT NULL,
    gross_sales     DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_sales       DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total  DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total       DECIMAL(14,2) NOT NULL DEFAULT 0,
    orders_count    INT NOT NULL DEFAULT 0,
    avg_order_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    top_item_id     UUID,
    UNIQUE (branch_id, summary_date)
);

-- ---------------------------------------------------------------------
-- 12. NOTIFICATIONS & AUDIT
-- ---------------------------------------------------------------------

CREATE TABLE notifications (
    id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     UUID REFERENCES users(id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(id) ON DELETE CASCADE,
    channel     VARCHAR(20) NOT NULL,     -- push | email | sms | in_app
    title       VARCHAR(150),
    body        VARCHAR(500),
    is_read     BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE audit_logs (                 -- immutable, append-only; who changed what
    id             BIGSERIAL PRIMARY KEY,
    tenant_id      UUID NOT NULL,
    user_id        UUID,
    action         VARCHAR(60) NOT NULL,       -- created | updated | deleted | refunded | voided | login
    auditable_type VARCHAR(60) NOT NULL,       -- 'Order', 'MenuItem', 'User'...
    auditable_id   UUID,
    old_values     JSONB,
    new_values     JSONB,
    ip_address     INET,
    user_agent     VARCHAR(255),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_audit_tenant_time ON audit_logs(tenant_id, created_at);

-- ---------------------------------------------------------------------
-- Deferred foreign key: loyalty_transactions.order_id -> orders.id
-- ---------------------------------------------------------------------
ALTER TABLE loyalty_transactions
    ADD CONSTRAINT fk_loyalty_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- Row-level security hook (Postgres): every tenant-scoped table can be
-- restricted to `current_setting('app.tenant_id')` for defense-in-depth
-- in addition to application-layer scoping. Example:
-- ---------------------------------------------------------------------
-- ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY tenant_isolation ON orders
--   USING (tenant_id = current_setting('app.tenant_id')::uuid);
