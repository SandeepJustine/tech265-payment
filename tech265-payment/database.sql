-- ============================================================
-- Tech265 PayChangu Payment Gateway - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS tech265_payments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tech265_payments;

-- -------------------------------------------------------
-- Transactions Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tx_ref          VARCHAR(100)   NOT NULL UNIQUE,
    transaction_id  VARCHAR(100)   DEFAULT NULL,
    first_name      VARCHAR(100)   NOT NULL,
    last_name       VARCHAR(100)   NOT NULL,
    email           VARCHAR(255)   NOT NULL,
    amount          DECIMAL(15,2)  NOT NULL,
    currency        VARCHAR(10)    NOT NULL DEFAULT 'MWK',
    status          ENUM('pending','success','failed','cancelled','verified') NOT NULL DEFAULT 'pending',
    payment_channel VARCHAR(50)    DEFAULT NULL,
    card_number     VARCHAR(30)    DEFAULT NULL,
    card_brand      VARCHAR(30)    DEFAULT NULL,
    mobile_number   VARCHAR(30)    DEFAULT NULL,
    charges         DECIMAL(10,2)  DEFAULT 0.00,
    payment_title   VARCHAR(255)   DEFAULT NULL,
    payment_desc    TEXT           DEFAULT NULL,
    meta_data       JSON           DEFAULT NULL,
    ip_address      VARCHAR(45)    DEFAULT NULL,
    user_agent      TEXT           DEFAULT NULL,
    checkout_url    TEXT           DEFAULT NULL,
    verified_at     TIMESTAMP      NULL DEFAULT NULL,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tx_ref  (tx_ref),
    INDEX idx_status  (status),
    INDEX idx_email   (email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- API Activity Logs Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tx_ref       VARCHAR(100)  DEFAULT NULL,
    action       VARCHAR(100)  NOT NULL,
    endpoint     VARCHAR(255)  NOT NULL,
    method       VARCHAR(10)   NOT NULL DEFAULT 'GET',
    request_data JSON          DEFAULT NULL,
    response_data JSON         DEFAULT NULL,
    http_status  SMALLINT      DEFAULT NULL,
    duration_ms  INT           DEFAULT NULL,
    ip_address   VARCHAR(45)   DEFAULT NULL,
    user_agent   TEXT          DEFAULT NULL,
    is_error     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tx_ref  (tx_ref),
    INDEX idx_action  (action),
    INDEX idx_error   (is_error),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Error Logs Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS error_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tx_ref       VARCHAR(100)  DEFAULT NULL,
    error_type   VARCHAR(100)  NOT NULL,
    error_code   VARCHAR(50)   DEFAULT NULL,
    message      TEXT          NOT NULL,
    stack_trace  TEXT          DEFAULT NULL,
    context      JSON          DEFAULT NULL,
    file         VARCHAR(255)  DEFAULT NULL,
    line_number  INT           DEFAULT NULL,
    ip_address   VARCHAR(45)   DEFAULT NULL,
    resolved     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tx_ref     (tx_ref),
    INDEX idx_error_type (error_type),
    INDEX idx_resolved   (resolved),
    INDEX idx_created    (created_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Webhook Logs Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tx_ref       VARCHAR(100)  DEFAULT NULL,
    event_type   VARCHAR(100)  DEFAULT NULL,
    payload      JSON          DEFAULT NULL,
    signature    VARCHAR(255)  DEFAULT NULL,
    processed    TINYINT(1)    NOT NULL DEFAULT 0,
    ip_address   VARCHAR(45)   DEFAULT NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tx_ref    (tx_ref),
    INDEX idx_processed (processed),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Admin Users Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(100)  NOT NULL UNIQUE,
    email        VARCHAR(255)  NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL,
    full_name    VARCHAR(200)  DEFAULT NULL,
    role         ENUM('super_admin','admin','viewer') NOT NULL DEFAULT 'viewer',
    last_login   TIMESTAMP     NULL DEFAULT NULL,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: admin / Tech265@Admin
INSERT INTO admin_users (username, email, password, full_name, role)
VALUES ('admin', '[email protected]', '$2y$12$XZvhm3QBzp.RB5hUMGxcuOolHp5PJHP8lMZJUhqbxm87hKCqjFKVu', 'System Admin', 'super_admin')
ON DUPLICATE KEY UPDATE id=id;

-- -------------------------------------------------------
-- API Keys Table (optional DB-managed keys)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_keys (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_value    VARCHAR(128)  NOT NULL UNIQUE,
    name         VARCHAR(100)  NOT NULL,
    role         ENUM('full','readonly','webhook') NOT NULL DEFAULT 'readonly',
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    last_used_at TIMESTAMP     NULL DEFAULT NULL,
    expires_at   TIMESTAMP     NULL DEFAULT NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key   (key_value),
    INDEX idx_active(is_active)
) ENGINE=InnoDB;
