<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

// ── Stats ──────────────────────────────────────────────────────
$db = Database::getInstance();

$totalTx    = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$successTx  = $db->query("SELECT COUNT(*) FROM transactions WHERE status='success'")->fetchColumn();
$failedTx   = $db->query("SELECT COUNT(*) FROM transactions WHERE status IN ('failed','cancelled')")->fetchColumn();
$pendingTx  = $db->query("SELECT COUNT(*) FROM transactions WHERE status='pending'")->fetchColumn();
$totalRev   = $db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='success'")->fetchColumn();
$totalErr   = $db->query("SELECT COUNT(*) FROM error_logs WHERE resolved=0")->fetchColumn();
$apiCalls   = $db->query("SELECT COUNT(*) FROM api_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$webhooks   = $db->query("SELECT COUNT(*) FROM webhook_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();

// Last 7 days chart data
$chartData = $db->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS total,
            SUM(status='success') AS success,
            SUM(status IN ('failed','cancelled')) AS failed
     FROM transactions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at) ORDER BY day"
)->fetchAll();

// Recent transactions
$recentTx = $db->query(
    "SELECT tx_ref,first_name,last_name,email,amount,currency,status,created_at
     FROM transactions ORDER BY created_at DESC LIMIT 10"
)->fetchAll();

// Recent errors
$recentErr = $db->query(
    "SELECT error_type,message,created_at FROM error_logs ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

include __DIR__ . '/layout/header.php';
?>

<div class="page-title">Dashboard Overview</div>

<!-- ── KPI Cards ─────────────────────────────────────────────── -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#ede9fe">💳</div>
    <div>
      <div class="kpi-val"><?= number_format($totalTx) ?></div>
      <div class="kpi-label">Total Transactions</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#dcfce7">✅</div>
    <div>
      <div class="kpi-val" style="color:#16a34a"><?= number_format($successTx) ?></div>
      <div class="kpi-label">Successful</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#fef9c3">⏳</div>
    <div>
      <div class="kpi-val" style="color:#b45309"><?= number_format($pendingTx) ?></div>
      <div class="kpi-label">Pending</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#fee2e2">❌</div>
    <div>
      <div class="kpi-val" style="color:#dc2626"><?= number_format($failedTx) ?></div>
      <div class="kpi-label">Failed / Cancelled</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#e0f2fe">💰</div>
    <div>
      <div class="kpi-val" style="color:#0369a1">MWK <?= number_format($totalRev, 2) ?></div>
      <div class="kpi-label">Total Revenue</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#fce7f3">🔴</div>
    <div>
      <div class="kpi-val" style="color:#9d174d"><?= number_format($totalErr) ?></div>
      <div class="kpi-label">Unresolved Errors</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#f0fdf4">📡</div>
    <div>
      <div class="kpi-val"><?= number_format($apiCalls) ?></div>
      <div class="kpi-label">API Calls Today</div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#f5f3ff">🪝</div>
    <div>
      <div class="kpi-val"><?= number_format($webhooks) ?></div>
      <div class="kpi-label">Webhooks Today</div>
    </div>
  </div>
</div>

<!-- ── Chart + Recent Errors ──────────────────────────────────── -->
<div class="two-col">
  <div class="card">
    <h3>📊 Transactions – Last 7 Days</h3>
    <canvas id="txChart" height="220"></canvas>
  </div>
  <div class="card">
    <h3>🔴 Recent Errors</h3>
    <?php if ($recentErr): foreach ($recentErr as $err): ?>
    <div class="err-row">
      <div class="err-type"><?= htmlspecialchars($err['error_type']) ?></div>
      <div class="err-msg"><?= htmlspecialchars(mb_strimwidth($err['message'], 0, 80, '…')) ?></div>
      <div class="err-time"><?= $err['created_at'] ?></div>
    </div>
    <?php endforeach; else: ?>
    <p style="color:#16a34a;font-size:.9rem;padding:16px 0">✅ No unresolved errors.</p>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/admin/errors.php" class="view-all">View All Errors →</a>
  </div>
</div>

<!-- ── Recent Transactions ───────────────────────────────────── -->
<div class="card" style="margin-top:24px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h3>💳 Recent Transactions</h3>
    <a href="<?= APP_URL ?>/admin/transactions.php" class="view-all">View All →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>TX Ref</th><th>Customer</th><th>Email</th>
        <th>Amount</th><th>Status</th><th>Date</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recentTx as $tx): ?>
      <tr>
        <td><code style="font-size:.78rem"><?= htmlspecialchars($tx['tx_ref']) ?></code></td>
        <td><?= htmlspecialchars($tx['first_name'] . ' ' . $tx['last_name']) ?></td>
        <td><?= htmlspecialchars($tx['email']) ?></td>
        <td><?= $tx['currency'] ?> <?= number_format($tx['amount'], 2) ?></td>
        <td><span class="badge <?= $tx['status'] ?>"><?= strtoupper($tx['status']) ?></span></td>
        <td style="font-size:.8rem;color:#64748b"><?= $tx['created_at'] ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const raw = <?= json_encode($chartData) ?>;
const labels = raw.map(r => r.day);
new Chart(document.getElementById('txChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      { label: 'Successful', data: raw.map(r=>r.success), backgroundColor:'#22c55e' },
      { label: 'Failed',     data: raw.map(r=>r.failed),  backgroundColor:'#ef4444' },
    ]
  },
  options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } } }
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
