<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

$db     = Database::getInstance();
$limit  = 25;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Handle resolve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    Database::update('error_logs', ['resolved' => 1], ['id' => (int)$_POST['resolve_id']]);
    header('Location: ' . APP_URL . '/admin/errors.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_all'])) {
    $db->exec("UPDATE error_logs SET resolved = 1 WHERE resolved = 0");
    header('Location: ' . APP_URL . '/admin/errors.php');
    exit;
}

// Filters
$search   = trim($_GET['search'] ?? '');
$errType  = trim($_GET['error_type'] ?? '');
$resolved = $_GET['resolved'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$where  = ['1=1'];
$params = [];
if ($search)  { $where[] = "(message LIKE ? OR tx_ref LIKE ?)"; $s="%{$search}%"; array_push($params,$s,$s); }
if ($errType) { $where[] = "error_type = ?"; $params[] = $errType; }
if ($resolved !== '') { $where[] = "resolved = ?"; $params[] = (int)$resolved; }
if ($dateFrom) { $where[] = "DATE(created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(created_at) <= ?"; $params[] = $dateTo; }
$whereStr = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM error_logs WHERE {$whereStr}");
$cntStmt->execute($params);
$totalRows  = (int)$cntStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT * FROM error_logs WHERE {$whereStr} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);
$errors = $stmt->fetchAll();

$errTypes  = $db->query("SELECT DISTINCT error_type FROM error_logs ORDER BY error_type")->fetchAll(PDO::FETCH_COLUMN);
$unresolvedCount = $db->query("SELECT COUNT(*) FROM error_logs WHERE resolved=0")->fetchColumn();

include __DIR__ . '/layout/header.php';
?>

<div class="page-title">🔴 Error Logs</div>

<?php if ($unresolvedCount > 0): ?>
<div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center">
  <span style="color:#991b1b;font-weight:600">⚠️ <?= number_format($unresolvedCount) ?> unresolved error(s) require attention.</span>
  <form method="POST" style="display:inline">
    <button name="resolve_all" value="1" class="btn" style="background:#dc2626;padding:7px 16px;font-size:.82rem" onclick="return confirm('Mark all errors as resolved?')">✔ Resolve All</button>
  </form>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search message, tx_ref…" value="<?= htmlspecialchars($search) ?>">
    <select name="error_type">
      <option value="">All Error Types</option>
      <?php foreach ($errTypes as $et): ?>
      <option value="<?= $et ?>" <?= $errType===$et?'selected':'' ?>><?= $et ?></option>
      <?php endforeach; ?>
    </select>
    <select name="resolved">
      <option value="">All</option>
      <option value="0" <?= $resolved==='0'?'selected':'' ?>>Unresolved</option>
      <option value="1" <?= $resolved==='1'?'selected':'' ?>>Resolved</option>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>">
    <button class="btn" type="submit">🔍 Filter</button>
    <a href="<?= APP_URL ?>/admin/errors.php" style="color:#64748b;font-size:.85rem;align-self:center">Clear</a>
  </form>
</div>

<div class="card">
  <h3 style="margin-bottom:14px">Results: <?= number_format($totalRows) ?> errors</h3>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Type</th><th>Code</th><th>Message</th>
        <th>TX Ref</th><th>File:Line</th><th>Status</th><th>Date</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if ($errors): foreach ($errors as $i => $err): ?>
      <tr style="<?= !$err['resolved'] ? 'background:#fff5f5' : '' ?>">
        <td style="color:#94a3b8"><?= $offset+$i+1 ?></td>
        <td><span class="badge error" style="white-space:nowrap"><?= htmlspecialchars($err['error_type']) ?></span></td>
        <td style="font-size:.8rem"><?= htmlspecialchars($err['error_code'] ?? '—') ?></td>
        <td style="font-size:.82rem;max-width:260px"><?= htmlspecialchars(mb_strimwidth($err['message'], 0, 90, '…')) ?></td>
        <td><code style="font-size:.72rem"><?= htmlspecialchars($err['tx_ref'] ?? '—') ?></code></td>
        <td style="font-size:.75rem;color:#64748b">
          <?= $err['file'] ? basename($err['file']) . ':' . $err['line_number'] : '—' ?>
        </td>
        <td><span class="badge <?= $err['resolved'] ? 'success' : 'failed' ?>"><?= $err['resolved'] ? 'RESOLVED' : 'OPEN' ?></span></td>
        <td style="font-size:.75rem;color:#64748b"><?= $err['created_at'] ?></td>
        <td style="display:flex;gap:6px;align-items:center">
          <button onclick='showDetail(<?= htmlspecialchars(json_encode($err),ENT_QUOTES) ?>)'
                  style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.8rem;font-weight:600">Detail</button>
          <?php if (!$err['resolved']): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="resolve_id" value="<?= $err['id'] ?>">
            <button type="submit" style="background:none;border:none;color:#16a34a;cursor:pointer;font-size:.8rem;font-weight:600">✔ Resolve</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:28px">No errors found. 🎉</td></tr>
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
  <div style="background:#fff;border-radius:16px;max-width:720px;width:95%;max-height:88vh;overflow:auto;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3>Error Detail</h3>
      <button onclick="document.getElementById('modal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b">×</button>
    </div>
    <div id="modal-body"></div>
  </div>
</div>

<script>
function showDetail(err) {
  const fields = {
    'Error Type': err.error_type, 'Error Code': err.error_code||'—',
    'TX Ref': err.tx_ref||'—', 'IP': err.ip_address||'—',
    'File': err.file||'—', 'Line': err.line_number||'—',
    'Date': err.created_at, 'Status': err.resolved ? 'Resolved' : 'Open'
  };
  let html = '<table style="width:100%;font-size:.85rem;border-collapse:collapse">';
  for (const [k,v] of Object.entries(fields))
    html += `<tr><td style="padding:7px 10px;color:#64748b;font-weight:600;white-space:nowrap;border-bottom:1px solid #f1f5f9">${k}</td><td style="padding:7px 10px;border-bottom:1px solid #f1f5f9">${v}</td></tr>`;
  html += '</table>';
  html += `<div style="margin-top:14px"><strong style="font-size:.82rem">Message:</strong><div style="background:#fef2f2;color:#991b1b;padding:12px;border-radius:8px;margin-top:6px;font-size:.85rem">${err.message}</div></div>`;
  if (err.stack_trace)
    html += `<div style="margin-top:14px"><strong style="font-size:.82rem">Stack Trace:</strong><pre style="background:#1e293b;color:#fca5a5;padding:12px;border-radius:8px;font-size:.72rem;margin-top:6px;overflow:auto;max-height:220px">${err.stack_trace}</pre></div>`;
  if (err.context) {
    try { html += `<div style="margin-top:14px"><strong style="font-size:.82rem">Context:</strong><pre style="background:#1e293b;color:#7dd3fc;padding:12px;border-radius:8px;font-size:.76rem;margin-top:6px;overflow:auto;max-height:160px">${JSON.stringify(JSON.parse(err.context),null,2)}</pre></div>`; } catch(e) {}
  }
  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('modal').style.display = 'flex';
}
document.getElementById('modal').addEventListener('click',e=>{ if(e.target===document.getElementById('modal')) document.getElementById('modal').style.display='none'; });
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
