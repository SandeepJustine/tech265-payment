<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

$db     = Database::getInstance();
$limit  = 25;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$processed = $_GET['processed'] ?? '';
$dateFrom  = $_GET['date_from'] ?? '';
$dateTo    = $_GET['date_to']   ?? '';
$search    = trim($_GET['search'] ?? '');

$where  = ['1=1'];
$params = [];
if ($search)    { $where[] = "tx_ref LIKE ?"; $params[] = "%{$search}%"; }
if ($processed !== '') { $where[] = "processed = ?"; $params[] = (int)$processed; }
if ($dateFrom)  { $where[] = "DATE(created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)    { $where[] = "DATE(created_at) <= ?"; $params[] = $dateTo; }
$whereStr = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM webhook_logs WHERE {$whereStr}");
$cntStmt->execute($params);
$totalRows  = (int)$cntStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT * FROM webhook_logs WHERE {$whereStr} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);
$webhooks = $stmt->fetchAll();

$totalWebhooks     = $db->query("SELECT COUNT(*) FROM webhook_logs")->fetchColumn();
$processedWebhooks = $db->query("SELECT COUNT(*) FROM webhook_logs WHERE processed=1")->fetchColumn();
$pendingWebhooks   = $db->query("SELECT COUNT(*) FROM webhook_logs WHERE processed=0")->fetchColumn();

include __DIR__ . '/layout/header.php';
?>

<div class="page-title">🪝 Webhook Logs</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#f5f3ff">🪝</div>
    <div><div class="kpi-val"><?= number_format($totalWebhooks) ?></div><div class="kpi-label">Total Webhooks</div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#dcfce7">✅</div>
    <div><div class="kpi-val" style="color:#16a34a"><?= number_format($processedWebhooks) ?></div><div class="kpi-label">Processed</div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon" style="background:#fef9c3">⏳</div>
    <div><div class="kpi-val" style="color:#b45309"><?= number_format($pendingWebhooks) ?></div><div class="kpi-label">Unprocessed</div></div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="TX Ref…" value="<?= htmlspecialchars($search) ?>">
    <select name="processed">
      <option value="">All</option>
      <option value="1" <?= $processed==='1'?'selected':'' ?>>Processed</option>
      <option value="0" <?= $processed==='0'?'selected':'' ?>>Unprocessed</option>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>">
    <button class="btn" type="submit">🔍 Filter</button>
    <a href="<?= APP_URL ?>/admin/webhooks.php" style="color:#64748b;font-size:.85rem;align-self:center">Clear</a>
  </form>
</div>

<div class="card">
  <h3 style="margin-bottom:14px">Results: <?= number_format($totalRows) ?> webhooks</h3>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>TX Ref</th><th>Event Type</th><th>IP Address</th>
        <th>Processed</th><th>Date</th><th>Payload</th>
      </tr></thead>
      <tbody>
      <?php if ($webhooks): foreach ($webhooks as $i => $wh): ?>
      <tr>
        <td style="color:#94a3b8"><?= $offset+$i+1 ?></td>
        <td><code style="font-size:.73rem"><?= htmlspecialchars($wh['tx_ref'] ?? '—') ?></code></td>
        <td><span class="badge info"><?= htmlspecialchars($wh['event_type'] ?? '—') ?></span></td>
        <td style="font-size:.82rem"><?= htmlspecialchars($wh['ip_address'] ?? '—') ?></td>
        <td><span class="badge <?= $wh['processed'] ? 'success' : 'pending' ?>"><?= $wh['processed'] ? 'YES' : 'NO' ?></span></td>
        <td style="font-size:.75rem;color:#64748b"><?= $wh['created_at'] ?></td>
        <td>
          <?php if ($wh['payload']): ?>
          <button onclick='showPayload(<?= htmlspecialchars($wh["payload"],ENT_QUOTES) ?>)'
                  style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.8rem;font-weight:600">View JSON</button>
          <?php else: ?>
          <span style="color:#94a3b8;font-size:.8rem">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:28px">No webhooks found.</td></tr>
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

<!-- Payload Modal -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;max-width:680px;width:95%;max-height:85vh;overflow:auto;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3>Webhook Payload</h3>
      <button onclick="document.getElementById('modal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer">×</button>
    </div>
    <pre id="payload-pre" style="background:#1e293b;color:#a7f3d0;padding:16px;border-radius:10px;font-size:.8rem;overflow:auto;max-height:500px"></pre>
  </div>
</div>

<script>
function showPayload(raw) {
  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    document.getElementById('payload-pre').textContent = JSON.stringify(parsed, null, 2);
  } catch(e) {
    document.getElementById('payload-pre').textContent = raw;
  }
  document.getElementById('modal').style.display = 'flex';
}
document.getElementById('modal').addEventListener('click',e=>{ if(e.target===document.getElementById('modal')) document.getElementById('modal').style.display='none'; });
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
