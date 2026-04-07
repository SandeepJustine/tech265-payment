<?php
/**
 * Tech265 - Payment Result Page & Checkout Demo
 */
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

$txRef    = $_GET['tx_ref']    ?? null;
$verified = ($_GET['verified'] ?? '0') === '1';
$txData   = null;

if ($txRef) {
    try {
        $txData = Database::query(
            "SELECT * FROM transactions WHERE tx_ref = ? LIMIT 1",
            [$txRef]
        )->fetch();
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tech265 – Payment Gateway Demo</title>
<style>
  :root { --primary:#6C63FF; --success:#22c55e; --danger:#ef4444; --dark:#1e1b4b; --light:#f8fafc; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI',sans-serif; background:var(--light); color:#334155; min-height:100vh; }
  .header { background:var(--dark); color:#fff; padding:16px 32px; display:flex; align-items:center; gap:12px; }
  .header h1 { font-size:1.3rem; font-weight:700; }
  .header span { background:var(--primary); color:#fff; font-size:.7rem; padding:3px 8px; border-radius:20px; font-weight:600; }
  .container { max-width:860px; margin:40px auto; padding:0 20px; }
  .card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.07); padding:36px; margin-bottom:28px; }
  h2 { font-size:1.1rem; font-weight:700; margin-bottom:20px; color:var(--dark); border-bottom:2px solid #e2e8f0; padding-bottom:10px; }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
  .form-row.single { grid-template-columns:1fr; }
  label { display:block; font-size:.82rem; font-weight:600; color:#475569; margin-bottom:5px; }
  input,select,textarea { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.9rem; transition:border .2s; }
  input:focus,select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(108,99,255,.12); }
  .btn { background:var(--primary); color:#fff; border:none; padding:12px 28px; border-radius:10px; font-size:.95rem; font-weight:600; cursor:pointer; transition:opacity .2s; width:100%; margin-top:8px; }
  .btn:hover { opacity:.88; }
  .result { text-align:center; padding:24px; border-radius:12px; }
  .result.success { background:#f0fdf4; border:1.5px solid #bbf7d0; }
  .result.failed  { background:#fef2f2; border:1.5px solid #fecaca; }
  .icon { font-size:3rem; margin-bottom:10px; }
  .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:600; }
  .badge.success { background:#dcfce7; color:#166534; }
  .badge.failed  { background:#fee2e2; color:#991b1b; }
  .badge.pending { background:#fef9c3; color:#713f12; }
  .detail-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:.88rem; }
  .detail-row:last-child { border:none; }
  #loading { display:none; text-align:center; padding:20px; color:var(--primary); font-weight:600; }
  #apiResponse { background:#1e293b; color:#7dd3fc; padding:16px; border-radius:10px; font-size:.8rem; white-space:pre-wrap; max-height:220px; overflow:auto; margin-top:16px; display:none; }
</style>
</head>
<body>
<div class="header">
  <div>
    <h1>⚡ Tech265 Payment Gateway</h1>
  </div>
  <span>PayChangu Integration</span>
  <a href="<?= APP_URL ?>/admin/" style="margin-left:auto;color:#a5b4fc;font-size:.85rem;text-decoration:none;">🛠 Admin Dashboard →</a>
</div>

<div class="container">

<?php if ($txData): ?>
  <!-- ── Result Page ── -->
  <div class="card">
    <div class="result <?= $verified ? 'success' : 'failed' ?>">
      <div class="icon"><?= $verified ? '✅' : '❌' ?></div>
      <h2 style="border:none;margin:0 0 8px"><?= $verified ? 'Payment Successful!' : 'Payment Failed / Cancelled' ?></h2>
      <p style="color:#64748b;margin-bottom:16px">
        <?= $verified ? 'Your payment has been verified and recorded.' : 'The payment could not be completed. Please try again.' ?>
      </p>
      <span class="badge <?= $txData['status'] ?>"><?= strtoupper($txData['status']) ?></span>
    </div>
    <div style="margin-top:24px">
      <?php $rows = [
        'Transaction Ref' => $txData['tx_ref'],
        'Customer'        => $txData['first_name'] . ' ' . $txData['last_name'],
        'Email'           => $txData['email'],
        'Amount'          => number_format($txData['amount'], 2) . ' ' . $txData['currency'],
        'Charges'         => number_format($txData['charges'], 2) . ' ' . $txData['currency'],
        'Channel'         => $txData['payment_channel'] ?? '—',
        'Date'            => $txData['created_at'],
      ]; foreach ($rows as $label => $val): ?>
      <div class="detail-row"><span style="color:#64748b"><?= $label ?></span><strong><?= htmlspecialchars($val) ?></strong></div>
      <?php endforeach; ?>
    </div>
    <a href="<?= APP_URL ?>/public/result.php" style="display:block;text-align:center;margin-top:20px;color:var(--primary);font-weight:600;">← Make Another Payment</a>
  </div>

<?php else: ?>
  <!-- ── Checkout Demo Form ── -->
  <div class="card">
    <h2>💳 Initiate Payment</h2>
    <div class="form-row">
      <div><label>First Name</label><input id="firstName" type="text" placeholder="John"></div>
      <div><label>Last Name</label><input id="lastName" type="text" placeholder="Banda"></div>
    </div>
    <div class="form-row">
      <div><label>Email</label><input id="email" type="email" placeholder="[email protected]"></div>
      <div><label>Phone (optional)</label><input id="phone" type="text" placeholder="+265 999 000 000"></div>
    </div>
    <div class="form-row">
      <div>
        <label>Amount</label>
        <input id="amount" type="number" min="1" step="0.01" placeholder="1000">
      </div>
      <div>
        <label>Currency</label>
        <select id="currency"><option value="MWK">MWK – Malawian Kwacha</option><option value="USD">USD – US Dollar</option></select>
      </div>
    </div>
    <div class="form-row single">
      <div><label>Payment Description</label><input id="desc" type="text" placeholder="e.g. Course registration fee"></div>
    </div>
    <button class="btn" onclick="initiatePayment()">Pay Now →</button>
    <div id="loading">⏳ Processing… please wait</div>
    <pre id="apiResponse"></pre>
  </div>
<?php endif; ?>

</div>

<script>
async function initiatePayment() {
  const fields = {
    first_name:  document.getElementById('firstName').value.trim(),
    last_name:   document.getElementById('lastName').value.trim(),
    email:       document.getElementById('email').value.trim(),
    amount:      document.getElementById('amount').value.trim(),
    currency:    document.getElementById('currency').value,
    description: document.getElementById('desc').value.trim() || 'Online Payment',
  };

  if (!fields.first_name || !fields.last_name || !fields.email || !fields.amount) {
    alert('Please fill in all required fields.'); return;
  }

  document.getElementById('loading').style.display = 'block';
  const respEl = document.getElementById('apiResponse');
  respEl.style.display = 'none';

  try {
    const res  = await fetch('<?= APP_URL ?>/public/pay.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(fields),
    });
    const data = await res.json();
    respEl.textContent  = JSON.stringify(data, null, 2);
    respEl.style.display = 'block';

    if (data.status === 'success' && data.checkout_url) {
      window.location.href = data.checkout_url;
    } else {
      alert('Error: ' + (data.message || JSON.stringify(data.errors)));
    }
  } catch (err) {
    alert('Request failed: ' + err.message);
  } finally {
    document.getElementById('loading').style.display = 'none';
  }
}
</script>
</body>
</html>
