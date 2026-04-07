<?php
require_once __DIR__ . '/../src/AdminAuth.php';
require_once __DIR__ . '/../config/constants.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

$apiBase = APP_URL . '/api/v1';

include __DIR__ . '/layout/header.php';
?>

<style>
.endpoint-group { margin-bottom: 32px; }
.group-title { font-size:.75rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#94a3b8; margin-bottom:12px; padding-left:4px; }
.endpoint { border:1.5px solid #e2e8f0; border-radius:12px; margin-bottom:10px; overflow:hidden; }
.endpoint-header { display:flex; align-items:center; gap:12px; padding:13px 16px; cursor:pointer; user-select:none; background:#fff; transition:background .15s; }
.endpoint-header:hover { background:#f8fafc; }
.method-badge { display:inline-block; padding:3px 10px; border-radius:6px; font-size:.72rem; font-weight:800; min-width:54px; text-align:center; }
.method-GET    { background:#dcfce7; color:#166534; }
.method-POST   { background:#dbeafe; color:#1e40af; }
.method-PUT    { background:#fef9c3; color:#713f12; }
.method-DELETE { background:#fee2e2; color:#991b1b; }
.endpoint-path { font-family:monospace; font-size:.88rem; font-weight:600; color:#1e1b4b; flex:1; }
.auth-badge { font-size:.72rem; padding:2px 8px; border-radius:20px; }
.auth-none   { background:#f1f5f9; color:#64748b; }
.auth-key    { background:#ede9fe; color:#5b21b6; }
.auth-full   { background:#fee2e2; color:#991b1b; }
.auth-webhook{ background:#fef9c3; color:#713f12; }
.endpoint-body { display:none; border-top:1.5px solid #e2e8f0; padding:18px; background:#fafafa; }
.endpoint-body.open { display:block; }
.desc { font-size:.87rem; color:#475569; margin-bottom:14px; }
.params-table { width:100%; border-collapse:collapse; font-size:.82rem; margin-bottom:14px; }
.params-table th { padding:7px 10px; background:#f1f5f9; text-align:left; font-size:.75rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.params-table td { padding:7px 10px; border-bottom:1px solid #e2e8f0; vertical-align:top; }
.params-table .required { color:#dc2626; font-weight:700; font-size:.72rem; }
.params-table .optional { color:#94a3b8; font-size:.72rem; }
.try-section { background:#fff; border:1.5px solid #e2e8f0; border-radius:10px; padding:16px; margin-top:14px; }
.try-section h5 { font-size:.82rem; font-weight:700; color:#334155; margin-bottom:10px; }
.try-input { width:100%; padding:8px 11px; border:1.5px solid #e2e8f0; border-radius:7px; font-size:.82rem; margin-bottom:8px; font-family:monospace; }
.try-input:focus { outline:none; border-color:var(--primary); }
.try-btn { background:var(--primary); color:#fff; border:none; padding:8px 18px; border-radius:7px; font-size:.82rem; font-weight:600; cursor:pointer; }
.try-btn:hover { opacity:.88; }
.response-box { margin-top:12px; background:#1e293b; color:#a7f3d0; padding:14px; border-radius:8px; font-size:.77rem; font-family:monospace; white-space:pre-wrap; max-height:320px; overflow:auto; display:none; }
.chevron { color:#94a3b8; font-size:.9rem; transition:transform .2s; }
.chevron.open { transform:rotate(90deg); }
</style>

<div class="page-title">📚 API Reference & Explorer</div>

<div class="card" style="margin-bottom:22px">
  <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:240px">
      <h3 style="margin-bottom:6px">Base URL</h3>
      <code style="background:#f1f5f9;padding:6px 12px;border-radius:7px;font-size:.9rem;display:inline-block"><?= $apiBase ?></code>
    </div>
    <div style="flex:1;min-width:240px">
      <h3 style="margin-bottom:6px">Authentication</h3>
      <p style="font-size:.85rem;color:#64748b">Pass your API key via <code>X-API-Key</code> header or <code>?api_key=</code> query parameter.</p>
    </div>
    <div style="flex:1;min-width:200px">
      <h3 style="margin-bottom:6px">Rate Limit</h3>
      <p style="font-size:.85rem;color:#64748b">60 requests per minute per API key. Headers: <code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code>.</p>
    </div>
  </div>
</div>

<!-- API Key Tester Input -->
<div class="card" style="margin-bottom:22px;background:#ede9fe;border:1.5px solid #c4b5fd">
  <h3 style="margin-bottom:8px">🔑 Your API Key (for Try It)</h3>
  <div style="display:flex;gap:10px;align-items:center">
    <input type="text" id="globalApiKey" class="try-input" style="margin:0;max-width:400px"
           placeholder="Paste your X-API-Key here to test endpoints…"
           value="tech265-dev-key-CHANGE-ME">
    <span style="font-size:.8rem;color:#5b21b6">Used in all "Try It" requests below</span>
  </div>
</div>

<?php
$groups = [
  'System' => [
    [
      'method'=>'GET', 'path'=>'/health', 'auth'=>'none',
      'desc' => 'Full health check — tests DB connectivity, PayChangu API reachability, PHP extensions, disk write access, and returns counts from all tables.',
      'params' => [],
      'sample_response' => '{"status":"healthy","timestamp":"2025-01-01T00:00:00+00:00","version":"1.0.0","checks":{"database":{"status":"ok","latency_ms":2},"paychangu_api":{"status":"reachable","latency_ms":45},"log_directory":{"status":"ok"},"php_extensions":{"status":"ok"},"database_counts":{"transactions":120,"api_logs":350,"error_logs":2}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/status', 'auth'=>'none',
      'desc' => 'Lightweight ping endpoint — returns service name, version, environment and timestamp. Use for uptime monitoring.',
      'params' => [],
      'sample_response' => '{"status":"ok","service":"Tech265 Payments","version":"1.0.0","env":"development","timestamp":"2025-01-01T00:00:00+00:00"}',
    ],
    [
      'method'=>'GET', 'path'=>'/info', 'auth'=>'none',
      'desc' => 'Returns API metadata, base URL, auth instructions, and a full list of all available routes.',
      'params' => [],
      'sample_response' => '{"service":"Tech265 Payments","version":"1.0.0","base_url":"...","endpoints":[...]}',
    ],
  ],
  'Payments' => [
    [
      'method'=>'POST', 'path'=>'/payments/initiate', 'auth'=>'full',
      'desc' => 'Create a new payment session with PayChangu. Returns a checkout_url to redirect your customer to.',
      'params' => [
        ['name'=>'first_name',  'type'=>'string','required'=>true, 'desc'=>'Customer first name'],
        ['name'=>'last_name',   'type'=>'string','required'=>true, 'desc'=>'Customer last name'],
        ['name'=>'email',       'type'=>'string','required'=>true, 'desc'=>'Customer email address (receipt sent here)'],
        ['name'=>'amount',      'type'=>'number','required'=>true, 'desc'=>'Amount to charge (must be > 0)'],
        ['name'=>'currency',    'type'=>'string','required'=>true, 'desc'=>'MWK or USD'],
        ['name'=>'title',       'type'=>'string','required'=>false,'desc'=>'Payment page title'],
        ['name'=>'description', 'type'=>'string','required'=>false,'desc'=>'Payment description'],
        ['name'=>'meta',        'type'=>'object','required'=>false,'desc'=>'Arbitrary key-value metadata'],
      ],
      'body_example' => "{\n  \"first_name\": \"John\",\n  \"last_name\": \"Banda\",\n  \"email\": \"john@example.com\",\n  \"amount\": 5000,\n  \"currency\": \"MWK\",\n  \"title\": \"Course Registration\",\n  \"description\": \"Tech265 Training Fee\"\n}",
      'sample_response' => '{"status":"success","message":"Payment initiated successfully.","data":{"tx_ref":"T265-AB12CD-1700000000","checkout_url":"https://checkout.paychangu.com/...","status":"pending","currency":"MWK","amount":5000}}',
    ],
    [
      'method'=>'GET', 'path'=>'/payments', 'auth'=>'key',
      'desc' => 'List all transactions with optional filters and pagination.',
      'params' => [
        ['name'=>'status',    'type'=>'string','required'=>false,'desc'=>'pending | success | failed | cancelled | verified'],
        ['name'=>'email',     'type'=>'string','required'=>false,'desc'=>'Filter by customer email (partial match)'],
        ['name'=>'currency',  'type'=>'string','required'=>false,'desc'=>'MWK or USD'],
        ['name'=>'search',    'type'=>'string','required'=>false,'desc'=>'Search tx_ref, email, first_name, last_name'],
        ['name'=>'date_from', 'type'=>'date',  'required'=>false,'desc'=>'YYYY-MM-DD'],
        ['name'=>'date_to',   'type'=>'date',  'required'=>false,'desc'=>'YYYY-MM-DD'],
        ['name'=>'page',      'type'=>'int',   'required'=>false,'desc'=>'Page number (default: 1)'],
        ['name'=>'limit',     'type'=>'int',   'required'=>false,'desc'=>'Results per page (default: 20, max: 100)'],
      ],
      'sample_response' => '{"status":"success","data":{"transactions":[...],"pagination":{"total":150,"per_page":20,"current_page":1,"last_page":8}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/payments/{tx_ref}', 'auth'=>'key',
      'desc' => 'Retrieve full details of a single transaction by its tx_ref.',
      'params' => [
        ['name'=>'tx_ref','type'=>'path','required'=>true,'desc'=>'Transaction reference (e.g. T265-AB12CD-...)'],
      ],
      'sample_response' => '{"status":"success","data":{"id":1,"tx_ref":"T265-...","email":"john@example.com","amount":5000,"status":"success",...}}',
    ],
    [
      'method'=>'GET', 'path'=>'/payments/verify/{tx_ref}', 'auth'=>'key',
      'desc' => 'Verify a transaction status directly with PayChangu and update the local DB record. Always call this before providing value to a customer.',
      'params' => [
        ['name'=>'tx_ref','type'=>'path','required'=>true,'desc'=>'Transaction reference to verify'],
      ],
      'sample_response' => '{"status":"success","data":{"event_type":"checkout.payment","tx_ref":"...","status":"success","amount":5000,"currency":"MWK","customer":{"email":"..."}}}',
    ],
    [
      'method'=>'POST', 'path'=>'/payments/callback', 'auth'=>'webhook',
      'desc' => 'PayChangu IPN callback endpoint. Set this as your callback_url when initiating payments. Also accepts GET for redirect-based callbacks. Automatically verifies and updates transaction status.',
      'params' => [
        ['name'=>'tx_ref', 'type'=>'string','required'=>true,'desc'=>'Transaction reference from PayChangu (query param or POST body)'],
        ['name'=>'status', 'type'=>'string','required'=>false,'desc'=>'Payment status'],
      ],
      'sample_response' => '{"status":"success","message":"Callback processed.","data":{"tx_ref":"T265-...","verified":true,"status":"success"}}',
    ],
    [
      'method'=>'GET', 'path'=>'/payments/return', 'auth'=>'webhook',
      'desc' => 'PayChangu return URL — called when a customer cancels or payment repeatedly fails. Set as your return_url. Browsers are redirected to the result page; API clients receive JSON.',
      'params' => [
        ['name'=>'tx_ref', 'type'=>'query','required'=>false,'desc'=>'Transaction reference'],
        ['name'=>'status', 'type'=>'query','required'=>false,'desc'=>'Usually "failed"'],
      ],
      'sample_response' => '{"status":"success","data":{"tx_ref":"T265-...","status":"cancelled"}}',
    ],
  ],
  'Logs' => [
    [
      'method'=>'GET', 'path'=>'/logs/api', 'auth'=>'key',
      'desc' => 'List API activity logs. Shows every outbound call made to PayChangu with request/response details and latency.',
      'params' => [
        ['name'=>'action',    'type'=>'string','required'=>false,'desc'=>'e.g. INITIATE_PAYMENT, VERIFY_PAYMENT'],
        ['name'=>'is_error',  'type'=>'int',   'required'=>false,'desc'=>'0 (success) or 1 (error)'],
        ['name'=>'tx_ref',    'type'=>'string','required'=>false,'desc'=>'Filter by transaction ref'],
        ['name'=>'date_from', 'type'=>'date',  'required'=>false,'desc'=>'YYYY-MM-DD'],
        ['name'=>'date_to',   'type'=>'date',  'required'=>false,'desc'=>'YYYY-MM-DD'],
        ['name'=>'page',      'type'=>'int',   'required'=>false,'desc'=>'Page number'],
        ['name'=>'limit',     'type'=>'int',   'required'=>false,'desc'=>'Per page (max 100)'],
      ],
      'sample_response' => '{"status":"success","data":{"records":[{"id":1,"action":"INITIATE_PAYMENT","http_status":200,"duration_ms":312,...}],"pagination":{...}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/logs/errors', 'auth'=>'key',
      'desc' => 'List error logs. Shows all caught exceptions and API errors with stack traces and context.',
      'params' => [
        ['name'=>'error_type','type'=>'string','required'=>false,'desc'=>'e.g. CURL_ERROR, API_HTTP_ERROR'],
        ['name'=>'resolved',  'type'=>'int',   'required'=>false,'desc'=>'0 (open) or 1 (resolved)'],
        ['name'=>'tx_ref',    'type'=>'string','required'=>false,'desc'=>'Filter by transaction ref'],
        ['name'=>'date_from', 'type'=>'date',  'required'=>false,'desc'=>'YYYY-MM-DD'],
        ['name'=>'date_to',   'type'=>'date',  'required'=>false,'desc'=>'YYYY-MM-DD'],
        ['name'=>'page',      'type'=>'int',   'required'=>false,'desc'=>'Page number'],
        ['name'=>'limit',     'type'=>'int',   'required'=>false,'desc'=>'Per page (max 100)'],
      ],
      'sample_response' => '{"status":"success","data":{"records":[{"error_type":"CURL_ERROR","message":"...","resolved":0,...}]}}',
    ],
    [
      'method'=>'GET', 'path'=>'/logs/webhooks', 'auth'=>'key',
      'desc' => 'List incoming webhook payloads from PayChangu.',
      'params' => [
        ['name'=>'processed', 'type'=>'int',   'required'=>false,'desc'=>'0 (pending) or 1 (processed)'],
        ['name'=>'event_type','type'=>'string','required'=>false,'desc'=>'e.g. checkout.payment'],
        ['name'=>'tx_ref',    'type'=>'string','required'=>false,'desc'=>'Filter by transaction ref'],
        ['name'=>'page',      'type'=>'int',   'required'=>false,'desc'=>'Page number'],
        ['name'=>'limit',     'type'=>'int',   'required'=>false,'desc'=>'Per page (max 100)'],
      ],
      'sample_response' => '{"status":"success","data":{"records":[{"tx_ref":"T265-...","event_type":"checkout.payment","processed":1,...}]}}',
    ],
  ],
  'Direct Charge – Mobile Money' => [
    [
      'method'=>'GET', 'path'=>'/direct/momo/operators', 'auth'=>'key',
      'desc' => 'List all supported mobile money operators (Airtel Money, TNM Mpamba etc.) available for direct charges.',
      'params' => [],
      'sample_response' => '{"status":"success","data":[{"ref_id":"20be6c20-...","name":"Airtel Money","country":"Malawi"}]}',
    ],
    [
      'method'=>'POST', 'path'=>'/direct/momo/charge', 'auth'=>'full',
      'desc' => 'Directly charge a customers mobile money wallet. The customer receives a payment prompt on their phone to approve.',
      'params' => [
        ['name'=>'mobile_money_operator_ref_id','type'=>'string','required'=>true, 'desc'=>'Operator UUID from GET /direct/momo/operators'],
        ['name'=>'mobile',    'type'=>'string','required'=>true,  'desc'=>'Customer mobile number e.g. 0991234567'],
        ['name'=>'amount',    'type'=>'number','required'=>true,  'desc'=>'Amount in MWK'],
        ['name'=>'charge_id', 'type'=>'string','required'=>true,  'desc'=>'Your unique transaction reference e.g. PC-T265-XXXX'],
        ['name'=>'first_name','type'=>'string','required'=>false, 'desc'=>'Customer first name'],
        ['name'=>'last_name', 'type'=>'string','required'=>false, 'desc'=>'Customer last name'],
        ['name'=>'email',     'type'=>'string','required'=>false, 'desc'=>'Customer email'],
      ],
      'body_example' => '{"mobile_money_operator_ref_id":"20be6c20-adeb-4b5b-a7ba-0769820df4fb","mobile":"0991234567","amount":1000,"charge_id":"PC-T265-A1B2C3D4"}',
      'sample_response' => '{"status":"success","data":{"amount":1000,"charge_id":"PC-T265-A1B2C3D4","status":"pending","mobile":"+265991234567","mobile_money":{"name":"Airtel Money"}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/direct/momo/charge/{charge_id}/verify', 'auth'=>'key',
      'desc' => 'Verify the status of a mobile money direct charge. Call this after receiving a webhook to confirm payment.',
      'params' => [['name'=>'charge_id','type'=>'path','required'=>true,'desc'=>'Your charge_id reference']],
      'sample_response' => '{"status":"success","data":{"status":"success","amount":1000,"charge_id":"PC-T265-A1B2C3D4","authorization":{"channel":"Mobile Money","provider":"Airtel Money"}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/direct/momo/charge/{charge_id}', 'auth'=>'key',
      'desc' => 'Retrieve full details of a mobile money charge including transaction logs.',
      'params' => [['name'=>'charge_id','type'=>'path','required'=>true,'desc'=>'Your charge_id reference']],
      'sample_response' => '{"status":"success","data":{"amount":1000,"charge_id":"PC-T265-A1B2C3D4","status":"success"}}',
    ],
  ],

  'Direct Charge – Bank Transfer' => [
    [
      'method'=>'POST', 'path'=>'/direct/bank/charge', 'auth'=>'full',
      'desc' => 'Generate a temporary virtual bank account. Share the account details with your customer — they pay via instant bank transfer (Malawian banks USSD/app). Account expires in 1 hour. MWK only.',
      'params' => [
        ['name'=>'charge_id',               'type'=>'string','required'=>true,  'desc'=>'Your unique charge reference'],
        ['name'=>'amount',                   'type'=>'number','required'=>false, 'desc'=>'Amount in MWK (omit for permanent accounts)'],
        ['name'=>'currency',                 'type'=>'string','required'=>true,  'desc'=>'Always MWK'],
        ['name'=>'create_permanent_account', 'type'=>'string','required'=>false, 'desc'=>'"true" to create a static account number (can be reused), "false" for one-time (default)'],
      ],
      'body_example' => '{"charge_id":"PC-T265-B5C6D7E8","amount":5000,"currency":"MWK","create_permanent_account":"false"}',
      'sample_response' => '{"status":"success","data":{"payment_account_details":{"bank_name":"Centenary Bank","account_number":"2652455380","account_name":"PayChangu","account_expiration_timestamp":1736805724},"transaction":{"amount":5000,"status":"pending"}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/direct/bank/charge/{charge_id}', 'auth'=>'key',
      'desc' => 'Retrieve details of a bank transfer charge and check if the customer has completed the payment.',
      'params' => [['name'=>'charge_id','type'=>'path','required'=>true,'desc'=>'Your charge_id reference']],
      'sample_response' => '{"status":"success","data":{"transaction":{"charge_id":"PC-T265-B5C6D7E8","status":"success","amount":5000}}}',
    ],
  ],
  'Payout – Mobile Money' => [
    [
      'method'=>'GET', 'path'=>'/payout/momo/operators', 'auth'=>'key',
      'desc' => 'List mobile money operators available for sending payouts.',
      'params' => [],
      'sample_response' => '{"status":"success","data":[{"ref_id":"20be6c20-...","name":"Airtel Money","country":"Malawi"}]}',
    ],
    [
      'method'=>'POST', 'path'=>'/payout/momo', 'auth'=>'full',
      'desc' => 'Send funds directly from your PayChangu balance to a mobile money number. Recipient gets funds instantly.',
      'params' => [
        ['name'=>'mobile_money_operator_ref_id','type'=>'string','required'=>true, 'desc'=>'Operator UUID from GET /payout/momo/operators'],
        ['name'=>'mobile',    'type'=>'string','required'=>true, 'desc'=>'Recipient mobile number'],
        ['name'=>'amount',    'type'=>'number','required'=>true, 'desc'=>'Amount to send in MWK'],
        ['name'=>'charge_id', 'type'=>'string','required'=>true, 'desc'=>'Your unique payout reference'],
      ],
      'body_example' => '{"mobile_money_operator_ref_id":"20be6c20-adeb-4b5b-a7ba-0769820df4fb","mobile":"0990000000","amount":1000,"charge_id":"PC-T265-PAYOUT-001"}',
      'sample_response' => '{"status":"success","data":{"amount":1000,"charge_id":"PC-T265-PAYOUT-001","status":"pending","mobile":"+265990000000","mobile_money":{"name":"Airtel Money"}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/payout/momo/{charge_id}', 'auth'=>'key',
      'desc' => 'Get the current status and details of a mobile money payout.',
      'params' => [['name'=>'charge_id','type'=>'path','required'=>true,'desc'=>'Your payout charge_id']],
      'sample_response' => '{"status":"success","data":{"charge_id":"PC-T265-PAYOUT-001","status":"success","amount":1000}}',
    ],
  ],
  'Payout – Bank Transfer' => [
    [
      'method'=>'GET', 'path'=>'/payout/bank/supported-banks', 'auth'=>'key',
      'desc' => 'List all banks supported for bank payouts. Returns bank_uuid needed for the payout request.',
      'params' => [
        ['name'=>'currency','type'=>'query','required'=>false,'desc'=>'MWK (default) or USD'],
      ],
      'sample_response' => '{"status":"success","data":[{"uuid":"82310dd1-...","name":"National Bank of Malawi","country":"Malawi"}]}',
    ],
    [
      'method'=>'POST', 'path'=>'/payout/bank', 'auth'=>'full',
      'desc' => 'Send funds from your PayChangu balance directly to a bank account. Requires KYC approval and feature activation.',
      'params' => [
        ['name'=>'bank_uuid',           'type'=>'string','required'=>true, 'desc'=>'Bank UUID from GET /payout/bank/supported-banks'],
        ['name'=>'amount',              'type'=>'number','required'=>true, 'desc'=>'Amount to send'],
        ['name'=>'charge_id',           'type'=>'string','required'=>true, 'desc'=>'Your unique payout reference'],
        ['name'=>'bank_account_name',   'type'=>'string','required'=>true, 'desc'=>"Recipient's full account name"],
        ['name'=>'bank_account_number', 'type'=>'string','required'=>true, 'desc'=>"Recipient's account number"],
      ],
      'body_example' => '{"bank_uuid":"82310dd1-ec9b-4fe7-a32c-2f262ef08681","amount":10000,"charge_id":"PC-T265-BANK-001","bank_account_name":"Madalitso Kamwendo","bank_account_number":"1001000010"}',
      'sample_response' => '{"status":"success","data":{"transaction":{"charge_id":"PC-T265-BANK-001","status":"pending","amount":10000,"recipient_account_details":{"bank_name":"National Bank of Malawi"}}}}',
    ],
    [
      'method'=>'GET', 'path'=>'/payout/bank/payouts', 'auth'=>'key',
      'desc' => 'List all bank payouts for your PayChangu account.',
      'params' => [],
      'sample_response' => '{"status":"success","data":[{"charge_id":"PC-T265-BANK-001","status":"success","amount":10000}]}',
    ],
    [
      'method'=>'GET', 'path'=>'/payout/bank/{charge_id}', 'auth'=>'key',
      'desc' => 'Get the status and full details of a specific bank payout.',
      'params' => [['name'=>'charge_id','type'=>'path','required'=>true,'desc'=>'Your payout charge_id']],
      'sample_response' => '{"status":"success","data":{"transaction":{"charge_id":"PC-T265-BANK-001","status":"success","amount":10000}}}',
    ],
  ],
  'Statistics' => [
    [
      'method'=>'GET', 'path'=>'/stats', 'auth'=>'key',
      'desc' => 'Payment statistics summary: transaction counts, revenue totals, success rate, API performance metrics, top payment channels, and 7-day daily breakdown.',
      'params' => [],
      'sample_response' => '{"status":"success","data":{"transactions":{"total":150,"success":120,"failed":20,"pending":10,"success_rate":80},"revenue":{"total_amount":750000,"net_revenue":721500},"api":{"calls_today":45,"errors_today":2,"avg_latency_ms":280},"daily_summary":[...]}}',
    ],
  ],
];

foreach ($groups as $groupName => $endpoints):
?>
<div class="endpoint-group">
  <div class="group-title"><?= $groupName ?></div>
  <?php foreach ($endpoints as $idx => $ep):
    $eid = strtolower(preg_replace('/[^a-z0-9]/i','-',$ep['method'].$ep['path']));
    $authClass = match($ep['auth']) {
      'none'    => 'auth-none',
      'webhook' => 'auth-webhook',
      'full'    => 'auth-full',
      default   => 'auth-key',
    };
    $authLabel = match($ep['auth']) {
      'none'    => '🔓 Public',
      'webhook' => '🪝 Webhook Key',
      'full'    => '🔐 Full Key',
      default   => '🔑 API Key',
    };
  ?>
  <div class="endpoint" id="ep-<?= $eid ?>">
    <div class="endpoint-header" onclick="toggleEndpoint('<?= $eid ?>')">
      <span class="method-badge method-<?= $ep['method'] ?>"><?= $ep['method'] ?></span>
      <span class="endpoint-path"><?= htmlspecialchars($ep['path']) ?></span>
      <span class="auth-badge <?= $authClass ?>"><?= $authLabel ?></span>
      <span class="chevron" id="chev-<?= $eid ?>">›</span>
    </div>
    <div class="endpoint-body" id="body-<?= $eid ?>">
      <p class="desc"><?= htmlspecialchars($ep['desc']) ?></p>

      <?php if ($ep['params']): ?>
      <strong style="font-size:.8rem;color:#334155;display:block;margin-bottom:6px">
        <?= $ep['method']==='POST'?'Request Body':'Query Parameters' ?></strong>
      <table class="params-table">
        <thead><tr><th>Name</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($ep['params'] as $p): ?>
        <tr>
          <td><code><?= $p['name'] ?></code></td>
          <td><span style="color:#6C63FF"><?= $p['type'] ?></span></td>
          <td><?= $p['required'] ? '<span class="required">required</span>' : '<span class="optional">optional</span>' ?></td>
          <td style="color:#64748b"><?= htmlspecialchars($p['desc']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <!-- Try It -->
      <div class="try-section">
        <h5>⚡ Try It</h5>
        <?php if ($ep['method'] === 'POST'): ?>
        <label style="font-size:.78rem;color:#64748b;display:block;margin-bottom:4px">Request Body (JSON)</label>
        <textarea class="try-input" id="body-in-<?= $eid ?>" rows="8"><?= isset($ep['body_example']) ? htmlspecialchars($ep['body_example']) : "{\n  \n}" ?></textarea>
        <?php elseif (preg_match('/\{([^}]+)\}/', $ep['path'], $pathVar)): ?>
        <label style="font-size:.78rem;color:#64748b;display:block;margin-bottom:4px"><?= $pathVar[1] ?></label>
        <input type="text" class="try-input" id="pathvar-<?= $eid ?>" placeholder="Enter <?= $pathVar[1] ?>…">
        <?php else: ?>
        <label style="font-size:.78rem;color:#64748b;display:block;margin-bottom:4px">Query string (optional)</label>
        <input type="text" class="try-input" id="qs-<?= $eid ?>" placeholder="e.g. status=success&limit=5">
        <?php endif; ?>
        <button class="try-btn" onclick="tryEndpoint('<?= $eid ?>','<?= $ep['method'] ?>','<?= htmlspecialchars($ep['path'],ENT_QUOTES) ?>')">Send Request →</button>
        <div class="response-box" id="resp-<?= $eid ?>"></div>
      </div>

      <?php if (!empty($ep['sample_response'])): ?>
      <details style="margin-top:10px">
        <summary style="cursor:pointer;font-size:.8rem;color:#6C63FF;font-weight:600">📄 Sample Response</summary>
        <pre style="background:#1e293b;color:#7dd3fc;padding:12px;border-radius:8px;font-size:.76rem;margin-top:8px;overflow:auto;max-height:220px"><?= htmlspecialchars(json_encode(json_decode($ep['sample_response']),JSON_PRETTY_PRINT)) ?></pre>
      </details>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
const API_BASE = '<?= $apiBase ?>';

function toggleEndpoint(id) {
  const body = document.getElementById('body-'+id);
  const chev = document.getElementById('chev-'+id);
  body.classList.toggle('open');
  chev.classList.toggle('open');
}

async function tryEndpoint(id, method, path) {
  const apiKey = document.getElementById('globalApiKey').value.trim();
  const respEl = document.getElementById('resp-'+id);
  respEl.style.display = 'block';
  respEl.textContent   = '⏳ Sending request…';

  // Build URL
  let url = API_BASE + path;

  // Substitute path variables
  const pathVarEl = document.getElementById('pathvar-'+id);
  if (pathVarEl) {
    url = url.replace(/\{[^}]+\}/, encodeURIComponent(pathVarEl.value.trim()));
  }

  // Query string
  const qsEl = document.getElementById('qs-'+id);
  if (qsEl && qsEl.value.trim()) {
    url += (url.includes('?') ? '&' : '?') + qsEl.value.trim();
  }

  // Body
  const bodyEl = document.getElementById('body-in-'+id);
  let bodyData = null;
  if (bodyEl) {
    try { bodyData = bodyEl.value.trim(); JSON.parse(bodyData); } catch(e) {
      respEl.textContent = '❌ Invalid JSON body:\n' + e.message; return;
    }
  }

  const opts = {
    method,
    headers: { 'Content-Type':'application/json', 'X-API-Key': apiKey },
  };
  if (bodyData) opts.body = bodyData;

  try {
    const res  = await fetch(url, opts);
    const text = await res.text();
    let pretty;
    try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch { pretty = text; }
    const status = `HTTP ${res.status} ${res.statusText}`;
    respEl.textContent = `// ${status}\n// ${url}\n\n${pretty}`;
    respEl.style.color = res.ok ? '#a7f3d0' : '#fca5a5';
  } catch(err) {
    respEl.textContent = '❌ Network error:\n' + err.message;
    respEl.style.color = '#fca5a5';
  }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
