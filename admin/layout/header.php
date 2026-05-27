<?php
// Shared admin header/layout
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/../../src/Security.php';
Security::sendSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tech265 Admin – <?= APP_NAME ?></title>
<style>
/* ── Variables ─────────────────────────────────────── */
:root{
  --primary:#0099FF;--dark:#0A1628;--accent:#F97316;
  --success:#22c55e;--danger:#ef4444;--warning:#f59e0b;--light:#f0f6ff;
  --sidebar-w:235px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:var(--light);color:#334155;display:flex;min-height:100vh;}

/* ── Sidebar ────────────────────────────────────────── */
.sidebar{
  width:var(--sidebar-w);background:var(--dark);color:#cbd5e1;
  display:flex;flex-direction:column;flex-shrink:0;
  position:sticky;top:0;height:100vh;overflow-y:auto;
}
.sidebar-logo{padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;}
.sidebar-logo img{height:38px;width:auto;filter:brightness(0) invert(1);}
.sidebar nav{flex:1;padding:12px 0;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#94a3b8;text-decoration:none;font-size:.88rem;font-weight:500;transition:background .15s,color .15s;border-left:3px solid transparent;}
.sidebar a:hover,.sidebar a.active{background:rgba(0,153,255,.15);color:#fff;border-left-color:var(--primary);}
.sidebar-footer{padding:14px 20px;font-size:.75rem;color:#475569;border-top:1px solid rgba(255,255,255,.06);}

/* ── Overlay (mobile drawer backdrop) ──────────────── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:40;}
.sidebar-overlay.active{display:block;}

/* ── Hamburger ──────────────────────────────────────── */
.hamburger{display:none;background:none;border:none;cursor:pointer;padding:6px 8px;border-radius:8px;color:var(--dark);align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;}
.hamburger:hover{background:#f1f5f9;}
.hamburger svg{width:22px;height:22px;display:block;}

/* ── Main ───────────────────────────────────────────── */
.main{flex:1;display:flex;flex-direction:column;min-width:0;}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px;}
.topbar-title{font-weight:700;color:var(--dark);display:flex;align-items:center;gap:10px;min-width:0;}
.topbar-title img{height:30px;width:auto;flex-shrink:0;}
.topbar-label{white-space:nowrap;}
.topbar-user{font-size:.85rem;color:#64748b;display:flex;gap:14px;align-items:center;white-space:nowrap;flex-shrink:0;}
.topbar-user a{color:var(--primary);text-decoration:none;font-weight:600;}
.content{padding:28px;flex:1;}
.page-title{font-size:1.25rem;font-weight:800;color:var(--dark);margin-bottom:22px;}

/* ── KPI grid ───────────────────────────────────────── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:24px;}
.kpi-card{background:#fff;border-radius:14px;padding:18px 20px;box-shadow:0 2px 12px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px;}
.kpi-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.kpi-val{font-size:1.25rem;font-weight:800;color:var(--dark);}
.kpi-label{font-size:.78rem;color:#94a3b8;font-weight:500;margin-top:2px;}

/* ── Cards / Tables ─────────────────────────────────── */
.card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.card h3{font-size:.95rem;font-weight:700;color:var(--dark);margin-bottom:16px;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
table{width:100%;border-collapse:collapse;font-size:.85rem;}
thead{background:#f8fafc;}
th{padding:10px 14px;text-align:left;font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e2e8f0;}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
tr:hover td{background:#fafafa;}

/* ── Badges ─────────────────────────────────────────── */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;}
.badge.success,.badge.verified{background:#dcfce7;color:#166534;}
.badge.failed,.badge.cancelled{background:#fee2e2;color:#991b1b;}
.badge.pending{background:#fef9c3;color:#713f12;}
.badge.error{background:#fee2e2;color:#991b1b;}
.badge.info{background:#e0f2fe;color:#075985;}

/* ── Error rows ─────────────────────────────────────── */
.err-row{padding:10px 0;border-bottom:1px solid #f1f5f9;}
.err-type{font-size:.75rem;font-weight:700;color:#dc2626;background:#fee2e2;display:inline-block;padding:2px 8px;border-radius:20px;margin-bottom:4px;}
.err-msg{font-size:.83rem;color:#334155;}
.err-time{font-size:.75rem;color:#94a3b8;margin-top:2px;}

/* ── Misc ───────────────────────────────────────────── */
.view-all{font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600;}
.view-all:hover{text-decoration:underline;}
.filter-bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px;}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.85rem;}
.filter-bar .btn{background:var(--primary);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;}
.pagination{display:flex;gap:6px;margin-top:16px;align-items:center;flex-wrap:wrap;font-size:.85rem;}
.pagination a{padding:5px 11px;border:1.5px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#334155;}
.pagination a.active,.pagination a:hover{background:var(--primary);color:#fff;border-color:var(--primary);}

/* ══════════════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
══════════════════════════════════════════════════════ */

/* Tablet (≤ 900px) */
@media(max-width:900px){
  :root{--sidebar-w:200px;}
  .two-col{grid-template-columns:1fr;}
}

/* Mobile (≤ 768px) — sidebar becomes off-canvas drawer */
@media(max-width:768px){
  body{display:block;}
  .sidebar{
    position:fixed;left:0;top:0;bottom:0;height:100%;min-height:unset;
    z-index:50;transform:translateX(-100%);
    transition:transform .27s cubic-bezier(.4,0,.2,1);
  }
  .sidebar.open{transform:translateX(0);}
  .hamburger{display:flex;}
  .main{width:100%;min-height:100vh;}
  .topbar{padding:10px 14px;}
  .topbar-title img{height:26px;}
  .topbar-label{display:none;}
  .topbar-user{gap:10px;}
  .user-name{display:none;}
  .content{padding:16px;}
  .page-title{font-size:1.05rem;margin-bottom:14px;}
  .kpi-grid{grid-template-columns:repeat(2,1fr);gap:10px;}
  .kpi-card{padding:14px 12px;gap:10px;border-radius:10px;}
  .kpi-icon{width:38px;height:38px;font-size:1.1rem;}
  .kpi-val{font-size:1rem;}
  .kpi-label{font-size:.72rem;}
  .card{padding:16px;border-radius:10px;}
  .filter-bar input,.filter-bar select{flex:1 1 130px;min-width:0;}
  th,td{padding:8px 10px;}
}

/* Small phones (≤ 480px) */
@media(max-width:480px){
  .kpi-grid{gap:8px;}
  .kpi-card{padding:11px 10px;gap:8px;}
  .kpi-icon{width:32px;height:32px;font-size:.9rem;}
  .kpi-val{font-size:.92rem;}
  .topbar-title img{height:22px;}
  .topbar{padding:9px 12px;}
  th{font-size:.72rem;padding:7px 8px;}
  td{font-size:.8rem;padding:7px 8px;}
  .filter-bar{gap:8px;}
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="<?= APP_URL ?>/public/images/tech265-new-white-original.png" alt="Tech265">
  </div>
  <nav>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $links = [
        'index.php'         => ['📊', 'Dashboard'],
        'transactions.php'  => ['💳', 'Transactions'],
        'api-logs.php'      => ['📡', 'API Logs'],
        'errors.php'        => ['🔴', 'Error Logs'],
        'webhooks.php'      => ['🪝', 'Webhooks'],
        'direct-charges.php' => ['📱', 'Direct Charges'],
        'payouts.php'         => ['💸', 'Payouts'],
        'api-reference.php'   => ['📚', 'API Reference'],
    ];
    foreach ($links as $file => [$icon, $label]):
        $active = $currentPage === $file ? 'active' : '';
    ?>
    <a href="<?= APP_URL ?>/admin/<?= $file ?>" class="<?= $active ?>"><?= $icon ?> <?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">PayChangu Integration &nbsp;·&nbsp; v1.0</div>
</aside>

<div class="main" id="main-content">
  <div class="topbar">
    <button class="hamburger" id="hamburger" aria-label="Toggle navigation">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <span class="topbar-title">
      <img src="<?= APP_URL ?>/public/images/tech265-new-white-original.png" alt="Tech265">
      <span class="topbar-label">Admin Panel</span>
    </span>
    <div class="topbar-user">
      <span class="user-name">👤 <?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></span>
      <a href="<?= APP_URL ?>/admin/logout.php">Logout</a>
    </div>
  </div>
  <div class="content">
