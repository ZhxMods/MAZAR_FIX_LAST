<?php
declare(strict_types=1);
// admin/_layout.php — Admin Panel Header + Sidebar
// $pageTitle must be set before including this file

if (!function_exists('isSuperAdmin')) require_once dirname(__DIR__) . '/includes/permissions.php';

$lang = getCurrentLang();
$dir  = getDirection();

// Get admin name
if (defined('SESS_UNAME') && isset($_SESSION[SESS_UNAME])) {
    $adminName = $_SESSION[SESS_UNAME];
} elseif (defined('SESS_USERNAME') && isset($_SESSION[SESS_USERNAME])) {
    $adminName = $_SESSION[SESS_USERNAME];
} elseif (isset($_SESSION['username'])) {
    $adminName = $_SESSION['username'];
} elseif (isset($_SESSION['full_name'])) {
    $adminName = $_SESSION['full_name'];
} else {
    $adminName = 'Admin';
}

$role = '';
if (defined('SESS_ROLE') && isset($_SESSION[SESS_ROLE])) {
    $role = $_SESSION[SESS_ROLE];
} elseif (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
}

// Get AI config for kill switch indicator
$aiConfig = getAIConfig();
$aiEnabled = $aiConfig['enabled'] ?? true;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= clean(isset($pageTitle) ? $pageTitle : 'Admin') ?> — <?= t('site_name') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    body { font-family: <?= $lang==='ar' ? "'Cairo'" : "'Poppins'" ?>, sans-serif; background:#0f172a; }
    .admin-sidebar { background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); border-right: 1px solid rgba(255,255,255,.06); }
    .admin-content { background: #f1f5f9; }
    .active-nav { background: rgba(59,130,246,.15); color: #60a5fa; border-<?= $dir==='rtl'?'right':'left' ?>: 3px solid #3b82f6; }
    .nav-item { transition: all .15s; }
    .nav-item:hover { background: rgba(255,255,255,.05); color: #93c5fd; }
    .stat-card { background: #fff; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.07); padding: 1.5rem; }
    .admin-card { background: #fff; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
    .badge-role-super  { background: linear-gradient(135deg, #7c3aed, #c026d3); }
    .badge-role-admin  { background: linear-gradient(135deg, #1d4ed8, #7c3aed); }
    .badge-role-staff  { background: linear-gradient(135deg, #0369a1, #0891b2); }
    .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem; }
    .modal-box { background:#fff;border-radius:1.5rem;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.35); }
    .modal-box-sm { background:#fff;border-radius:1.5rem;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.35); }
    #toast-admin { position:fixed;top:20px;<?= $dir==='rtl'?'left':'right' ?>:20px;z-index:9999; }
    .nav-section { font-size:.65rem; font-weight:700; letter-spacing:.08em; color:#475569; text-transform:uppercase; padding: .5rem 1rem .25rem; margin-top:.5rem; }
    
    /* Mobile Sidebar */
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; }
    .sidebar-overlay.active { display: block; }
    .sidebar-mobile { transform: translateX(<?= $dir==='rtl' ? '100%' : '-100%' ?>); transition: transform 0.3s ease; }
    .sidebar-mobile.active { transform: translateX(0); }
    
    /* Hamburger Menu */
    .hamburger { display: none; }
    @media (max-width: 768px) {
      .hamburger { display: flex; }
      .sidebar-desktop { display: none !important; }
    }
    
    /* AI Kill Switch Indicator */
    .ai-status { 
      display: inline-flex; align-items: center; gap: 0.5rem;
      padding: 0.25rem 0.75rem; border-radius: 9999px;
      font-size: 0.75rem; font-weight: 600;
    }
    .ai-status.enabled { background: #dcfce7; color: #166534; }
    .ai-status.disabled { background: #fee2e2; color: #991b1b; }
  </style>
</head>
<body class="flex h-screen overflow-hidden">

<div id="toast-admin" class="space-y-2"></div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- ══ SIDEBAR (Desktop) ════════════════════════════════════════════════ -->
<aside class="admin-sidebar sidebar-desktop w-60 flex-shrink-0 flex flex-col h-full overflow-y-auto hidden md:flex">

  <!-- Logo -->
  <div class="px-5 py-5 border-b border-white/5">
    <a href="../index.php" class="flex items-center gap-2">
      <img src="../assets/images/mazar.avif" alt="MAZAR" class="w-9 h-9 rounded-xl object-contain">
      <span class="text-white font-black text-lg"><?= t('site_name') ?></span>
    </a>
    <div class="mt-1 text-xs text-slate-500">Admin Panel</div>
  </div>

  <!-- Profile -->
  <div class="px-5 py-4 border-b border-white/5">
    <div class="flex items-center gap-3">
      <?php
        if ($role === 'super_admin') {
            $badgeClass = 'badge-role-super';
        } elseif ($role === 'admin') {
            $badgeClass = 'badge-role-admin';
        } else {
            $badgeClass = 'badge-role-staff';
        }
      ?>
      <div class="w-10 h-10 <?= $badgeClass ?> rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
        <?= mb_strtoupper(mb_substr($adminName, 0, 1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-white font-semibold text-sm truncate"><?= clean($adminName) ?></div>
        <div class="text-slate-400 text-xs"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="px-3 py-3 flex-1 space-y-0.5">
    <?php
    $current = basename($_SERVER['PHP_SELF']);

    $alwaysNav = array(
      array('dashboard.php',      'layout-dashboard', t('dashboard')),
      array('manage_lessons.php', 'book-open',        t('manage_lessons')),
      array('manage_quizzes.php', 'help-circle',      t('manage_quizzes')),
    );

    $superNav = array(
      array('manage_subjects.php', 'layers',        t('manage_subjects')),
      array('manage_levels.php',   'list-ordered',  t('manage_levels')),
      array('manage_users.php',    'users',         t('manage_users')),
      array('ai_config.php',       'sparkles',      'Mazar AI Config'),
    );
    ?>

    <div class="nav-section"><?= t('general') ?></div>
    <?php foreach($alwaysNav as $navItem): ?>
    <?php $href = $navItem[0]; $icon = $navItem[1]; $label = $navItem[2]; ?>
    <a href="<?= $href ?>"
       class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-slate-400 text-sm font-medium cursor-pointer <?= $current===$href ? 'active-nav text-blue-400' : '' ?>">
      <i data-lucide="<?= $icon ?>" class="w-4 h-4 flex-shrink-0"></i>
      <?= $label ?>
    </a>
    <?php endforeach; ?>

    <?php if(isSuperAdmin()): ?>
    <div class="nav-section mt-3">Super Admin</div>
    <?php foreach($superNav as $navItem): ?>
    <?php $href = $navItem[0]; $icon = $navItem[1]; $label = $navItem[2]; ?>
    <a href="<?= $href ?>"
       class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-slate-400 text-sm font-medium cursor-pointer <?= $current===$href ? 'active-nav text-blue-400' : '' ?>">
      <i data-lucide="<?= $icon ?>" class="w-4 h-4 flex-shrink-0"></i>
      <?= $label ?>
      <?php if($href === 'ai_config.php'): ?>
      <span class="ml-auto w-2 h-2 rounded-full <?= $aiEnabled ? 'bg-green-500' : 'bg-red-500' ?>"></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <!-- Lang + Logout -->
  <div class="px-4 py-4 border-t border-white/5 space-y-3">
    <div class="flex gap-1">
      <?php foreach(array('ar','fr','en') as $l): ?>
      <a href="?lang=<?= $l ?>"
         class="flex-1 text-center py-1 rounded-lg text-xs font-bold transition <?= $lang===$l ? 'bg-blue-600 text-white' : 'text-slate-500 hover:bg-white/5' ?>">
        <?= strtoupper($l) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <a href="../logout.php"
       class="flex items-center gap-2 text-slate-500 hover:text-white text-xs px-2 py-2 rounded-xl hover:bg-white/5 transition">
      <i data-lucide="log-out" class="w-4 h-4"></i>
      <?= t('logout') ?>
    </a>
  </div>
</aside>

<!-- ══ MOBILE SIDEBAR ════════════════════════════════════════════════ -->
<aside class="admin-sidebar sidebar-mobile fixed inset-y-0 <?= $dir==='rtl'?'right-0':'left-0' ?> w-72 z-50 flex flex-col h-full overflow-y-auto md:hidden" id="mobile-sidebar">
  <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
    <a href="../index.php" class="flex items-center gap-2">
      <img src="../assets/images/mazar.avif" alt="MAZAR" class="w-8 h-8 rounded-xl object-contain">
      <span class="text-white font-black text-lg"><?= t('site_name') ?></span>
    </a>
    <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white p-1">
      <i data-lucide="x" class="w-6 h-6"></i>
    </button>
  </div>

  <div class="px-5 py-4 border-b border-white/5">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 <?= $badgeClass ?> rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
        <?= mb_strtoupper(mb_substr($adminName, 0, 1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-white font-semibold text-sm truncate"><?= clean($adminName) ?></div>
        <div class="text-slate-400 text-xs"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
      </div>
    </div>
  </div>

  <nav class="px-3 py-3 flex-1 space-y-0.5">
    <div class="nav-section"><?= t('general') ?></div>
    <?php foreach($alwaysNav as $navItem): ?>
    <?php $href = $navItem[0]; $icon = $navItem[1]; $label = $navItem[2]; ?>
    <a href="<?= $href ?>"
       class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-slate-400 text-sm font-medium cursor-pointer <?= $current===$href ? 'active-nav text-blue-400' : '' ?>">
      <i data-lucide="<?= $icon ?>" class="w-4 h-4 flex-shrink-0"></i>
      <?= $label ?>
    </a>
    <?php endforeach; ?>

    <?php if(isSuperAdmin()): ?>
    <div class="nav-section mt-3">Super Admin</div>
    <?php foreach($superNav as $navItem): ?>
    <?php $href = $navItem[0]; $icon = $navItem[1]; $label = $navItem[2]; ?>
    <a href="<?= $href ?>"
       class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-slate-400 text-sm font-medium cursor-pointer <?= $current===$href ? 'active-nav text-blue-400' : '' ?>">
      <i data-lucide="<?= $icon ?>" class="w-4 h-4 flex-shrink-0"></i>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <div class="px-4 py-4 border-t border-white/5 space-y-3">
    <div class="flex gap-1">
      <?php foreach(array('ar','fr','en') as $l): ?>
      <a href="?lang=<?= $l ?>"
         class="flex-1 text-center py-1 rounded-lg text-xs font-bold transition <?= $lang===$l ? 'bg-blue-600 text-white' : 'text-slate-500 hover:bg-white/5' ?>">
        <?= strtoupper($l) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <a href="../logout.php"
       class="flex items-center gap-2 text-slate-500 hover:text-white text-xs px-2 py-2 rounded-xl hover:bg-white/5 transition">
      <i data-lucide="log-out" class="w-4 h-4"></i>
      <?= t('logout') ?>
    </a>
  </div>
</aside>

<!-- ══ MAIN CONTENT ══════════════════════════════════════════ -->
<div class="flex-1 admin-content flex flex-col overflow-hidden">
  <!-- Top Bar -->
  <header class="bg-white border-b border-gray-200 px-4 md:px-6 py-4 flex items-center justify-between flex-shrink-0">
    <div class="flex items-center gap-3">
      <!-- Hamburger Menu -->
      <button onclick="toggleSidebar()" class="hamburger p-2 -ml-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
      </button>
      <h1 class="font-black text-gray-900 text-base md:text-lg"><?= clean(isset($pageTitle) ? $pageTitle : '') ?></h1>
    </div>
    <div class="flex items-center gap-2 md:gap-3">
      <!-- AI Status Indicator -->
      <?php if(isSuperAdmin()): ?>
      <a href="ai_config.php" class="hidden sm:flex ai-status <?= $aiEnabled ? 'enabled' : 'disabled' ?>">
        <i data-lucide="sparkles" class="w-3 h-3"></i>
        AI <?= $aiEnabled ? t('enabled') : t('disabled') ?>
      </a>
      <?php endif; ?>
      <a href="../index.php" target="_blank"
         class="text-gray-400 hover:text-blue-600 text-sm flex items-center gap-1 transition">
        <i data-lucide="external-link" class="w-4 h-4"></i>
        <span class="hidden sm:inline"><?= t('home') ?></span>
      </a>
      <?php
        if ($role === 'super_admin') {
            $roleLabel = ' Super Admin';
            $roleBg    = 'bg-purple-600';
        } elseif ($role === 'admin') {
            $roleLabel = ' Admin';
            $roleBg    = 'bg-blue-700';
        } else {
            $roleLabel = ' Staff';
            $roleBg    = 'bg-cyan-700';
        }
      ?>
      <div class="<?= $roleBg ?> text-white text-xs font-bold px-2 md:px-3 py-1 rounded-full"><?= $roleLabel ?></div>
    </div>
  </header>

  <main class="flex-1 overflow-y-auto p-4 md:p-6">

<script>
  // Mobile sidebar toggle
  function toggleSidebar() {
    const sidebar = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
  }
  
  lucide.createIcons();
</script>
