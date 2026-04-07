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
        <td><span class="badge <?= $tx['status'] ?>"><?= strtoupper($tx['status']) ?></span></td>
        <td style="font-size:.78rem;color:#64748b"><?= $tx['created_at'] ?></td>
        <td>
          <a href="<?= APP_URL ?>/public/verify.php?tx_ref=<?= urlencode($tx['tx_ref']) ?>"
             target="_blank"
             style="font-size:.78rem;color:var(--primary);font-weight:600">Verify</a>
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

<?php include __DIR__ . '/layout/footer.php'; ?>
