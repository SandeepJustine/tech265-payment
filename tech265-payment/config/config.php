<?php
/**
 * Tech265 - PayChangu Payment Gateway Configuration
 */

// ── Environment ─────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV')  ?: 'development'); // 'production' | 'development'
define('APP_NAME',    'Tech265 Payments');
define('APP_VERSION', '1.0.0');
define('APP_URL',     getenv('APP_URL')  ?: 'http://localhost/tech265-payment');
define('APP_DEBUG',   APP_ENV !== 'production');

// ── PayChangu API ────────────────────────────────────────────
define('PAYCHANGU_SECRET_KEY',  getenv('PAYCHANGU_SECRET_KEY')  ?: 'YOUR_SECRET_KEY_HERE');
define('PAYCHANGU_PUBLIC_KEY',  getenv('PAYCHANGU_PUBLIC_KEY')  ?: 'YOUR_PUBLIC_KEY_HERE');
define('PAYCHANGU_API_BASE',    'https://api.paychangu.com');
define('PAYCHANGU_TIMEOUT',     30); // seconds

// Callback / Return URLs — now routed through the API
define('PAYCHANGU_CALLBACK_URL', APP_URL . '/api/v1/payments/callback');
define('PAYCHANGU_RETURN_URL',   APP_URL . '/api/v1/payments/return');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'tech265_payments');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Admin ────────────────────────────────────────────────────
define('ADMIN_SESSION_LIFETIME', 7200);   // 2 hours
define('ADMIN_SESSION_NAME',     'tech265_admin');

// ── Logging ──────────────────────────────────────────────────
define('LOG_DIR',   __DIR__ . '/../logs');
define('LOG_LEVEL', APP_DEBUG ? 'debug' : 'error');  // debug|info|warning|error

// ── API Authentication ────────────────────────────────────────
define('API_KEY_HEADER', 'X-API-Key');
define('API_KEYS', [
    (getenv('TECH265_API_KEY') ?: 'tech265-dev-key-CHANGE-ME')  => ['name' => 'Default App Key', 'role' => 'full'],
    (getenv('TECH265_RO_KEY')  ?: 'tech265-readonly-CHANGE-ME') => ['name' => 'Read-Only Key',   'role' => 'readonly'],
    (getenv('TECH265_WH_KEY')  ?: 'tech265-webhook-CHANGE-ME')  => ['name' => 'Webhook Key',     'role' => 'webhook'],
]);

// ── Rate Limiting ─────────────────────────────────────────────
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_RPM',     60);

// ── Allowed Currencies ────────────────────────────────────────
define('ALLOWED_CURRENCIES', ['MWK', 'USD']);
