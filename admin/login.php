<?php
require_once __DIR__ . '/../src/AdminAuth.php';
require_once __DIR__ . '/../src/Security.php';

AdminAuth::startSession();
Security::sendSecurityHeaders();

if (AdminAuth::isLoggedIn()) {
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::validateCsrf();
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (AdminAuth::login($user, $pass)) {
        header('Location: ' . APP_URL . '/admin/');
        exit;
    }
    $error = 'Invalid username or password.';
}
$csrfToken = Security::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tech265 Admin – Login</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#0A1628 0%,#0066CC 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .card{background:#fff;border-radius:20px;padding:44px 40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.35);}
  .logo{text-align:center;margin-bottom:28px;}
  .logo img{height:50px;width:auto;margin-bottom:10px;}
  .logo p{color:#64748b;font-size:.85rem;margin-top:4px;}
  label{display:block;font-size:.82rem;font-weight:600;color:#475569;margin-bottom:5px;}
  input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.9rem;margin-bottom:16px;}
  input:focus{outline:none;border-color:#0099FF;box-shadow:0 0 0 3px rgba(0,153,255,.12);}
  .btn{width:100%;background:#0099FF;color:#fff;border:none;padding:13px;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s;}
  .btn:hover{opacity:.88;}
  .error{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;border:1px solid #fecaca;}
  .hint{text-align:center;color:#94a3b8;font-size:.75rem;margin-top:16px;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="<?= APP_URL ?>/public/images/tech265-new-white-original.png" alt="Tech265">
    <p>Payment Gateway Admin</p>
  </div>
  <?php if ($error): ?>
  <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= Security::h($csrfToken) ?>">
    <label>Username</label>
    <input type="text" name="username" required autocomplete="username" placeholder="admin">
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    <button class="btn" type="submit">Sign In</button>
  </form>
</div>
</body>
</html>
