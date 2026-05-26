<?php
/**
 * Tech265 – API Front Controller / Router
 * Entry point: /api/index.php  (all /api/v1/* routed here via .htaccess)
 *
 * Routes:
 *   GET    /api/v1/health                  – full health check
 *   GET    /api/v1/status                  – lightweight ping
 *   GET    /api/v1/info                    – API metadata & route listing
 *   POST   /api/v1/payments/initiate       – create payment session
 *   GET    /api/v1/payments/verify/{ref}   – verify a transaction
 *   GET    /api/v1/payments/callback       – PayChangu GET callback
 *   POST   /api/v1/payments/callback       – PayChangu POST callback (IPN)
 *   GET    /api/v1/payments/return         – PayChangu return URL
 *   GET    /api/v1/payments                – list transactions
 *   GET    /api/v1/payments/{ref}          – single transaction
 *   GET    /api/v1/logs/api                – API activity logs
 *   GET    /api/v1/logs/errors             – error logs
 *   GET    /api/v1/logs/webhooks           – webhook logs
 *   GET    /api/v1/stats                   – statistics summary
 */

require_once __DIR__ . '/../src/ApiMiddleware.php';
require_once __DIR__ . '/../src/PayChangu.php';
require_once __DIR__ . '/../src/DirectCharge.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../config/constants.php';


// ── Global CORS ──────────────────────────────────────────────
ApiMiddleware::cors();

// ── Resolve path ─────────────────────────────────────────────
$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$path       = parse_url($requestUri, PHP_URL_PATH);
$method     = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

// Normalise path: strip the install prefix and /api segment, keep /v1/...
//
// WampServer example:  /tech265-payment/api/v1/health  →  /v1/health
// Direct API call:     /api/v1/health                  →  /v1/health
// Already stripped:    /v1/health                      →  /v1/health
//
// Strategy: find the FIRST occurrence of /api followed by /v1
// This avoids the bug where a path like /logs/api also contains "/api"
if (preg_match('#/api(/v1/.*)$#', $path, $m)) {
    // Standard case: .../api/v1/...
    $path = $m[1];
} elseif (preg_match('#^/v1/#', $path) || $path === '/v1') {
    // Already clean (e.g. called directly without /api prefix)
    // $path is fine as-is
} else {
    // Fallback: try to find /v1/ anywhere in the path
    $pos = strpos($path, '/v1/');
    if ($pos !== false) {
        $path = substr($path, $pos);
    }
}

// Guarantee path starts with /
if (!$path || $path[0] !== '/') {
    $path = '/' . $path;
}

// ── Route table ──────────────────────────────────────────────
// [HTTP_METHOD, regex_pattern, handler_function, required_role]
$routes = [
    // Public – no API key needed
    ['GET',  '#^/v1/health$#i',                'route_health',           'public'],
    ['GET',  '#^/v1/status$#i',                'route_status',           'public'],
    ['GET',  '#^/v1/info$#i',                  'route_info',             'public'],

    // Payments – specific routes BEFORE the catch-all /{tx_ref}
    ['POST', '#^/v1/payments/initiate$#i',     'route_paymentsInitiate', 'full'],
    ['GET',  '#^/v1/payments/verify/(.+)$#i',  'route_paymentsVerify',   'readonly'],
    ['POST', '#^/v1/payments/callback$#i',     'route_paymentsCallback', 'webhook'],
    ['GET',  '#^/v1/payments/callback$#i',     'route_paymentsCallback', 'webhook'],
    ['GET',  '#^/v1/payments/return$#i',       'route_paymentsReturn',   'webhook'],
    ['GET',  '#^/v1/payments$#i',              'route_paymentsList',     'readonly'],
    ['GET',  '#^/v1/payments/([^/]+)$#i',      'route_paymentsGet',      'readonly'],

    // ── Direct Charge — unified (operator field: 'momo' | 'bank_transfer')
    ['GET',  '#^/v1/direct/operators$#i',                      'route_dcOperators',     'readonly'],
    ['GET',  '#^/v1/direct/banks$#i',                          'route_dcBanks',         'readonly'],
    ['POST', '#^/v1/direct/charge$#i',                         'route_dcCharge',        'full'],
    ['GET',  '#^/v1/direct/charge/([^/]+)/verify$#i',          'route_dcVerify',        'readonly'],
    ['GET',  '#^/v1/direct/charge/([^/]+)$#i',                 'route_dcDetails',       'readonly'],

    // ── Payout — unified (operator field: 'momo' | 'bank_transfer')
    ['POST', '#^/v1/payout$#i',                                'route_payoutInit',      'full'],
    ['GET',  '#^/v1/payout/list$#i',                           'route_payoutList',      'readonly'],
    ['GET',  '#^/v1/payout/([^/]+)$#i',                        'route_payoutDetails',   'readonly'],

    // Logs
    ['GET',  '#^/v1/logs/api$#i',              'route_logsApi',          'readonly'],
    ['GET',  '#^/v1/logs/errors$#i',           'route_logsErrors',       'readonly'],
    ['GET',  '#^/v1/logs/webhooks$#i',         'route_logsWebhooks',     'readonly'],

    // Stats
    ['GET',  '#^/v1/stats$#i',                 'route_stats',            'readonly'],
];

// ── Dispatch ─────────────────────────────────────────────────
$matched       = false;
$methodMismatch = false;

foreach ($routes as $route) {
    list($routeMethod, $pattern, $handler, $role) = $route;

    if (!preg_match($pattern, $path, $matches)) {
        continue; // path doesn't match this route
    }

    // Path matched — check method
    if ($routeMethod !== $method) {
        $methodMismatch = true;
        continue;
    }

    $matched      = true;
    $routeParams  = array_slice($matches, 1);

    // Authenticate (skip public routes)
    $keyMeta = ['name' => 'public', 'role' => 'public'];
    if ($role !== 'public') {
        $keyMeta = ApiMiddleware::authenticate($role);
        ApiMiddleware::rateLimit($keyMeta['name']);
    }

    // Dispatch
    $handler($routeParams, $keyMeta);
    break;
}

if (!$matched) {
    if ($methodMismatch) {
        ApiMiddleware::error('Method ' . $method . ' not allowed on this route.', 405, [], 'METHOD_NOT_ALLOWED');
    } else {
        ApiMiddleware::error('Route not found: ' . $method . ' ' . $path, 404, [], 'NOT_FOUND');
    }
}

// ════════════════════════════════════════════════════════════
// HANDLER FUNCTIONS  (prefixed route_ to avoid name clashes)
// ════════════════════════════════════════════════════════════

// ── GET /v1/health ───────────────────────────────────────────
function route_health($params, $keyMeta)
{
    $start  = microtime(true);
    $checks = [];

    // 1. Database
    try {
        Database::query("SELECT 1");
        $checks['database'] = ['status' => 'ok', 'latency_ms' => (int)round((microtime(true) - $start) * 1000)];
    } catch (Throwable $e) {
        $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
    }

    // 2. PayChangu API reachability (TCP only, no credentials)
    $tcpStart = microtime(true);
    $sock     = @fsockopen('api.paychangu.com', 443, $errno, $errstr, 3);
    if ($sock) {
        fclose($sock);
        $checks['paychangu_api'] = ['status' => 'reachable', 'latency_ms' => (int)round((microtime(true) - $tcpStart) * 1000)];
    } else {
        $checks['paychangu_api'] = ['status' => 'unreachable', 'error' => $errstr];
    }

    // 3. Log directory writable
    $logDir = LOG_DIR;
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $checks['log_directory'] = is_writable($logDir)
        ? ['status' => 'ok',   'path' => realpath($logDir)]
        : ['status' => 'fail', 'path' => $logDir, 'error' => 'Directory not writable'];

    // 4. Required PHP extensions
    $required = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
    $missing  = array_values(array_filter($required, function ($e) { return !extension_loaded($e); }));
    $checks['php_extensions'] = empty($missing)
        ? ['status' => 'ok',   'loaded' => $required]
        : ['status' => 'warn', 'missing' => $missing];

    // 5. Database row counts
    try {
        $checks['database_counts'] = [
            'status'       => 'ok',
            'transactions' => (int) Database::query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
            'api_logs'     => (int) Database::query("SELECT COUNT(*) FROM api_logs")->fetchColumn(),
            'open_errors'  => (int) Database::query("SELECT COUNT(*) FROM error_logs WHERE resolved=0")->fetchColumn(),
        ];
    } catch (Throwable $e) {
        $checks['database_counts'] = ['status' => 'fail', 'error' => $e->getMessage()];
    }

    // 6. Disk space
    $free  = @disk_free_space('.');
    $total = @disk_total_space('.');
    if ($free !== false && $total !== false) {
        $checks['disk'] = [
            'status'     => ($free / $total) < 0.05 ? 'warn' : 'ok',
            'free_mb'    => round($free  / 1048576, 1),
            'total_mb'   => round($total / 1048576, 1),
            'used_pct'   => round((1 - $free / $total) * 100, 1),
        ];
    }

    $overallOk = empty(array_filter($checks, function ($c) { return isset($c['status']) && $c['status'] === 'fail'; }));

    ApiMiddleware::json([
        'status'       => $overallOk ? 'healthy' : 'degraded',
        'timestamp'    => date('c'),
        'environment'  => APP_ENV,
        'version'      => APP_VERSION,
        'php_version'  => PHP_VERSION,
        'server'       => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
        'checks'       => $checks,
        'response_ms'  => (int)round((microtime(true) - $start) * 1000),
    ], $overallOk ? 200 : 503);
}

// ── GET /v1/status ───────────────────────────────────────────
function route_status($params, $keyMeta)
{
    ApiMiddleware::json([
        'status'    => 'ok',
        'service'   => APP_NAME,
        'version'   => APP_VERSION,
        'env'       => APP_ENV,
        'timestamp' => date('c'),
        'php'       => PHP_VERSION,
    ]);
}

// ── GET /v1/info ─────────────────────────────────────────────
function route_info($params, $keyMeta)
{
    ApiMiddleware::json([
        'service'     => APP_NAME,
        'version'     => APP_VERSION,
        'description' => 'Tech265 PayChangu Payment Gateway REST API',
        'base_url'    => APP_URL . '/api/v1',
        'auth'        => 'Pass your API key via X-API-Key header or ?api_key= query string.',
        'rate_limit'  => RATE_LIMIT_RPM . ' requests/minute per key',
        'note'        => 'For direct charge and payout, the operator field (momo|bank_transfer) selects the payment method. No separate endpoints per method.',
        'groups'      => [

            'system' => [
                ['method'=>'GET',  'path'=>'/v1/health',  'auth'=>false,       'description'=>'Full health check'],
                ['method'=>'GET',  'path'=>'/v1/status',  'auth'=>false,       'description'=>'Lightweight ping / version info'],
                ['method'=>'GET',  'path'=>'/v1/info',    'auth'=>false,       'description'=>'API route listing (this endpoint)'],
            ],

            'checkout_payments' => [
                ['method'=>'POST', 'path'=>'/v1/payments/initiate',        'auth'=>'full',     'description'=>'Initiate hosted checkout session'],
                ['method'=>'GET',  'path'=>'/v1/payments',                 'auth'=>'readonly', 'description'=>'List transactions (filter + paginate)'],
                ['method'=>'GET',  'path'=>'/v1/payments/{tx_ref}',        'auth'=>'readonly', 'description'=>'Get single transaction'],
                ['method'=>'GET',  'path'=>'/v1/payments/verify/{tx_ref}', 'auth'=>'readonly', 'description'=>'Verify transaction with PayChangu'],
                ['method'=>'POST', 'path'=>'/v1/payments/callback',        'auth'=>'webhook',  'description'=>'PayChangu IPN callback'],
                ['method'=>'GET',  'path'=>'/v1/payments/callback',        'auth'=>'webhook',  'description'=>'PayChangu redirect callback'],
                ['method'=>'GET',  'path'=>'/v1/payments/return',          'auth'=>'webhook',  'description'=>'PayChangu return URL (cancelled/failed)'],
            ],

            'direct_charge' => [
                ['method'=>'GET',  'path'=>'/v1/direct/operators',              'auth'=>'readonly', 'description'=>'List MoMo operators (use for both charge and payout)'],
                ['method'=>'GET',  'path'=>'/v1/direct/banks?currency=MWK',    'auth'=>'readonly', 'description'=>'List banks supported for bank-transfer payouts'],
                ['method'=>'POST', 'path'=>'/v1/direct/charge',                'auth'=>'full',
                    'description'=>'Unified charge endpoint. operator=momo → charges mobile wallet. operator=bank_transfer → generates virtual bank account.',
                    'body_operator_momo'          => ['operator','charge_id','mobile_money_operator_ref_id','mobile','amount','currency','first_name','last_name','email'],
                    'body_operator_bank_transfer' => ['operator','charge_id','amount','currency','create_permanent_account'],
                ],
                ['method'=>'GET',  'path'=>'/v1/direct/charge/{charge_id}/verify?operator=momo', 'auth'=>'readonly', 'description'=>'Verify a charge. operator param selects PayChangu endpoint.'],
                ['method'=>'GET',  'path'=>'/v1/direct/charge/{charge_id}?operator=momo',        'auth'=>'readonly', 'description'=>'Get full charge details. operator auto-detected from DB if omitted.'],
            ],

            'payout' => [
                ['method'=>'POST', 'path'=>'/v1/payout',                  'auth'=>'full',
                    'description'=>'Unified payout endpoint. operator=momo → sends to mobile wallet. operator=bank_transfer → sends to bank account.',
                    'body_operator_momo'          => ['operator','charge_id','amount','mobile_money_operator_ref_id','mobile'],
                    'body_operator_bank_transfer' => ['operator','charge_id','amount','bank_uuid','bank_account_name','bank_account_number'],
                ],
                ['method'=>'GET',  'path'=>'/v1/payout/list',             'auth'=>'readonly', 'description'=>'List all bank payouts from PayChangu account'],
                ['method'=>'GET',  'path'=>'/v1/payout/{charge_id}?operator=momo', 'auth'=>'readonly', 'description'=>'Get payout details. operator auto-detected from DB if omitted.'],
            ],

            'logs' => [
                ['method'=>'GET', 'path'=>'/v1/logs/api',      'auth'=>'readonly', 'description'=>'API activity logs'],
                ['method'=>'GET', 'path'=>'/v1/logs/errors',   'auth'=>'readonly', 'description'=>'Error logs'],
                ['method'=>'GET', 'path'=>'/v1/logs/webhooks', 'auth'=>'readonly', 'description'=>'Webhook logs'],
            ],

            'stats' => [
                ['method'=>'GET', 'path'=>'/v1/stats', 'auth'=>'readonly', 'description'=>'Revenue, success rates, channel breakdown, daily summary'],
            ],
        ],
    ]);
}

// ── POST /v1/payments/initiate ───────────────────────────────
function route_paymentsInitiate($params, $keyMeta)
{
    $body = ApiMiddleware::body();
    ApiMiddleware::validate($body, [
        'first_name' => 'required|max_len:100',
        'last_name'  => 'required|max_len:100',
        'email'      => 'required|email',
        'amount'     => 'required|numeric|min:1',
        'currency'   => 'required|in:' . implode(',', ALLOWED_CURRENCIES),
    ]);

    try {
        $pc     = new PayChangu();
        $result = $pc->initiatePayment([
            'first_name'  => htmlspecialchars(trim($body['first_name'])),
            'last_name'   => htmlspecialchars(trim($body['last_name'])),
            'email'       => trim($body['email']),
            'amount'      => (float) $body['amount'],
            'currency'    => strtoupper($body['currency']),
            'title'       => isset($body['title'])       ? $body['title']       : APP_NAME . ' Payment',
            'description' => isset($body['description']) ? $body['description'] : 'Online Payment',
            'meta'        => isset($body['meta'])        ? $body['meta']        : [],
        ]);

        Logger::logApiActivity([
            'tx_ref'       => isset($result['tx_ref']) ? $result['tx_ref'] : null,
            'action'       => 'API_INITIATE',
            'endpoint'     => APP_URL . '/api/v1/payments/initiate',
            'method'       => 'POST',
            'request_data' => array_merge($body, ['api_key_name' => $keyMeta['name']]),
            'is_error'     => $result['success'] ? 0 : 1,
        ]);

        if ($result['success']) {
            $checkoutUrl = null;
            if (isset($result['data']['checkout_url'])) {
                $checkoutUrl = $result['data']['checkout_url'];
            }
            ApiMiddleware::success([
                'tx_ref'       => $result['tx_ref'],
                'checkout_url' => $checkoutUrl,
                'status'       => 'pending',
                'currency'     => strtoupper($body['currency']),
                'amount'       => (float) $body['amount'],
            ], 'Payment initiated successfully.', 201);
        } else {
            $msg = isset($result['message']) ? $result['message'] : 'Payment initiation failed.';
            ApiMiddleware::error($msg, 400, [], 'PAYMENT_INIT_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'API_INITIATE_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/payments/verify/{tx_ref} ────────────────────────
function route_paymentsVerify($params, $keyMeta)
{
    $txRef = trim(isset($params[0]) ? $params[0] : '');
    if (!$txRef) {
        ApiMiddleware::error('tx_ref is required.', 400, [], 'MISSING_TX_REF');
    }

    try {
        $pc     = new PayChangu();
        $result = $pc->verifyTransaction($txRef);

        Logger::logApiActivity([
            'tx_ref'       => $txRef,
            'action'       => 'API_VERIFY',
            'endpoint'     => APP_URL . '/api/v1/payments/verify/' . $txRef,
            'method'       => 'GET',
            'request_data' => ['api_key_name' => $keyMeta['name']],
            'is_error'     => $result['success'] ? 0 : 1,
        ]);

        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Transaction verified.');
        } else {
            $msg = isset($result['message']) ? $result['message'] : 'Verification failed.';
            ApiMiddleware::error($msg, 400, [], 'VERIFY_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'API_VERIFY_EXCEPTION', 'tx_ref' => $txRef]));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/payments ─────────────────────────────────────────
function route_paymentsList($params, $keyMeta)
{
    $limit  = min(100, max(1, (int)(isset($_GET['limit'])  ? $_GET['limit']  : 20)));
    $page   = max(1,           (int)(isset($_GET['page'])   ? $_GET['page']   : 1));
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $qp     = [];

    if (!empty($_GET['status']))    { $where[] = 'status = ?';             $qp[] = $_GET['status']; }
    if (!empty($_GET['email']))     { $where[] = 'email LIKE ?';           $qp[] = '%' . $_GET['email'] . '%'; }
    if (!empty($_GET['currency']))  { $where[] = 'currency = ?';           $qp[] = strtoupper($_GET['currency']); }
    if (!empty($_GET['date_from'])) { $where[] = 'DATE(created_at) >= ?'; $qp[] = $_GET['date_from']; }
    if (!empty($_GET['date_to']))   { $where[] = 'DATE(created_at) <= ?'; $qp[] = $_GET['date_to']; }
    if (!empty($_GET['search']))    {
        $where[] = '(tx_ref LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        $qp[] = $s; $qp[] = $s; $qp[] = $s; $qp[] = $s;
    }

    $ws = implode(' AND ', $where);

    try {
        $total = (int) Database::query("SELECT COUNT(*) FROM transactions WHERE {$ws}", $qp)->fetchColumn();
        $rows  = Database::query(
            "SELECT * FROM transactions WHERE {$ws} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $qp
        )->fetchAll();

        ApiMiddleware::success([
            'transactions' => $rows,
            'pagination'   => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => max(1, (int)ceil($total / $limit)),
                'from'         => $total ? $offset + 1 : 0,
                'to'           => min($offset + $limit, $total),
            ],
        ], 'Transactions retrieved.');
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'API_LIST_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/payments/{tx_ref} ────────────────────────────────
function route_paymentsGet($params, $keyMeta)
{
    $txRef = trim(isset($params[0]) ? $params[0] : '');
    if (!$txRef) {
        ApiMiddleware::error('tx_ref is required.', 400, [], 'MISSING_TX_REF');
    }

    try {
        $tx = Database::query("SELECT * FROM transactions WHERE tx_ref = ? LIMIT 1", [$txRef])->fetch();
        if (!$tx) {
            ApiMiddleware::error('Transaction not found.', 404, [], 'NOT_FOUND');
        }
        ApiMiddleware::success($tx, 'Transaction retrieved.');
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'API_GET_TX_EXCEPTION', 'tx_ref' => $txRef]));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── POST|GET /v1/payments/callback ───────────────────────────
function route_paymentsCallback($params, $keyMeta)
{
    $txRef  = isset($_GET['tx_ref'])  ? $_GET['tx_ref']  : (isset($_POST['tx_ref'])  ? $_POST['tx_ref']  : null);
    $trxRef = isset($_GET['trx_ref']) ? $_GET['trx_ref'] : (isset($_POST['trx_ref']) ? $_POST['trx_ref'] : null);
    $status = isset($_GET['status'])  ? $_GET['status']  : (isset($_POST['status'])  ? $_POST['status']  : null);
    $raw    = file_get_contents('php://input');
    $hook   = null;
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $hook = $decoded;
        }
    }

    if ($hook) {
        if (!$txRef  && isset($hook['tx_ref']))  $txRef  = $hook['tx_ref'];
        if (!$trxRef && isset($hook['trx_ref'])) $trxRef = $hook['trx_ref'];
        if (!$status && isset($hook['status']))  $status = $hook['status'];
    }

    $payload = $hook ? $hook : array_merge($_GET, $_POST);
    Logger::logWebhook([
        'tx_ref'     => $txRef,
        'event_type' => isset($hook['event']) ? $hook['event'] : 'checkout.payment',
        'payload'    => $payload,
        'processed'  => 0,
    ]);

    if (!$txRef) {
        ApiMiddleware::error('Missing tx_ref in callback payload.', 400, [], 'MISSING_TX_REF');
    }

    try {
        // Persist PayChangu's trx_ref early so verifyTransaction can resolve it
        if ($trxRef && $txRef) {
            try { Database::update('transactions', ['transaction_id' => $trxRef], ['tx_ref' => $txRef]); } catch (Throwable $e) { /* non-fatal */ }
        }

        $pc     = new PayChangu();
        $result = $pc->verifyTransaction($txRef);

        Database::query(
            "UPDATE webhook_logs SET processed=1 WHERE tx_ref=? ORDER BY id DESC LIMIT 1",
            [$txRef]
        );

        $verified   = $result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success';
        $txStatus   = isset($result['data']['status']) ? $result['data']['status'] : $status;

        Logger::info('Callback processed', ['tx_ref' => $txRef, 'verified' => $verified]);

        ApiMiddleware::success([
            'tx_ref'   => $txRef,
            'verified' => $verified,
            'status'   => $txStatus,
        ], 'Callback processed successfully.');
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['tx_ref' => $txRef, 'error_type' => 'CALLBACK_EXCEPTION']));
        ApiMiddleware::error('Callback processing failed.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/payments/return ──────────────────────────────────
function route_paymentsReturn($params, $keyMeta)
{
    $txRef  = isset($_GET['tx_ref']) ? $_GET['tx_ref'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : 'failed';

    if ($txRef) {
        try {
            Database::update('transactions', ['status' => 'cancelled'], ['tx_ref' => $txRef, 'status' => 'pending']);
            Logger::info('Return URL hit', ['tx_ref' => $txRef, 'status' => $status]);
            Logger::logApiActivity([
                'tx_ref'   => $txRef,
                'action'   => 'RETURN_URL',
                'endpoint' => APP_URL . '/api/v1/payments/return',
                'method'   => 'GET',
                'request_data' => ['status' => $status],
            ]);
        } catch (Throwable $e) { /* non-fatal */ }
    }

    // Redirect browsers, return JSON for API clients
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    if (strpos($accept, 'text/html') !== false) {
        $url = APP_URL . '/public/result.php?verified=0' . ($txRef ? '&tx_ref=' . urlencode($txRef) : '');
        header('Location: ' . $url);
        exit;
    }

    ApiMiddleware::success([
        'tx_ref' => $txRef,
        'status' => 'cancelled',
    ], 'Payment was cancelled or failed.');
}

// ── GET /v1/logs/api ─────────────────────────────────────────
function route_logsApi($params, $keyMeta)
{
    route_genericLogList('api_logs', 'id,tx_ref,action,method,endpoint,http_status,duration_ms,is_error,ip_address,created_at', ['action', 'is_error']);
}

// ── GET /v1/logs/errors ──────────────────────────────────────
function route_logsErrors($params, $keyMeta)
{
    route_genericLogList('error_logs', 'id,tx_ref,error_type,error_code,message,file,line_number,resolved,ip_address,created_at', ['error_type', 'resolved']);
}

// ── GET /v1/logs/webhooks ────────────────────────────────────
function route_logsWebhooks($params, $keyMeta)
{
    route_genericLogList('webhook_logs', 'id,tx_ref,event_type,processed,ip_address,created_at', ['event_type', 'processed']);
}

function route_genericLogList($table, $cols, $filterFields)
{
    $limit  = min(100, max(1, (int)(isset($_GET['limit']) ? $_GET['limit'] : 20)));
    $page   = max(1,           (int)(isset($_GET['page'])  ? $_GET['page']  : 1));
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $qp    = [];

    foreach ($filterFields as $f) {
        if (isset($_GET[$f]) && $_GET[$f] !== '') {
            $where[] = "`{$f}` = ?";
            $qp[]    = $_GET[$f];
        }
    }
    if (!empty($_GET['tx_ref']))    { $where[] = 'tx_ref = ?';            $qp[] = $_GET['tx_ref']; }
    if (!empty($_GET['date_from'])) { $where[] = 'DATE(created_at) >= ?'; $qp[] = $_GET['date_from']; }
    if (!empty($_GET['date_to']))   { $where[] = 'DATE(created_at) <= ?'; $qp[] = $_GET['date_to']; }

    $ws = implode(' AND ', $where);

    try {
        $total = (int) Database::query("SELECT COUNT(*) FROM `{$table}` WHERE {$ws}", $qp)->fetchColumn();
        $rows  = Database::query(
            "SELECT {$cols} FROM `{$table}` WHERE {$ws} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $qp
        )->fetchAll();

        ApiMiddleware::success([
            'records'    => $rows,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => max(1, (int)ceil($total / $limit)),
            ],
        ], ucfirst(str_replace('_', ' ', $table)) . ' retrieved.');
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'API_LOG_LIST_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/stats ────────────────────────────────────────────
function route_stats($params, $keyMeta)
{
    try {
        $db = Database::getInstance();

        $total    = (int)   $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
        $success  = (int)   $db->query("SELECT COUNT(*) FROM transactions WHERE status='success'")->fetchColumn();
        $failed   = (int)   $db->query("SELECT COUNT(*) FROM transactions WHERE status IN('failed','cancelled')")->fetchColumn();
        $pending  = (int)   $db->query("SELECT COUNT(*) FROM transactions WHERE status='pending'")->fetchColumn();
        $revenue  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='success'")->fetchColumn();
        $charges  = (float) $db->query("SELECT COALESCE(SUM(charges),0) FROM transactions WHERE status='success'")->fetchColumn();
        $errOpen  = (int)   $db->query("SELECT COUNT(*) FROM error_logs WHERE resolved=0")->fetchColumn();
        $apiToday = (int)   $db->query("SELECT COUNT(*) FROM api_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $apiErr   = (int)   $db->query("SELECT COUNT(*) FROM api_logs WHERE DATE(created_at)=CURDATE() AND is_error=1")->fetchColumn();
        $avgMs    = (float) $db->query("SELECT COALESCE(ROUND(AVG(duration_ms),1),0) FROM api_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();

        $byCurrency = $db->query(
            "SELECT currency, COUNT(*) as count, COALESCE(SUM(amount),0) as total_amount
             FROM transactions WHERE status='success' GROUP BY currency"
        )->fetchAll();

        $daily = $db->query(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(status='success') as success,
                    SUM(status IN('failed','cancelled')) as failed,
                    COALESCE(SUM(CASE WHEN status='success' THEN amount ELSE 0 END),0) as revenue
             FROM transactions
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(created_at) ORDER BY date"
        )->fetchAll();

        $channels = $db->query(
            "SELECT payment_channel, COUNT(*) as count
             FROM transactions WHERE status='success' AND payment_channel IS NOT NULL
             GROUP BY payment_channel ORDER BY count DESC LIMIT 5"
        )->fetchAll();

        ApiMiddleware::success([
            'transactions' => [
                'total'        => $total,
                'success'      => $success,
                'failed'       => $failed,
                'pending'      => $pending,
                'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            ],
            'revenue' => [
                'total_amount'  => $revenue,
                'total_charges' => $charges,
                'net_revenue'   => round($revenue - $charges, 2),
                'by_currency'   => $byCurrency,
            ],
            'api' => [
                'calls_today'    => $apiToday,
                'errors_today'   => $apiErr,
                'avg_latency_ms' => $avgMs,
                'open_errors'    => $errOpen,
            ],
            'top_channels'  => $channels,
            'daily_summary' => $daily,
            'generated_at'  => date('c'),
        ], 'Statistics retrieved.');
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'API_STATS_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}


// ════════════════════════════════════════════════════════════
// UNIFIED DIRECT CHARGE & PAYOUT HANDLERS
// ════════════════════════════════════════════════════════════

// ── GET /v1/direct/operators ─────────────────────────────────
// Returns MoMo operators for both charge and payout operations
function route_dcOperators($params, $keyMeta)
{
    try {
        $dc     = new DirectCharge();
        $result = $dc->getOperators();
        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Mobile money operators retrieved.');
        } else {
            ApiMiddleware::error($result['message'] ?? 'Failed to retrieve operators.', 400, [], 'OPERATORS_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'GET_OPERATORS_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/direct/banks?currency=MWK ────────────────────────
// Returns banks supported for bank-transfer payouts
function route_dcBanks($params, $keyMeta)
{
    $currency = strtoupper(isset($_GET['currency']) ? $_GET['currency'] : 'MWK');
    try {
        $dc     = new DirectCharge();
        $result = $dc->getSupportedBanks($currency);
        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Supported banks retrieved.');
        } else {
            ApiMiddleware::error($result['message'] ?? 'Failed to retrieve banks.', 400, [], 'BANKS_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'GET_BANKS_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── POST /v1/direct/charge ────────────────────────────────────
// Unified charge endpoint — operator field routes to MoMo or Bank
function route_dcCharge($params, $keyMeta)
{
    $body = ApiMiddleware::body();

    // Validate common required fields
    ApiMiddleware::validate($body, [
        'operator' => 'required|in:airtel,tnm,mpamba,bank_transfer',
        'amount'   => 'required|numeric|min:1',
    ]);

    $operator = strtolower($body['operator']);

    // Validate operator-specific required fields
    if (in_array($operator, ['airtel', 'tnm', 'mpamba'])) {
        ApiMiddleware::validate($body, [
            'phone'     => 'required|max_len:20',
            'email'     => 'email',
            'firstname' => 'max_len:100',
            'lastname'  => 'max_len:100',
        ]);
    } elseif ($operator === 'bank_transfer') {
        ApiMiddleware::validate($body, [
            'bank_name'      => 'required|max_len:200',
            'account_number' => 'required|max_len:50',
            'account_name'   => 'required|max_len:200',
        ]);
    }

    try {
        $dc     = new DirectCharge();
        $result = $dc->charge($body);

        if ($result['success']) {
            $msg = in_array($operator, ['airtel', 'tnm', 'mpamba'])
                ? 'Mobile money charge initiated. Customer will receive a payment prompt on their phone.'
                : 'Bank transfer charge initialised. Share the generated account details with your customer.';
            ApiMiddleware::success($result['data'], $msg, 201);
        } else {
            ApiMiddleware::error($result['message'] ?? 'Charge failed.', 400, [], 'CHARGE_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'CHARGE_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/direct/charge/{charge_id}/verify?operator=momo ───
// Verify a charge — operator query param routes to correct PayChangu endpoint
function route_dcVerify($params, $keyMeta)
{
    $chargeId = trim(isset($params[0]) ? $params[0] : '');
    $operator = strtolower(isset($_GET['operator']) ? $_GET['operator'] : '');

    if (!$chargeId) {
        ApiMiddleware::error('charge_id is required.', 400, [], 'MISSING_CHARGE_ID');
    }
    if (!in_array($operator, ['momo', 'bank_transfer'])) {
        ApiMiddleware::error("operator query param is required: 'momo' or 'bank_transfer'.", 400, [], 'MISSING_OPERATOR');
    }

    try {
        $dc     = new DirectCharge();
        $result = $dc->verifyCharge($chargeId, $operator);

        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Charge verified.');
        } else {
            ApiMiddleware::error($result['message'] ?? 'Verification failed.', 400, [], 'VERIFY_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'VERIFY_EXCEPTION', 'charge_id' => $chargeId]));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/direct/charge/{charge_id}?operator=momo ──────────
// Get full charge details — operator query param routes to correct endpoint
function route_dcDetails($params, $keyMeta)
{
    $chargeId = trim(isset($params[0]) ? $params[0] : '');
    $operator = strtolower(isset($_GET['operator']) ? $_GET['operator'] : '');

    if (!$chargeId) {
        ApiMiddleware::error('charge_id is required.', 400, [], 'MISSING_CHARGE_ID');
    }

    // If operator not supplied, try to look it up from our DB
    if (!in_array($operator, ['momo', 'bank_transfer'])) {
        try {
            $row = Database::query(
                "SELECT charge_type FROM direct_charges WHERE charge_id = ? LIMIT 1",
                [$chargeId]
            )->fetch();
            $operator = $row ? $row['charge_type'] : '';
        } catch (Throwable $e) { }
    }

    if (!in_array($operator, ['momo', 'bank_transfer'])) {
        ApiMiddleware::error("operator query param required: 'momo' or 'bank_transfer'.", 400, [], 'MISSING_OPERATOR');
    }

    try {
        $dc     = new DirectCharge();
        $result = $dc->getChargeDetails($chargeId, $operator);

        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Charge details retrieved.');
        } else {
            ApiMiddleware::error($result['message'] ?? 'Could not retrieve charge details.', 404, [], 'DETAILS_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'DETAILS_EXCEPTION', 'charge_id' => $chargeId]));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── POST /v1/payout ───────────────────────────────────────────
// Unified payout endpoint — amount + phone; operator auto-detected from phone prefix
function route_payoutInit($params, $keyMeta)
{
    $body = ApiMiddleware::body();

    // Only amount and phone are required
    ApiMiddleware::validate($body, [
        'amount' => 'required|numeric|min:1',
        'phone'  => 'required|max_len:20',
    ]);

    try {
        $dc     = new DirectCharge();
        $result = $dc->payout($body);

        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Payout initiated. Funds will be sent to the recipient\'s mobile wallet.', 201);
        } else {
            ApiMiddleware::error($result['message'] ?? 'Payout failed.', 400, [], 'PAYOUT_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'PAYOUT_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/payout/list ───────────────────────────────────────
// List all bank payouts from PayChangu account
function route_payoutList($params, $keyMeta)
{
    try {
        $dc     = new DirectCharge();
        $result = $dc->listPayouts();
        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Payouts retrieved.');
        } else {
            ApiMiddleware::error($result['message'] ?? 'Failed to list payouts.', 400, [], 'PAYOUT_LIST_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'PAYOUT_LIST_EXCEPTION']));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}

// ── GET /v1/payout/{charge_id}?operator=momo ─────────────────
// Get payout details — operator query param routes to correct endpoint
function route_payoutDetails($params, $keyMeta)
{
    $chargeId = trim(isset($params[0]) ? $params[0] : '');
    $operator = strtolower(isset($_GET['operator']) ? $_GET['operator'] : '');

    if (!$chargeId) {
        ApiMiddleware::error('charge_id is required.', 400, [], 'MISSING_CHARGE_ID');
    }

    // Auto-detect operator from DB if not supplied
    if (!in_array($operator, ['momo', 'bank_transfer'])) {
        try {
            $row = Database::query(
                "SELECT payout_type FROM payouts WHERE charge_id = ? LIMIT 1",
                [$chargeId]
            )->fetch();
            $operator = $row ? $row['payout_type'] : '';
        } catch (Throwable $e) { }
    }

    if (!in_array($operator, ['momo', 'bank_transfer'])) {
        ApiMiddleware::error("operator query param required: 'momo' or 'bank_transfer'.", 400, [], 'MISSING_OPERATOR');
    }

    try {
        $dc     = new DirectCharge();
        $result = $dc->getPayoutDetails($chargeId, $operator);

        if ($result['success']) {
            ApiMiddleware::success($result['data'], 'Payout details retrieved.');
        } else {
            ApiMiddleware::error($result['message'] ?? 'Could not retrieve payout details.', 404, [], 'PAYOUT_DETAILS_FAILED');
        }
    } catch (Throwable $e) {
        Logger::logError(Logger::fromThrowable($e, ['error_type' => 'PAYOUT_DETAILS_EXCEPTION', 'charge_id' => $chargeId]));
        ApiMiddleware::error('Internal server error.', 500, [], 'SERVER_ERROR');
    }
}
