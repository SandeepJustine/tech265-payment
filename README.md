# ⚡ Tech265 – PayChangu Payment Gateway Integration

A full-featured PHP payment integration for **Tech265** using the [PayChangu](https://paychangu.com) payment gateway, with MySQL activity logging and an admin monitoring dashboard.

---

## 📁 Project Structure

```
tech265-payment/
├── config/
│   └── config.php          # All configuration (keys, DB, URLs)
├── src/
│   ├── Database.php        # PDO singleton + query helpers
│   ├── Logger.php          # File + DB logging (API, errors, webhooks)
│   ├── PayChangu.php       # PayChangu API client
│   └── AdminAuth.php       # Admin session authentication
├── public/
│   ├── pay.php             # POST endpoint – initiate payment
│   ├── callback.php        # PayChangu callback / webhook handler
│   ├── return.php          # Return URL (cancelled/failed payments)
│   ├── verify.php          # GET endpoint – verify a transaction
│   └── result.php          # Payment result page + demo checkout form
├── admin/
│   ├── index.php           # Dashboard with KPIs and charts
│   ├── transactions.php    # All transactions with filtering
│   ├── api-logs.php        # API activity logs with request/response detail
│   ├── errors.php          # Error logs – view, filter, resolve
│   ├── webhooks.php        # Webhook log viewer
│   ├── login.php           # Admin login
│   ├── logout.php          # Admin logout
│   └── layout/
│       ├── header.php      # Shared sidebar + topbar
│       └── footer.php      # Shared closing HTML
├── logs/                   # File-based logs (auto-created)
├── database.sql            # MySQL schema – run this first
└── .htaccess               # Security rules
```

---

## 🚀 Setup Instructions

### 1. Create the Database

```bash
mysql -u root -p < database.sql
```

This creates the `tech265_payments` database with all required tables and a default admin user.

### 2. Configure the Application

Edit **`config/config.php`**:

```php
define('APP_URL',              'https://yourdomain.com/tech265-payment');
define('PAYCHANGU_SECRET_KEY', 'your_paychangu_secret_key');
define('PAYCHANGU_PUBLIC_KEY', 'your_paychangu_public_key');
define('DB_HOST',   '127.0.0.1');
define('DB_NAME',   'tech265_payments');
define('DB_USER',   'your_db_user');
define('DB_PASS',   'your_db_password');
```

> **Tip:** Use environment variables in production by replacing the `getenv(...)` fallbacks.

### 3. Set Permissions

```bash
chmod 755 logs/
chmod 644 config/config.php
```

### 4. Deploy

Place the project in your web server root or a subdirectory. The `public/` folder is the web-facing entry point for payment URLs.

---

## 🔑 Admin Dashboard

| URL | Description |
|-----|-------------|
| `/admin/` | Dashboard overview |
| `/admin/transactions.php` | All payment transactions |
| `/admin/api-logs.php` | PayChangu API call history |
| `/admin/errors.php` | Error logs with resolve workflow |
| `/admin/webhooks.php` | Incoming webhook log |

**Default credentials:**
- Username: `admin`
- Password: `Tech265@Admin`

> ⚠️ Change the password immediately after first login by updating the hash in `admin_users`.

Generate a new hash:
```php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);
```

---

## 📡 Payment API Endpoints

### Initiate Payment
```
POST /public/pay.php
Content-Type: application/json

{
  "first_name":  "John",
  "last_name":   "Banda",
  "email":       "john@example.com",
  "amount":      1500,
  "currency":    "MWK",
  "title":       "Course Registration",
  "description": "Tech265 Training Fee"
}
```

**Response:**
```json
{
  "status": "success",
  "tx_ref": "T265-AB12CD-1700000000",
  "checkout_url": "https://checkout.paychangu.com/..."
}
```

### Verify Transaction
```
GET /public/verify.php?tx_ref=T265-AB12CD-1700000000
```

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `transactions` | Every payment with full status history |
| `api_logs` | Every PayChangu API call (request + response + timing) |
| `error_logs` | All errors with stack traces and context |
| `webhook_logs` | Incoming PayChangu webhook payloads |
| `admin_users` | Admin dashboard users |

---

## 🧪 Testing

Get your **test API keys** from [PayChangu Dashboard → API Keys](https://in.paychangu.com/user/api).

Use test card:
- Card Number: `5531 8866 5214 2950`
- Expiry: Any future date
- CVV: `564`
- PIN: `3310`
- OTP: `12345`

---

## 🔐 Security Notes

- All API keys are in `config/config.php` — never commit to version control
- Add `config/` and `logs/` to `.gitignore`
- Admin sessions expire after 2 hours of inactivity
- All HTML output is `htmlspecialchars()` encoded
- Prepared statements used for all DB queries
- `.htaccess` blocks directory listing and direct access to `config/`, `src/`, and `logs/`

---

## 📡 REST API Reference

All API endpoints live under `/api/v1/`. Full interactive documentation is available in the admin panel at `/admin/api-reference.php`.

### Authentication

Pass your API key via the `X-API-Key` header or `?api_key=` query parameter.

Three key roles:
- **full** — all endpoints (initiate, verify, list, logs, stats, callbacks)
- **readonly** — GET-only (list, get, verify, logs, stats)
- **webhook** — callback and return URL endpoints only

### Route Summary

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/v1/health` | Public | Full health check (DB, PayChangu API, PHP ext, disk) |
| GET | `/api/v1/status` | Public | Lightweight ping / version info |
| GET | `/api/v1/info` | Public | API metadata & route listing |
| POST | `/api/v1/payments/initiate` | full | Create payment → returns `checkout_url` |
| GET | `/api/v1/payments` | readonly | List transactions (filterable + paginated) |
| GET | `/api/v1/payments/{tx_ref}` | readonly | Get single transaction |
| GET | `/api/v1/payments/verify/{tx_ref}` | readonly | Verify with PayChangu |
| POST | `/api/v1/payments/callback` | webhook | PayChangu IPN / callback handler |
| GET | `/api/v1/payments/return` | webhook | PayChangu return URL (cancelled) |
| GET | `/api/v1/logs/api` | readonly | API activity logs |
| GET | `/api/v1/logs/errors` | readonly | Error logs |
| GET | `/api/v1/logs/webhooks` | readonly | Webhook logs |
| GET | `/api/v1/stats` | readonly | Statistics summary |

### Example: Initiate a Payment

```bash
curl -X POST https://yoursite.com/tech265-payment/api/v1/payments/initiate \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Banda",
    "email": "john@example.com",
    "amount": 5000,
    "currency": "MWK",
    "title": "Course Registration"
  }'
```

### Example: Check Health

```bash
curl https://yoursite.com/tech265-payment/api/v1/health
```

### Rate Limiting

60 requests/minute per API key. Response headers include:
- `X-RateLimit-Limit: 60`
- `X-RateLimit-Remaining: 59`
- `Retry-After: 60` (when limit hit, HTTP 429)
