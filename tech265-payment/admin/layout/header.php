<?php
// Shared admin header/layout
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tech265 Admin – <?= APP_NAME ?></title>
<style>
:root{--primary:#6C63FF;--dark:#1e1b4b;--success:#22c55e;--danger:#ef4444;--warning:#f59e0b;--light:#f8fafc;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:var(--light);color:#334155;display:flex;min-height:100vh;}

/* Sidebar */
.sidebar{width:230px;background:var(--dark);color:#c7d2fe;padding:0;display:flex;flex-direction:column;flex-shrink:0;min-height:100vh;position:sticky;top:0;}
.sidebar-logo{padding:22px 20px;font-size:1.1rem;font-weight:800;color:#fff;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:8px;}
.sidebar nav{flex:1;padding:12px 0;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#a5b4fc;text-decoration:none;font-size:.88rem;font-weight:500;transition:background .15s,color .15s;border-left:3px solid transparent;}
.sidebar a:hover,.sidebar a.active{background:rgba(108,99,255,.18);color:#fff;border-left-color:var(--primary);}
.sidebar-footer{padding:14px 20px;font-size:.75rem;color:#6366f1;border-top:1px solid rgba(255,255,255,.08);}

/* Main */
.main{flex:1;display:flex;flex-direction:column;min-width:0;}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;}
.topbar-title{font-weight:700;color:var(--dark);}
.topbar-user{font-size:.85rem;color:#64748b;display:flex;gap:16px;align-items:center;}
.topbar-user a{color:#6C63FF;text-decoration:none;font-weight:600;}
.content{padding:28px;flex:1;}
.page-title{font-size:1.25rem;font-weight:800;color:var(--dark);margin-bottom:22px;}

/* KPI grid */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px;}
.kpi-card{background:#fff;border-radius:14px;padding:18px 20px;box-shadow:0 2px 12px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px;}
.kpi-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.kpi-val{font-size:1.25rem;font-weight:800;color:#1e1b4b;}
.kpi-label{font-size:.78rem;color:#94a3b8;font-weight:500;margin-top:2px;}

/* Cards / Tables */
.card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.card h3{font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:16px;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.85rem;}
thead{background:#f8fafc;}
th{padding:10px 14px;text-align:left;font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e2e8f0;}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
tr:hover td{background:#fafafa;}

/* Badges */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;}
.badge.success,.badge.verified{background:#dcfce7;color:#166534;}
.badge.failed,.badge.cancelled{background:#fee2e2;color:#991b1b;}
.badge.pending{background:#fef9c3;color:#713f12;}
.badge.error{background:#fee2e2;color:#991b1b;}
.badge.info{background:#e0f2fe;color:#075985;}

/* Error rows */
.err-row{padding:10px 0;border-bottom:1px solid #f1f5f9;}
.err-type{font-size:.75rem;font-weight:700;color:#dc2626;background:#fee2e2;display:inline-block;padding:2px 8px;border-radius:20px;margin-bottom:4px;}
.err-msg{font-size:.83rem;color:#334155;}
.err-time{font-size:.75rem;color:#94a3b8;margin-top:2px;}

/* Misc */
.view-all{font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600;}
.view-all:hover{text-decoration:underline;}
.filter-bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px;}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.85rem;}
.filter-bar .btn{background:var(--primary);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;}
.pagination{display:flex;gap:6px;margin-top:16px;align-items:center;font-size:.85rem;}
.pagination a{padding:5px 11px;border:1.5px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#334155;}
.pagination a.active,.pagination a:hover{background:var(--primary);color:#fff;border-color:var(--primary);}
@media(max-width:900px){.two-col{grid-template-columns:1fr;}.sidebar{width:200px;}}
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-logo">⚡ Tech265</div>
  <nav>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $links = [
        'index.php'         => ['📊', 'Dashboard'],
        'transactions.php'  => ['💳', 'Transactions'],
        'api-logs.php'      => ['📡', 'API Logs'],
        'errors.php'        => ['🔴', 'Error Logs'],
        'webhooks.php'      => ['🪝', 'Webhooks'],
        'api-reference.php' => ['📚', 'API Reference'],
    ];
    foreach ($links as $file => [$icon, $label]):
        $active = $currentPage === $file ? 'active' : '';
    ?>
    <a href="<?= APP_URL ?>/admin/<?= $file ?>" class="<?= $active ?>"><?= $icon ?> <?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">PayChangu Integration v1.0</div>
</div>

<div class="main">
  <div class="topbar">
    <span class="topbar-title">Admin Panel</span>
    <div class="topbar-user">
      👤 <?= htmlspecialchars($user['fullname'] ?: $user['username']) ?>
      <a href="<?= APP_URL ?>/admin/logout.php">Logout</a>
    </div>
  </div>
  <div class="content">
