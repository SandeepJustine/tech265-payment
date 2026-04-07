<?php
/**
 * Tech265 – WampServer Diagnostic Tool
 * Access: http://localhost/tech265-payment/api/diag.php
 * DELETE this file from production!
 */

// Only allow local access
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    die('Access denied. This diagnostic tool is for local use only.');
}

header('Content-Type: text/html; charset=utf-8');

$checks = [];

// 1. PHP Version
$checks['PHP Version'] = [
    'value'  => PHP_VERSION,
    'ok'     => version_compare(PHP_VERSION, '8.0.0', '>='),
    'note'   => 'Requires PHP 8.0+',
];

// 2. Extensions
foreach (['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'] as $ext) {
    $checks["Extension: {$ext}"] = [
        'value' => extension_loaded($ext) ? 'loaded' : 'MISSING',
        'ok'    => extension_loaded($ext),
        'note'  => '',
    ];
}

// 3. mod_rewrite
$checks['mod_rewrite'] = [
    'value' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) ? 'enabled' : 'unknown (check Apache)',
    'ok'    => true,
    'note'  => 'Must be enabled in httpd.conf',
];

// 4. AllowOverride
$checks['AllowOverride'] = [
    'value' => '.htaccess working',
    'ok'    => true,
    'note'  => 'If you see 500 errors, set AllowOverride All in httpd.conf for your www directory',
];

// 5. Config file
$configPath = __DIR__ . '/../config/config.php';
$checks['config/config.php'] = [
    'value' => file_exists($configPath) ? 'found' : 'MISSING',
    'ok'    => file_exists($configPath),
    'note'  => '',
];

// 6. DB connection
try {
    require_once __DIR__ . '/../config/config.php';
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->query("SELECT 1");
    $checks['MySQL Connection'] = ['value' => 'OK  (host=' . DB_HOST . ' db=' . DB_NAME . ')', 'ok' => true, 'note' => ''];
} catch (Exception $e) {
    $checks['MySQL Connection'] = ['value' => 'FAILED: ' . $e->getMessage(), 'ok' => false, 'note' => 'Check DB credentials in config/config.php'];
}

// 7. Tables
if (isset($pdo)) {
    $tables = ['transactions', 'api_logs', 'error_logs', 'webhook_logs', 'admin_users', 'direct_charges', 'payouts', 'api_keys'];
    foreach ($tables as $t) {
        try {
            $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");
            $checks["Table: {$t}"] = ['value' => 'exists', 'ok' => true, 'note' => ''];
        } catch (Exception $e) {
            $checks["Table: {$t}"] = ['value' => 'MISSING', 'ok' => false, 'note' => 'Run database.sql'];
        }
    }
}

// 8. Logs directory
if (defined('LOG_DIR')) {
    if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0755, true);
    $checks['logs/ directory'] = [
        'value' => is_writable(LOG_DIR) ? 'writable' : 'NOT writable',
        'ok'    => is_writable(LOG_DIR),
        'note'  => is_writable(LOG_DIR) ? '' : 'Run: chmod 755 logs/',
    ];
}

// 9. Path resolution
$checks['REQUEST_URI'] = ['value' => $_SERVER['REQUEST_URI'] ?? 'n/a', 'ok' => true, 'note' => ''];
$checks['SCRIPT_NAME'] = ['value' => $_SERVER['SCRIPT_NAME'] ?? 'n/a', 'ok' => true, 'note' => ''];
$checks['DOCUMENT_ROOT'] = ['value' => $_SERVER['DOCUMENT_ROOT'] ?? 'n/a', 'ok' => true, 'note' => ''];

// 10. APP_URL constant
if (defined('APP_URL')) {
    $checks['APP_URL'] = ['value' => APP_URL, 'ok' => true, 'note' => 'Update in config/config.php if wrong'];
}

$pass = count(array_filter($checks, fn($c) => $c['ok']));
$fail = count($checks) - $pass;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tech265 – Diagnostic</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:30px;margin:0}
h1{color:#7dd3fc;font-size:1.2rem;border-bottom:1px solid #334155;padding-bottom:10px}
.summary{background:#1e293b;border-radius:8px;padding:14px;margin:16px 0;font-size:.9rem}
.ok{color:#4ade80}.fail{color:#f87171}.warn{color:#fbbf24}
table{width:100%;border-collapse:collapse;font-size:.85rem;margin-top:16px}
th{padding:8px 12px;text-align:left;background:#1e293b;color:#94a3b8;border-bottom:1px solid #334155}
td{padding:8px 12px;border-bottom:1px solid #1e293b;vertical-align:top}
tr:hover td{background:#1e293b}
.tag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:bold}
.tag.ok{background:#14532d;color:#4ade80}.tag.fail{background:#450a0a;color:#f87171}
.note{color:#64748b;font-size:.78rem;margin-top:3px}
.fix-box{background:#1e293b;border-left:3px solid #f59e0b;padding:14px;border-radius:4px;margin:16px 0;font-size:.84rem;color:#fbbf24}
.fix-box code{background:#0f172a;padding:4px 8px;border-radius:4px;display:block;margin-top:6px;color:#a7f3d0}
</style>
</head>
<body>
<h1>⚡ Tech265 – WampServer Diagnostic</h1>
<div class="summary">
  <span class="ok">✅ <?= $pass ?> passed</span> &nbsp; <span class="fail">❌ <?= $fail ?> failed</span>
  <?= $fail === 0 ? ' &nbsp; <span class="ok">— All checks passed! Delete diag.php before going live.</span>' : '' ?>
</div>

<?php if ($fail > 0): ?>
<div class="fix-box">
  ⚠️ <strong>Common WampServer Fixes:</strong>
  <br><br>
  1. Enable <code>mod_rewrite</code>: Wamp tray icon → Apache → Modules → rewrite_module ✓
  <br><br>
  2. Allow .htaccess overrides in <code>C:\wamp64\bin\apache\apache2.x.x\conf\httpd.conf</code>:
  <code>&lt;Directory "${INSTALL_DIR}/www"&gt;
    AllowOverride All
&lt;/Directory&gt;</code>
  3. Import the database schema:
  <code>mysql -u root -p &lt; C:\wamp64\www\tech265-payment\database.sql</code>
  4. Update <code>config/config.php</code>: set APP_URL and DB credentials
</div>
<?php endif; ?>

<table>
  <thead><tr><th>Check</th><th>Result</th><th>Note</th></tr></thead>
  <tbody>
  <?php foreach ($checks as $label => $c): ?>
  <tr>
    <td><?= htmlspecialchars($label) ?></td>
    <td>
      <span class="tag <?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? '✓' : '✗' ?></span>
      &nbsp;<?= htmlspecialchars($c['value']) ?>
    </td>
    <td><span class="note"><?= htmlspecialchars($c['note']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p style="margin-top:24px;color:#475569;font-size:.78rem">
  ⚠️ Delete <code>diag.php</code> before deploying to production. Only accessible from 127.0.0.1.
</p>
</body>
</html>
