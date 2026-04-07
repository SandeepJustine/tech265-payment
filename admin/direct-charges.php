<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

$db     = Database::getInstance();
$limit  = 20;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$type     = $_GET['type']     ?? '';
$status   = $_GET['status']   ?? '';
$search   = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$where = ['1=1'];
$params = [];
if ($type)     { $where[] = 'charge_type = ?';       $params[] = $type; }
if ($status)   { $where[] = 'status = ?';             $params[] = $status; }
if ($search)   { $where[] = '(charge_id LIKE ? OR mobile LIKE ? OR email LIKE ?)';
                 $s = "%{$search}%"; array_push($params, $s, $s, $s); }
if ($dateFrom) { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo; }
$ws = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM direct_charges WHERE {$ws}");
$cntStmt->execute($params);
$totalRows  = (int)$cntStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT * FROM direct_charges WHERE {$ws} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);
$charges = $stmt->fetchAll();

// Summary stats
$totalMomo  = $db->query("SELECT COUNT(*) FROM direct_charges WHERE charge_type='momo'")->fetchColumn();
$totalBank  = $db->query("SELECT COUNT(*) FROM direct_charges WHERE charge_type='bank_transfer'")->fetchColumn();
$successCnt = $db->query("SELECT COUNT(*) FROM direct_charges WHERE status IN('success','successful')")->fetchColumn();
$pendingCnt = $db->query("SELECT COUNT(*) FROM direct_charges WHERE status='pending'")->fetchColumn();
$totalAmt   = $db->query("SELECT COALESCE(SUM(amount),0) FROM direct_charges WHERE status IN('success','successful')")->fetchColumn();

include __DIR__ . '/layout/header.php';
?>

<div class="page-title">📱 Direct Charges</div>

<div class="kpi-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="kpi-card"><div class="kpi-icon" style="background:#e0f2fe">📱</div>
    <div><div class="kpi-val"><?= number_format($totalMomo) ?></div><div class="kpi-label">MoMo Charges</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#f5f3ff">🏦</div>
    <div><div class="kpi-val"><?= number_format($totalBank) ?></div><div class="kpi-label">Bank Transfer</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#dcfce7">✅</div>
    <div><div class="kpi-val" style="color:#16a34a"><?= number_format($successCnt) ?></div><div class="kpi-label">Successful</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#fef9c3">⏳</div>
    <div><div class="kpi-val" style="color:#b45309"><?= number_format($pendingCnt) ?></div><div class="kpi-label">Pending</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:#e0f2fe">💰</div>
    <div><div class="kpi-val" style="color:#0369a1">MWK <?= number_format($totalAmt, 2) ?></div><div class="kpi-label">Collected</div></div></div>
</div>

<div class="card" style="margin-bottom:20px">
  <form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Charge ID, mobile, email…" value="<?= htmlspecialchars($search) ?>">
    <select name="type">
      <option value="">All Types</option>
      <option value="momo" <?= $type==='momo'?'selected':'' ?>>Mobile Money</option>
      <option value="bank_transfer" <?= $type==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      <?php foreach (['pending','success','successful','failed'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>">
    <button class="btn" type="submit">🔍 Filter</button>
    <a href="<?= APP_URL ?>/admin/direct-charges.php" style="color:#64748b;font-size:.85rem;align-self:center">Clear</a>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <h3>Results: <?= number_format($totalRows) ?> charges</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Charge ID</th><th>Type</th><th>Mobile / Account</th>
        <th>Amount</th><th>Operator / Bank</th><th>Status</th><th>Date</th><th>Detail</th>
      </tr></thead>
      <tbody>
      <?php if ($charges): foreach ($charges as $i => $c): ?>
      <tr>
        <td style="color:#94a3b8"><?= $offset+$i+1 ?></td>
        <td><code style="font-size:.75rem"><?= htmlspecialchars($c['charge_id']) ?></code></td>
        <td>
          <?php if ($c['charge_type'] === 'momo'): ?>
            <span class="badge info">📱 MoMo</span>
          <?php else: ?>
            <span class="badge" style="background:#f5f3ff;color:#5b21b6">🏦 Bank</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.85rem">
          <?= htmlspecialchars($c['mobile'] ?? $c['account_number'] ?? '—') ?>
          <?php if ($c['charge_type'] === 'bank_transfer' && $c['account_number']): ?>
          <br><small style="color:#94a3b8"><?= htmlspecialchars($c['bank_name'] ?? '') ?></small>
          <?php endif; ?>
        </td>
        <td><strong>MWK <?= number_format($c['amount'] ?? 0, 2) ?></strong></td>
        <td style="font-size:.83rem"><?= htmlspecialchars($c['operator_name'] ?? $c['bank_name'] ?? '—') ?></td>
        <td><span class="badge <?= in_array($c['status'],['success','successful'])?'success':($c['status']==='pending'?'pending':'failed') ?>">
          <?= strtoupper($c['status']) ?></span></td>
        <td style="font-size:.75rem;color:#64748b"><?= $c['created_at'] ?></td>
        <td>
          <button onclick='showDetail(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'
                  style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.8rem;font-weight:600">View</button>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:28px">No direct charges found.</td></tr>
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
  <div style="background:#fff;border-radius:16px;max-width:700px;width:95%;max-height:88vh;overflow:auto;padding:28px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3>Charge Detail</h3>
      <button onclick="document.getElementById('modal').style.display='none'"
              style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b">×</button>
    </div>
    <div id="modal-body"></div>
  </div>
</div>

<script>
function showDetail(c) {
  const rows = {
    'Charge ID': c.charge_id, 'Type': c.charge_type,
    'Status': c.status, 'Amount': 'MWK ' + parseFloat(c.amount||0).toFixed(2),
    'Currency': c.currency, 'Mobile': c.mobile||'—',
    'Operator': c.operator_name||'—', 'Bank': c.bank_name||'—',
    'Account No.': c.account_number||'—', 'Account Name': c.account_name||'—',
    'Expires At': c.account_expires_at||'—',
    'Ref ID': c.ref_id||'—', 'Trans ID': c.trans_id||'—',
    'Trace ID': c.trace_id||'—', 'Mode': c.mode||'—',
    'Created': c.created_at, 'Verified': c.verified_at||'—',
    'IP': c.ip_address||'—',
  };
  let html = '<table style="width:100%;font-size:.85rem;border-collapse:collapse">';
  for (const [k,v] of Object.entries(rows))
    html += `<tr><td style="padding:7px 10px;color:#64748b;font-weight:600;white-space:nowrap;border-bottom:1px solid #f1f5f9">${k}</td><td style="padding:7px 10px;border-bottom:1px solid #f1f5f9;word-break:break-all">${v}</td></tr>`;
  html += '</table>';
  if (c.raw_response) {
    try { html += `<div style="margin-top:14px"><strong style="font-size:.82rem">Raw Response:</strong><pre style="background:#1e293b;color:#a7f3d0;padding:12px;border-radius:8px;font-size:.76rem;margin-top:6px;overflow:auto;max-height:200px">${JSON.stringify(JSON.parse(c.raw_response),null,2)}</pre></div>`; } catch(e){}
  }
  document.getElementById('modal-body').innerHTML = html;
  document.getElementById('modal').style.display = 'flex';
}
document.getElementById('modal').addEventListener('click',e=>{ if(e.target===document.getElementById('modal')) document.getElementById('modal').style.display='none'; });
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
