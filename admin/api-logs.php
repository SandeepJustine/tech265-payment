<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

$db     = Database::getInstance();
$limit  = 25;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Filters
$search  = trim($_GET['search'] ?? '');
$action  = trim($_GET['action'] ?? '');
$isError = $_GET['is_error'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(tx_ref LIKE ? OR endpoint LIKE ?)";
    $s = "%{$search}%";
    array_push($params, $s, $s);
}
if ($action)  { $where[] = "action = ?";    $params[] = $action; }
if ($isError !== '') { $where[] = "is_error = ?"; $params[] = (int)$isError; }
if ($dateFrom) { $where[] = "DATE(created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(created_at) <= ?"; $params[] = $dateTo; }

$whereStr = implode(' AND ', $where);

$totalRows  = (int)$db->prepare("SELECT COUNT(*) FROM api_logs WHERE {$whereStr}")
    ->execute($params) ? $db->prepare("SELECT COUNT(*) FROM api_logs WHERE {$whereStr}") : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM api_logs WHERE {$whereStr}");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT * FROM api_logs WHERE {$whereStr} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM api_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Summary stats
$todayTotal   = $db->query("SELECT COUNT(*) FROM api_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$todayErrors  = $db->query("SELECT COUNT(*) FROM api_logs WHERE DATE(created_at)=CURDATE() AND is_error=1")->fetchColumn();
$avgDuration  = $db->query("SELECT ROUND(AVG(duration_ms),1) FROM api_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();

include __DIR__ . '/layout/header.php';
?>

<div class="page-title">📡 API Logs</div>

<!-- Stats row -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#e0f2fe">📡</div>
    <div><div class="kpi-val"><?= number_format($todayTotal) ?></div><div class="kpi-label">API Calls Today</div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#fee2e2">⚠️</div>
    <div><div class="kpi-val" style="color:#dc2626"><?= number_format($todayErrors) ?></div><div class="kpi-label">Errors Today</div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#f0fdf4">⚡</div>
    <div><div class="kpi-val"><?= $avgDuration ?? '—' ?>ms</div><div class="kpi-label">Avg Duration Today</div></div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <form method="GET" class="filter-bar">
    <input type="text"  name="search"   placeholder="TX Ref or endpoint…" value="<?= htmlspecialchars($search) ?>">
    <select name="action">
      <option value="">All Actions</option>
      <?php foreach ($actions as $a): ?>
      <option value="<?= $a ?>" <?= $action === $a ? 'selected' : '' ?>><?= $a ?></option>
      <?php endforeach; ?>
    </select>
    <select name="is_error">
      <option value="">All</option>
      <option value="0" <?= $isError==='0'?'selected':'' ?>>Success Only</option>
      <option value="1" <?= $isError==='1'?'selected':'' ?>>Errors Only</option>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>">
    <button class="btn" type="submit">🔍 Filter</button>
    <a href="<?= APP_URL ?>/admin/api-logs.php" style="color:#64748b;font-size:.85rem;align-self:center">Clear</a>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <h3>Results: <?= number_format($totalRows) ?> log entries</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>TX Ref</th><th>Action</th><th>Method</th>
        <th>Endpoint</th><th>HTTP</th><th>Duration</th><th>Status</th><th>Date</th><th>Detail</th>
      </tr></thead>
      <tbody>
      <?php if ($logs): foreach ($logs as $i => $log): ?>
      <tr style="<?= $log['is_error'] ? 'background:#fff5f5' : '' ?>">
        <td style="color:#94a3b8"><?= $offset+$i+1 ?></td>
        <td><code style="font-size:.72rem"><?= htmlspecialchars($log['tx_ref'] ?? '—') ?></code></td>
        <td><span class="badge info"><?= htmlspecialchars($log['action']) ?></span></td>
        <td><code><?= $log['method'] ?></code></td>
        <td style="font-size:.75rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="<?= htmlspecialchars($log['endpoint']) ?>"><?= htmlspecialchars($log['endpoint']) ?></td>
        <td><?php
            $code = (int)$log['http_status'];
            $cls  = $code >= 400 ? 'failed' : ($code >= 200 ? 'success' : 'pending');
        ?><span class="badge <?= $cls ?>"><?= $log['http_status'] ?? '—' ?></span></td>
        <td><?= $log['duration_ms'] ? $log['duration_ms'].'ms' : '—' ?></td>
        <td><span class="badge <?= $log['is_error'] ? 'failed' : 'success' ?>"><?= $log['is_error'] ? 'ERROR' : 'OK' ?></span></td>
        <td style="font-size:.75rem;color:#64748b"><?= $log['created_at'] ?></td>
        <td>
          <button onclick='showDetail(<?= htmlspecialchars(json_encode($log), ENT_QUOTES) ?>)'
                  style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.8rem;font-weight:600">View</button>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="10" style="text-align:center;color:#94a3b8;padding:28px">No logs found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p=1;$p<=$totalPages;$p++): $q=http_build_query(array_merge($_GET,['page'=>$p])); ?>
    <a href="?<?= $q ?>" class="<?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Detail Modal -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;max-width:700px;width:95%;max-height:85vh;overflow:auto;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 id="modal-title">API Log Detail</h3>
      <button onclick="document.getElementById('modal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b">×</button>
    </div>
    <div id="modal-body"></div>
  </div>
</div>

<script>
function showDetail(log) {
  const fields = {
    'TX Ref': log.tx_ref || '—',
    'Action': log.action,
    'Endpoint': log.endpoint,
    'Method': log.method,
    'HTTP Status': log.http_status || '—',
    'Duration': log.duration_ms ? log.duration_ms + ' ms' : '—',
    'IP Address': log.ip_address || '—',
    'Date': log.created_at,
  };
  let html = '<table style="width:100%;font-size:.85rem;border-collapse:collapse">';
  for (const [k,v] of Object.entries(fields)) {
    html += `<tr><td style="padding:7px 10px;color:#64748b;font-weight:600;white-space:nowrap;border-bottom:1px solid #f1f5f9">${k}</td><td style="padding:7px 10px;border-bottom:1px solid #f1f5f9">${v}</td></tr>`;
  }
  html += '</table>';

  if (log.request_data) {
    try { html += `<div style="margin-top:14px"><strong style="font-size:.82rem">Request:</strong><pre style="background:#1e293b;color:#7dd3fc;padding:12px;border-radius:8px;font-size:.78rem;margin-top:6px;overflow:auto;max-height:180px">${JSON.stringify(JSON.parse(log.request_data),null,2)}</pre></div>`; } catch(e) {}
  }
  if (log.response_data) {
    try { html += `<div style="margin-top:14px"><strong style="font-size:.82rem">Response:</strong><pre style="background:#1e293b;color:#a7f3d0;padding:12px;border-radius:8px;font-size:.78rem;margin-top:6px;overflow:auto;max-height:180px">${JSON.stringify(JSON.parse(log.response_data),null,2)}</pre></div>`; } catch(e) {}
  }

  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('modal').style.display = 'flex';
}
document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
