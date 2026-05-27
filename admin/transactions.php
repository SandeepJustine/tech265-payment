<?php
require_once __DIR__ . '/../src/AdminAuth.php';
AdminAuth::requireLogin();
$user = AdminAuth::currentUser();

$db     = Database::getInstance();
$limit  = 20;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Filters
$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status']   ?? '';
$currency = $_GET['currency'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(tx_ref LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s);
}
if ($status)   { $where[] = "status = ?";    $params[] = $status; }
if ($currency) { $where[] = "currency = ?";  $params[] = $currency; }
if ($dateFrom) { $where[] = "DATE(created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(created_at) <= ?"; $params[] = $dateTo; }

$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM transactions WHERE {$whereStr}");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

$stmt = $db->prepare("SELECT * FROM transactions WHERE {$whereStr} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

include __DIR__ . '/layout/header.php';
?>

<div class="page-title">💳 Transactions</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search ref, email, name…" value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="">All Statuses</option>
      <?php foreach (['pending','success','failed','cancelled','verified'] as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="currency">
      <option value="">All Currencies</option>
      <option value="MWK" <?= $currency==='MWK'?'selected':'' ?>>MWK</option>
      <option value="USD" <?= $currency==='USD'?'selected':'' ?>>USD</option>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>"   title="To date">
    <button class="btn" type="submit">🔍 Filter</button>
    <a href="<?= APP_URL ?>/admin/transactions.php" style="color:#64748b;font-size:.85rem;align-self:center">Clear</a>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <h3>Results: <?= number_format($totalRows) ?> transactions</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>TX Ref</th><th>Customer</th><th>Email</th>
        <th>Amount</th><th>Channel</th><th>Status</th><th>Date</th><th>Action</th>
      </tr></thead>
      <tbody>
      <?php if ($transactions): foreach ($transactions as $i => $tx): ?>
      <tr>
        <td style="color:#94a3b8"><?= $offset + $i + 1 ?></td>
        <td><code style="font-size:.75rem;word-break:break-all"><?= htmlspecialchars($tx['tx_ref']) ?></code></td>
        <td><?= htmlspecialchars($tx['first_name'] . ' ' . $tx['last_name']) ?></td>
        <td><?= htmlspecialchars($tx['email']) ?></td>
        <td><strong><?= $tx['currency'] ?> <?= number_format($tx['amount'],2) ?></strong>
            <br><small style="color:#94a3b8">Charges: <?= number_format($tx['charges'],2) ?></small></td>
        <td><?= $tx['payment_channel'] ? htmlspecialchars($tx['payment_channel']) : '—' ?></td>
        <td><span class="badge <?= $tx['status'] ?>" id="badge-<?= $offset + $i ?>"><?= strtoupper($tx['status']) ?></span></td>
        <td style="font-size:.78rem;color:#64748b"><?= $tx['created_at'] ?></td>
        <td>
          <button class="btn-verify" data-txref="<?= htmlspecialchars($tx['tx_ref']) ?>" data-idx="<?= $offset + $i ?>">Verify</button>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:28px">No transactions found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++):
      $q = http_build_query(array_merge($_GET, ['page' => $p]));
    ?>
    <a href="?<?= $q ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Verify Modal -->
<div id="verify-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;max-width:520px;width:90%;max-height:80vh;overflow-y:auto;position:relative">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 id="verify-modal-title" style="font-size:1rem;color:var(--dark)">Verify Transaction</h3>
      <button onclick="document.getElementById('verify-modal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b">×</button>
    </div>
    <div id="verify-modal-body"></div>
  </div>
</div>

<style>
.btn-verify{font-size:.78rem;color:var(--primary);font-weight:600;background:none;border:none;cursor:pointer;padding:2px 6px;border-radius:4px;transition:background .15s;}
.btn-verify:hover{background:rgba(0,153,255,.1);}
.btn-verify:disabled{color:#94a3b8;cursor:not-allowed;}
.verify-row{display:flex;gap:8px;margin-bottom:8px;font-size:.85rem;}
.verify-label{color:#64748b;min-width:120px;flex-shrink:0;}
.verify-value{color:#0f172a;font-weight:500;word-break:break-all;}
</style>

<script>
(function(){
  var apiKey = <?= json_encode(defined('TECH265_API_KEY') ? TECH265_API_KEY : '') ?>;
  var baseUrl = <?= json_encode(APP_URL . '/api/v1') ?>;

  document.querySelectorAll('.btn-verify').forEach(function(btn){
    btn.addEventListener('click', function(){
      var txRef = this.dataset.txref;
      var idx   = this.dataset.idx;
      var self  = this;
      self.disabled = true;
      self.textContent = '…';

      var modal = document.getElementById('verify-modal');
      var body  = document.getElementById('verify-modal-body');
      var title = document.getElementById('verify-modal-title');
      body.innerHTML = '<p style="color:#64748b;font-size:.88rem">Verifying with PayChangu…</p>';
      modal.style.display = 'flex';

      fetch(baseUrl + '/payments/verify/' + encodeURIComponent(txRef), {
        headers: { 'X-API-Key': apiKey }
      })
      .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, json:j}; }); })
      .then(function(res){
        var j = res.json;
        if(res.ok && j.status === 'success'){
          var d = j.data || {};
          var auth = d.authorization || {};
          var newStatus = (d.status || 'unknown').toUpperCase();

          // Update badge in the table
          var badge = document.getElementById('badge-' + idx);
          if(badge){
            badge.className = 'badge ' + (d.status || 'unknown').toLowerCase();
            badge.textContent = newStatus;
          }

          title.textContent = '✅ Verified';
          body.innerHTML =
            row('TX Ref',    txRef) +
            row('Status',    '<span class="badge ' + (d.status||'').toLowerCase() + '">' + newStatus + '</span>') +
            row('Amount',    (d.currency||'') + ' ' + fmt(d.amount)) +
            row('Charges',   (d.currency||'') + ' ' + fmt(d.charges)) +
            row('Customer',  (d.first_name||'') + ' ' + (d.last_name||'')) +
            row('Email',     d.email||'—') +
            row('Channel',   auth.channel||d.payment_channel||'—') +
            (auth.mobile_number ? row('Mobile', auth.mobile_number) : '') +
            (auth.card_number   ? row('Card',   auth.card_number + ' (' + (auth.brand||'') + ')') : '') +
            row('Verified At', d.verified_at||'—');
        } else {
          title.textContent = '⚠️ Verification Result';
          var msg = (j.message || j.error || 'Verification failed.');
          body.innerHTML = '<p style="color:#ef4444;font-size:.88rem">' + esc(msg) + '</p>'
            + '<p style="margin-top:10px;font-size:.78rem;color:#94a3b8">TX Ref: ' + esc(txRef) + '</p>';
        }
      })
      .catch(function(e){
        title.textContent = '❌ Error';
        body.innerHTML = '<p style="color:#ef4444;font-size:.88rem">Network error: ' + esc(e.message) + '</p>';
      })
      .finally(function(){
        self.disabled = false;
        self.textContent = 'Verify';
      });
    });
  });

  document.getElementById('verify-modal').addEventListener('click', function(e){
    if(e.target === this) this.style.display = 'none';
  });

  function row(label, value){
    return '<div class="verify-row"><span class="verify-label">' + esc(label) + '</span><span class="verify-value">' + value + '</span></div>';
  }
  function fmt(v){ return v != null ? Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '—'; }
  function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }
})();
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
