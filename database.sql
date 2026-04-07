-- ============================================================
--  Tech265 – PayChangu Payment Gateway
--  Database Schema  v2.0
--
--  Tables:
--    1. transactions     – hosted checkout payments
--    2. direct_charges   – direct MoMo + bank transfer charges
--    3. payouts          – MoMo + bank transfer disbursements
--    4. api_logs         – every outbound PayChangu API call
--    5. error_logs       – all caught errors with stack traces
--    6. webhook_logs     – incoming PayChangu webhook payloads
--    7. admin_users      – admin panel user accounts
--    8. api_keys         – DB-managed API key registry
--
--  Usage:
--    mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS tech265_payments
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tech265_payments;

-- ============================================================
-- 1. TRANSACTIONS  (hosted checkout payments via PayChangu)
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tx_ref           VARCHAR(100)    NOT NULL UNIQUE  COMMENT 'Your unique transaction reference',
    transaction_id   VARCHAR(100)    DEFAULT NULL     COMMENT 'PayChangu internal transaction ID',
    first_name       VARCHAR(100)    NOT NULL,
    last_name        VARCHAR(100)    NOT NULL,
    email            VARCHAR(255)    NOT NULL,
    amount           DECIMAL(15,2)   NOT NULL,
    currency         VARCHAR(10)     NOT NULL DEFAULT 'MWK',
    status           ENUM(
                         'pending',
                         'success',
                         'failed',
                         'cancelled',
                         'verified'
                     )               NOT NULL DEFAULT 'pending',
    payment_channel  VARCHAR(50)     DEFAULT NULL  COMMENT 'Card | Mobile Money | Mobile Bank Transfer',
    card_number      VARCHAR(30)     DEFAULT NULL,
    card_brand       VARCHAR(30)     DEFAULT NULL,
    mobile_number    VARCHAR(30)     DEFAULT NULL,
    charges          DECIMAL(10,2)   DEFAULT 0.00  COMMENT 'PayChangu processing fee',
    payment_title    VARCHAR(255)    DEFAULT NULL,
    payment_desc     TEXT            DEFAULT NULL,
    meta_data        JSON            DEFAULT NULL,
    ip_address       VARCHAR(45)     DEFAULT NULL,
    user_agent       TEXT            DEFAULT NULL,
    checkout_url     TEXT            DEFAULT NULL,
    verified_at      TIMESTAMP       NULL DEFAULT NULL,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tx_ref  (tx_ref),
    INDEX idx_status  (status),
    INDEX idx_email   (email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB COMMENT='Hosted checkout payment sessions';


-- ============================================================
-- 2. DIRECT_CHARGES  (MoMo charges + Bank Transfer charges)
--
--    charge_type = 'momo'          → mobile money direct charge
--    charge_type = 'bank_transfer' → virtual bank account charge
-- ============================================================
CREATE TABLE IF NOT EXISTS direct_charges (
    id                   INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    charge_id            VARCHAR(100)    NOT NULL UNIQUE  COMMENT 'Your unique charge reference',
    charge_type          ENUM(
                             'momo',
                             'bank_transfer'
                         )               NOT NULL          COMMENT 'Payment method used',

    -- ── Common fields ───────────────────────────────────────
    status               VARCHAR(50)     NOT NULL DEFAULT 'pending',
    amount               DECIMAL(15,2)   DEFAULT NULL,
    currency             VARCHAR(10)     NOT NULL DEFAULT 'MWK',
    ref_id               VARCHAR(100)    DEFAULT NULL  COMMENT 'PayChangu ref_id',
    trans_id             VARCHAR(100)    DEFAULT NULL  COMMENT 'PayChangu trans_id',
    trace_id             VARCHAR(100)    DEFAULT NULL  COMMENT 'PayChangu trace_id',
    charges_amount       DECIMAL(10,2)   DEFAULT 0.00  COMMENT 'PayChangu processing fee',
    mode                 VARCHAR(20)     DEFAULT NULL  COMMENT 'live | sandbox',

    -- ── Customer fields (MoMo) ──────────────────────────────
    mobile               VARCHAR(50)     DEFAULT NULL  COMMENT 'Customer phone number',
    operator_ref_id      VARCHAR(100)    DEFAULT NULL  COMMENT 'MoMo operator UUID',
    operator_name        VARCHAR(100)    DEFAULT NULL  COMMENT 'e.g. Airtel Money, TNM Mpamba',
    first_name           VARCHAR(100)    DEFAULT NULL,
    last_name            VARCHAR(100)    DEFAULT NULL,
    email                VARCHAR(255)    DEFAULT NULL,

    -- ── Virtual account fields (Bank Transfer) ───────────────
    bank_name            VARCHAR(150)    DEFAULT NULL  COMMENT 'Generated virtual account bank',
    account_number       VARCHAR(50)     DEFAULT NULL  COMMENT 'Generated virtual account number',
    account_name         VARCHAR(150)    DEFAULT NULL,
    account_expires_at   TIMESTAMP       NULL DEFAULT NULL  COMMENT 'Virtual account expiry (1 hour for one-time)',
    is_permanent_account TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = static reusable account',

    -- ── Metadata ────────────────────────────────────────────
    raw_response         JSON            DEFAULT NULL  COMMENT 'Full PayChangu API response',
    ip_address           VARCHAR(45)     DEFAULT NULL,
    verified_at          TIMESTAMP       NULL DEFAULT NULL,
    created_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_charge_id   (charge_id),
    INDEX idx_charge_type (charge_type),
    INDEX idx_status      (status),
    INDEX idx_mobile      (mobile),
    INDEX idx_created     (created_at)
) ENGINE=InnoDB COMMENT='Direct MoMo and Bank Transfer charge records';


-- ============================================================
-- 3. PAYOUTS  (MoMo disbursements + Bank Transfer disbursements)
--
--    payout_type = 'momo'          → send to mobile money wallet
--    payout_type = 'bank_transfer' → send to bank account
-- ============================================================
CREATE TABLE IF NOT EXISTS payouts (
    id                   INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    charge_id            VARCHAR(100)    NOT NULL UNIQUE  COMMENT 'Your unique payout reference',
    payout_type          ENUM(
                             'momo',
                             'bank_transfer'
                         )               NOT NULL          COMMENT 'Disbursement method',

    -- ── Common fields ───────────────────────────────────────
    status               VARCHAR(50)     NOT NULL DEFAULT 'pending',
    amount               DECIMAL(15,2)   NOT NULL,
    currency             VARCHAR(10)     NOT NULL DEFAULT 'MWK',
    ref_id               VARCHAR(100)    DEFAULT NULL,
    trans_id             VARCHAR(100)    DEFAULT NULL,
    trace_id             VARCHAR(100)    DEFAULT NULL,
    charges_amount       DECIMAL(10,2)   DEFAULT 0.00,
    mode                 VARCHAR(20)     DEFAULT NULL  COMMENT 'live | sandbox',

    -- ── MoMo recipient fields ────────────────────────────────
    mobile               VARCHAR(50)     DEFAULT NULL  COMMENT 'Recipient phone number',
    operator_ref_id      VARCHAR(100)    DEFAULT NULL  COMMENT 'MoMo operator UUID',
    operator_name        VARCHAR(100)    DEFAULT NULL  COMMENT 'e.g. Airtel Money',

    -- ── Bank Transfer recipient fields ───────────────────────
    bank_uuid            VARCHAR(100)    DEFAULT NULL  COMMENT 'PayChangu bank UUID',
    bank_name            VARCHAR(150)    DEFAULT NULL,
    bank_account_name    VARCHAR(150)    DEFAULT NULL  COMMENT 'Recipient full name',
    bank_account_number  VARCHAR(50)     DEFAULT NULL  COMMENT 'Recipient account number',

    -- ── Metadata ────────────────────────────────────────────
    raw_response         JSON            DEFAULT NULL,
    ip_address           VARCHAR(45)     DEFAULT NULL,
    completed_at         TIMESTAMP       NULL DEFAULT NULL,
    created_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_charge_id   (charge_id),
    INDEX idx_payout_type (payout_type),
    INDEX idx_status      (status),
    INDEX idx_mobile      (mobile),
    INDEX idx_created     (created_at)
) ENGINE=InnoDB COMMENT='MoMo and Bank Transfer payout/disbursement records';


-- ============================================================
-- 4. API_LOGS  (every outbound call to PayChangu)
-- ============================================================
CREATE TABLE IF NOT EXISTS api_logs (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tx_ref        VARCHAR(100)    DEFAULT NULL  COMMENT 'tx_ref or charge_id of related transaction',
    action        VARCHAR(100)    NOT NULL      COMMENT 'e.g. INITIATE_PAYMENT, DIRECT_CHARGE_MOMO, PAYOUT_BANK',
    endpoint      VARCHAR(512)    NOT NULL      COMMENT 'Full PayChangu API URL called',
    method        VARCHAR(10)     NOT NULL DEFAULT 'GET',
    request_data  JSON            DEFAULT NULL,
    response_data JSON            DEFAULT NULL,
    http_status   SMALLINT        DEFAULT NULL,
    duration_ms   INT             DEFAULT NULL  COMMENT 'Round-trip time in milliseconds',
    ip_address    VARCHAR(45)     DEFAULT NULL,
    user_agent    TEXT            DEFAULT NULL,
    is_error      TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tx_ref  (tx_ref),
    INDEX idx_action  (action),
    INDEX idx_error   (is_error),
    INDEX idx_created (created_at)
) ENGINE=InnoDB COMMENT='Audit log of every outbound PayChangu API call';


-- ============================================================
-- 5. ERROR_LOGS  (all caught exceptions and API errors)
-- ============================================================
CREATE TABLE IF NOT EXISTS error_logs (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tx_ref        VARCHAR(100)    DEFAULT NULL,
    error_type    VARCHAR(100)    NOT NULL  COMMENT 'e.g. CURL_ERROR, API_HTTP_ERROR, VALIDATION_ERROR',
    error_code    VARCHAR(50)     DEFAULT NULL,
    message       TEXT            NOT NULL,
    stack_trace   TEXT            DEFAULT NULL,
    context       JSON            DEFAULT NULL,
    file          VARCHAR(255)    DEFAULT NULL,
    line_number   INT             DEFAULT NULL,
    ip_address    VARCHAR(45)     DEFAULT NULL,
    resolved      TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tx_ref     (tx_ref),
    INDEX idx_error_type (error_type),
    INDEX idx_resolved   (resolved),
    INDEX idx_created    (created_at)
) ENGINE=InnoDB COMMENT='Application error and exception log';


-- ============================================================
-- 6. WEBHOOK_LOGS  (incoming PayChangu webhook payloads)
-- ============================================================
CREATE TABLE IF NOT EXISTS webhook_logs (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tx_ref        VARCHAR(100)    DEFAULT NULL,
    event_type    VARCHAR(100)    DEFAULT NULL  COMMENT 'e.g. checkout.payment, api.charge.payment, api.payout',
    payload       JSON            DEFAULT NULL,
    signature     VARCHAR(255)    DEFAULT NULL,
    processed     TINYINT(1)      NOT NULL DEFAULT 0,
    ip_address    VARCHAR(45)     DEFAULT NULL,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tx_ref    (tx_ref),
    INDEX idx_event     (event_type),
    INDEX idx_processed (processed),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB COMMENT='Incoming PayChangu webhook payloads';


-- ============================================================
-- 7. ADMIN_USERS  (admin panel user accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100)    NOT NULL UNIQUE,
    email         VARCHAR(255)    NOT NULL UNIQUE,
    password      VARCHAR(255)    NOT NULL          COMMENT 'bcrypt hash',
    full_name     VARCHAR(200)    DEFAULT NULL,
    role          ENUM(
                      'super_admin',
                      'admin',
                      'viewer'
                  )               NOT NULL DEFAULT 'viewer',
    last_login    TIMESTAMP       NULL DEFAULT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_active   (is_active)
) ENGINE=InnoDB COMMENT='Admin dashboard user accounts';

-- Default admin user  (password: Tech265@Admin)
INSERT INTO admin_users (username, email, password, full_name, role)
VALUES (
    'admin',
    'admin@tech265.com',
    '$2y$12$XZvhm3QBzp.RB5hUMGxcuOolHp5PJHP8lMZJUhqbxm87hKCqjFKVu',
    'System Admin',
    'super_admin'
)
ON DUPLICATE KEY UPDATE id = id;


-- ============================================================
-- 8. API_KEYS  (DB-managed API key registry)
--    Note: keys can also be set via .env (see config/config.php)
-- ============================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    key_value     VARCHAR(128)    NOT NULL UNIQUE  COMMENT 'Raw key value passed in X-API-Key header',
    name          VARCHAR(100)    NOT NULL          COMMENT 'Human-readable label',
    role          ENUM(
                      'full',
                      'readonly',
                      'webhook'
                  )               NOT NULL DEFAULT 'readonly',
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    last_used_at  TIMESTAMP       NULL DEFAULT NULL,
    expires_at    TIMESTAMP       NULL DEFAULT NULL  COMMENT 'NULL = never expires',
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_key    (key_value),
    INDEX idx_active (is_active)
) ENGINE=InnoDB COMMENT='API key registry (supplement to .env-based keys)';
