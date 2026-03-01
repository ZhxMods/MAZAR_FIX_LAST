<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$lang      = getCurrentLang();
$dir       = getDirection();
$userId    = (int)$_SESSION[SESS_USER_ID];
$gradeId   = (int)$_SESSION[SESS_GRADE];
$userXP    = (int)$_SESSION[SESS_XP];
$userLevel = (int)$_SESSION[SESS_LEVEL];
$userName  = $_SESSION[SESS_USERNAME] ?? 'Student';

$db      = getDB();
$lvlStmt = $db->prepare("SELECT name_{$lang} AS name FROM levels WHERE id = :id");
$lvlStmt->execute([':id' => $gradeId]);
$gradeName = $lvlStmt->fetchColumn() ?: '—';

$subjects    = getSubjectsByLevel($gradeId);
$leaderboard = getLeaderboard($gradeId, 5);

$rankStmt = $db->prepare(
    "SELECT COUNT(*)+1 AS rank FROM users
     WHERE grade_level_id = :gid AND xp_points > :xp AND role = 'student' AND status = 'active'"
);
$rankStmt->execute([':gid' => $gradeId, ':xp' => $userXP]);
$userRank = $rankStmt->fetchColumn() ?: 1;

$activeSubjectId = (int)($_GET['subject'] ?? ($subjects[0]['id'] ?? 0));
$lessons = $activeSubjectId ? getLessonsBySubject($activeSubjectId, $userId) : [];

$progressPct  = xpProgressPercent($userXP, $userLevel);
$nextLevelXP  = xpForNextLevel($userLevel);

$tab     = $_GET['tab'] ?? 'lessons';
$welcome = isset($_GET['welcome']);

// Validate subject ownership (IDOR protection)
if ($activeSubjectId > 0) {
    $subjectCheck = $db->prepare("SELECT level_id FROM subjects WHERE id = :sid");
    $subjectCheck->execute([':sid' => $activeSubjectId]);
    $subjectLevel = $subjectCheck->fetchColumn();
    
    if ($subjectLevel === false || (int)$subjectLevel !== $gradeId) {
        $activeSubjectId = $subjects[0]['id'] ?? 0;
        $lessons = $activeSubjectId ? getLessonsBySubject($activeSubjectId, $userId) : [];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= t('dashboard') ?> — <?= t('site_name') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="../assets/css/xp-animations.css">
  <style>
    body { font-family: <?= $lang==='ar' ? "'Cairo'" : "'Poppins'" ?>, sans-serif; background:#f1f5f9; }
    .sidebar { background: linear-gradient(180deg, #1e3a8a 0%, #1d4ed8 100%); }
    .active-tab { background: rgba(255,255,255,0.15); }
    .level-badge { background: linear-gradient(135deg, <?= levelBadgeColor($userLevel) ?>, <?= levelBadgeColor(min($userLevel+1,10)) ?>); }
    .card { background:#fff; border-radius:1rem; box-shadow:0 1px 3px rgba(0,0,0,.07); }
    .lesson-card { transition: transform .2s, box-shadow .2s; }
    .lesson-card:hover { transform:translateY(-4px); box-shadow:0 14px 32px rgba(59,130,246,.18); }
    .xp-bar-fill { transition: width 1s cubic-bezier(.4,0,.2,1); }
    .toast { position:fixed; top:20px; <?= $dir==='rtl'?'left':'right' ?>:20px; z-index:9999; }
    
    /* Mobile Sidebar */
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; }
    .sidebar-overlay.active { display: block; }
    .sidebar-mobile { transform: translateX(<?= $dir==='rtl' ? '100%' : '-100%' ?>); transition: transform 0.3s ease; }
    .sidebar-mobile.active { transform: translateX(0); }
    
    /* Hamburger Menu */
    .hamburger { display: none; }
    @media (max-width: 768px) {
      .hamburger { display: flex; }
      .sidebar-desktop { display: none; }
    }
    
    /* OPEN LESSON BUTTON */
    .open-lesson-btn {
      display: flex; align-items: center; justify-content: center; gap: .45rem;
      width: 100%; padding: .65rem 1rem; border-radius: .75rem;
      font-size: .82rem; font-weight: 700; border: none; cursor: pointer;
      text-decoration: none; transition: transform .18s cubic-bezier(.34,1.56,.64,1), box-shadow .18s;
      color: #fff; letter-spacing: .02em;
    }
    .open-lesson-btn.incomplete {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      box-shadow: 0 4px 14px rgba(37,99,235,.35);
    }
    .open-lesson-btn.incomplete:hover {
      transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,.5);
    }
    .open-lesson-btn.completed-btn {
      background: linear-gradient(135deg, #059669, #047857);
      box-shadow: 0 4px 14px rgba(5,150,105,.35);
    }
    .open-lesson-btn.completed-btn:hover {
      transform: translateY(-2px); box-shadow: 0 8px 24px rgba(5,150,105,.5);
    }

    /* SKELETON LOADING */
    @keyframes skeletonShimmer {
      0%   { background-position: -600px 0; }
      100% { background-position:  600px 0; }
    }
    .skeleton {
      background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
      background-size: 600px 100%;
      animation: skeletonShimmer 1.4s ease-in-out infinite;
    }
    .skeleton-card { background: #fff; border-radius: 1rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
    .skeleton-thumb { height: 160px; }
    .skeleton-line { border-radius: 6px; height: 12px; }
    .skeleton-btn { border-radius: .75rem; height: 36px; }
    .skeleton-card:nth-child(1) .skeleton { animation-delay: 0s; }
    .skeleton-card:nth-child(2) .skeleton { animation-delay: .1s; }
    .skeleton-card:nth-child(3) .skeleton { animation-delay: .2s; }
    .skeleton-card:nth-child(4) .skeleton { animation-delay: 0s; }
    .skeleton-card:nth-child(5) .skeleton { animation-delay: .1s; }
    .skeleton-card:nth-child(6) .skeleton { animation-delay: .2s; }
    @keyframes fadeInCards {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .cards-loaded { animation: fadeInCards .35s ease both; }

    /* MAZAR AI FAB */
    #mazar-fab {
      position: fixed; bottom: 2rem; <?= $dir === 'rtl' ? 'left' : 'right' ?>: 2rem;
      z-index: 9000; display: flex; flex-direction: column;
      align-items: <?= $dir === 'rtl' ? 'flex-start' : 'flex-end' ?>; gap: .65rem; pointer-events: none;
    }
    #fab-tooltip {
      background: #1e293b; color: #f1f5f9; font-size: .73rem; font-weight: 700;
      padding: .4rem .9rem; border-radius: 999px; white-space: nowrap;
      box-shadow: 0 4px 16px rgba(0,0,0,.2); opacity: 0;
      transform: translateX(<?= $dir === 'rtl' ? '-10px' : '10px' ?>);
      transition: opacity .2s ease, transform .2s ease; pointer-events: none;
    }
    #mazar-fab:hover #fab-tooltip { opacity: 1; transform: translateX(0); }
    #fab-btn {
      width: 60px; height: 60px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
      box-shadow: 0 0 0 4px rgba(29,78,216,.18), 0 8px 28px rgba(30,58,138,.45);
      position: relative; text-decoration: none; pointer-events: all;
      transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s ease; flex-shrink: 0;
    }
    #fab-btn:hover { transform: scale(1.1) translateY(-2px); box-shadow: 0 0 0 6px rgba(29,78,216,.22), 0 16px 40px rgba(30,58,138,.55); }
    #fab-btn svg { width: 26px; height: 26px; color: #fff; }
    #fab-btn::before { content:''; position:absolute; inset:-4px; border-radius:50%; border:2px solid rgba(29,78,216,.4); animation:fabPulse 2.6s ease-out infinite; }
    @keyframes fabPulse { 0%{transform:scale(1);opacity:.8} 70%{transform:scale(1.55);opacity:0} 100%{transform:scale(1.55);opacity:0} }
    #fab-online { position:absolute; top:3px; right:3px; width:13px; height:13px; background:#10b981; border-radius:50%; border:2.5px solid #fff; animation:onlineBlink 2.2s ease-in-out infinite; }
    @keyframes onlineBlink { 0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(16,185,129,.5)} 50%{opacity:.6;box-shadow:0 0 0 4px rgba(16,185,129,0)} }
    #mazar-fab { animation: fabEntry .7s cubic-bezier(.34,1.56,.64,1) .5s both; }
    @keyframes fabEntry { from{opacity:0;transform:translateY(20px) scale(.7)} to{opacity:1;transform:translateY(0) scale(1)} }

    /* lesson card thumbnail placeholder */
    .thumb-placeholder {
      width:100%; height:160px; display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, #dbeafe, #ede9fe);
    }
    
    /* Mobile optimizations */
    @media (max-width: 640px) {
      .lesson-card { margin-bottom: 1rem; }
      #mazar-fab { bottom: 1rem; <?= $dir === 'rtl' ? 'left' : 'right' ?>: 1rem; }
      #fab-btn { width: 50px; height: 50px; }
    }
  </style>
</head>

<body class="flex h-screen overflow-hidden" x-data="mazarDashboard()" x-init="init()">

<div id="toast-container" class="toast space-y-2"></div>
<div id="xp-float-container" style="position:fixed;top:0;left:0;pointer-events:none;z-index:9998;"></div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- ─── SIDEBAR (Desktop) ─── -->
<aside class="sidebar sidebar-desktop w-64 flex-shrink-0 flex flex-col h-full overflow-y-auto hidden md:flex">
  <div class="px-6 py-6 border-b border-white/10">
    <a href="/" class="flex items-center gap-3">
      <img src="../assets/images/mazar.avif" alt="MAZAR" class="w-10 h-10 rounded-xl object-contain">
      <span class="text-white font-black text-xl"><?= t('site_name') ?></span>
    </a>
  </div>

  <div class="px-6 py-5 border-b border-white/10">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
        <?= mb_strtoupper(mb_substr($userName, 0, 1)) ?>
      </div>
      <div class="overflow-hidden">
        <div class="text-white font-semibold text-sm truncate"><?= clean($userName) ?></div>
        <div class="text-blue-200 text-xs truncate"><?= clean($gradeName) ?></div>
      </div>
    </div>
    <div class="flex items-center justify-between mb-2">
      <span class="text-blue-200 text-xs"><?= t('level') ?> <span id="sidebar-level" class="text-white font-bold"><?= $userLevel ?></span></span>
      <span class="text-yellow-300 text-xs font-bold"><span id="sidebar-xp"><?= $userXP ?></span> XP</span>
    </div>
    <div class="bg-white/20 rounded-full h-2">
      <div id="sidebar-xp-bar" class="xp-bar-fill bg-yellow-400 h-2 rounded-full" style="width:<?= $progressPct ?>%"></div>
    </div>
    <div class="text-right mt-1">
      <span class="text-blue-300 text-xs"><?= round($progressPct) ?>% → <?= t('level') ?> <?= $userLevel+1 ?></span>
    </div>
  </div>

  <nav class="px-4 py-4 flex-1">
    <a href="?tab=lessons" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1 <?= $tab==='lessons'?'active-tab':'' ?>">
      <i data-lucide="book-open" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('my_lessons') ?></span>
    </a>
    <a href="quizzes.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1">
      <i data-lucide="help-circle" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('my_quizzes') ?></span>
    </a>
    <a href="?tab=leaderboard" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1 <?= $tab==='leaderboard'?'active-tab':'' ?>">
      <i data-lucide="trophy" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('leaderboard') ?></span>
    </a>
    <a href="?tab=achievements" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1 <?= $tab==='achievements'?'active-tab':'' ?>">
      <i data-lucide="star" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('achievements') ?></span>
    </a>
    <div class="mt-4 border-t border-white/10 pt-4">
      <a href="mazar-ai.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/70 hover:text-white hover:bg-white/10 transition group">
        <div class="w-5 h-5 flex-shrink-0 flex items-center justify-center">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
            <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
            <path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
          </svg>
        </div>
        <span class="text-sm font-medium">MAZAR AI</span>
        <span class="ml-auto bg-yellow-400 text-yellow-900 text-xs font-bold px-1.5 py-0.5 rounded-md">IA</span>
      </a>
    </div>
  </nav>

  <div class="px-6 py-4 border-t border-white/10 space-y-3">
    <div class="flex gap-1">
      <?php foreach(['ar','fr','en'] as $l): ?>
      <a href="?lang=<?= $l ?>&tab=<?= $tab ?>"
         class="flex-1 text-center py-1 rounded-lg text-xs font-bold transition <?= $lang===$l ? 'bg-white text-blue-700' : 'text-white/60 hover:bg-white/10' ?>">
        <?= strtoupper($l) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <a href="../logout.php" class="flex items-center gap-2 text-white/60 hover:text-white text-sm transition px-2 py-2 rounded-xl hover:bg-white/10">
      <i data-lucide="log-out" class="w-4 h-4 flex-shrink-0"></i>
      <?= t('logout') ?>
    </a>
  </div>
</aside>

<!-- ─── MOBILE SIDEBAR ─── -->
<aside class="sidebar sidebar-mobile fixed inset-y-0 <?= $dir==='rtl'?'right-0':'left-0' ?> w-72 z-50 flex flex-col h-full overflow-y-auto md:hidden" id="mobile-sidebar">
  <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
    <a href="/" class="flex items-center gap-3">
      <img src="../assets/images/mazar.avif" alt="MAZAR" class="w-8 h-8 rounded-xl object-contain">
      <span class="text-white font-black text-lg"><?= t('site_name') ?></span>
    </a>
    <button onclick="toggleSidebar()" class="text-white/60 hover:text-white p-1">
      <i data-lucide="x" class="w-6 h-6"></i>
    </button>
  </div>

  <div class="px-6 py-4 border-b border-white/10">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-bold flex-shrink-0">
        <?= mb_strtoupper(mb_substr($userName, 0, 1)) ?>
      </div>
      <div class="overflow-hidden">
        <div class="text-white font-semibold text-sm truncate"><?= clean($userName) ?></div>
        <div class="text-blue-200 text-xs truncate"><?= clean($gradeName) ?></div>
      </div>
    </div>
    <div class="flex items-center justify-between mb-1">
      <span class="text-blue-200 text-xs"><?= t('level') ?> <?= $userLevel ?></span>
      <span class="text-yellow-300 text-xs font-bold"><?= $userXP ?> XP</span>
    </div>
    <div class="bg-white/20 rounded-full h-1.5">
      <div class="xp-bar-fill bg-yellow-400 h-1.5 rounded-full" style="width:<?= $progressPct ?>%"></div>
    </div>
  </div>

  <nav class="px-4 py-4 flex-1">
    <a href="?tab=lessons" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1 <?= $tab==='lessons'?'active-tab':'' ?>">
      <i data-lucide="book-open" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('my_lessons') ?></span>
    </a>
    <a href="quizzes.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1">
      <i data-lucide="help-circle" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('my_quizzes') ?></span>
    </a>
    <a href="?tab=leaderboard" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1 <?= $tab==='leaderboard'?'active-tab':'' ?>">
      <i data-lucide="trophy" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('leaderboard') ?></span>
    </a>
    <a href="?tab=achievements" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1 <?= $tab==='achievements'?'active-tab':'' ?>">
      <i data-lucide="star" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('achievements') ?></span>
    </a>
    <a href="mazar-ai.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/70 hover:text-white hover:bg-white/10 transition">
      <i data-lucide="sparkles" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium">MAZAR AI</span>
    </a>
  </nav>

  <div class="px-6 py-4 border-t border-white/10 space-y-3">
    <div class="flex gap-1">
      <?php foreach(['ar','fr','en'] as $l): ?>
      <a href="?lang=<?= $l ?>&tab=<?= $tab ?>"
         class="flex-1 text-center py-1 rounded-lg text-xs font-bold transition <?= $lang===$l ? 'bg-white text-blue-700' : 'text-white/60 hover:bg-white/10' ?>">
        <?= strtoupper($l) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <a href="../logout.php" class="flex items-center gap-2 text-white/60 hover:text-white text-sm transition px-2 py-2 rounded-xl hover:bg-white/10">
      <i data-lucide="log-out" class="w-4 h-4 flex-shrink-0"></i>
      <?= t('logout') ?>
    </a>
  </div>
</aside>

<!-- ─── MAIN CONTENT ─── -->
<div class="flex-1 flex flex-col overflow-hidden">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 px-4 md:px-6 py-4 flex items-center justify-between flex-shrink-0">
    <div class="flex items-center gap-3">
      <!-- Hamburger Menu -->
      <button onclick="toggleSidebar()" class="hamburger p-2 -ml-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
      </button>
      <div>
        <h1 class="font-black text-gray-900 text-lg"><?= t('dashboard') ?></h1>
        <p class="text-gray-500 text-xs hidden sm:block"><?= clean($gradeName) ?></p>
      </div>
    </div>
    <div class="flex items-center gap-2 md:gap-3">
      <!-- Level Badge Only (XP removed from header as per spec) -->
      <div class="level-badge flex items-center gap-1.5 md:gap-2 rounded-xl px-3 md:px-4 py-2">
        <i data-lucide="shield" class="w-4 h-4 text-white"></i>
        <span class="font-bold text-white text-sm"><?= t('level') ?> <?= $userLevel ?></span>
      </div>
      <!-- Rank (hidden on small mobile) -->
      <div class="hidden sm:flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-xl px-3 md:px-4 py-2">
        <i data-lucide="trophy" class="w-4 h-4 text-blue-600"></i>
        <span class="font-bold text-blue-700 text-sm">#<?= $userRank ?></span>
      </div>
    </div>
  </header>

  <main class="flex-1 overflow-y-auto p-4 md:p-6">

    <?php if ($tab === 'lessons'): ?>
    <!-- XP Progress Card -->
    <div class="card p-4 md:p-5 mb-6 bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
      <div class="flex items-center justify-between mb-3">
        <div>
          <div class="text-white/80 text-xs mb-1"><?= t('my_progress') ?></div>
          <div class="text-xl md:text-2xl font-black"><?= $userXP ?> <span class="text-white/60 text-sm font-normal">XP</span></div>
        </div>
        <div class="text-right">
          <div class="text-white/80 text-xs mb-1"><?= t('next_level') ?> (<?= $userLevel+1 ?>)</div>
          <div class="text-base md:text-lg font-bold"><?= $nextLevelXP ?> XP</div>
        </div>
      </div>
      <div class="bg-white/20 rounded-full h-2.5 md:h-3">
        <div class="bg-white/80 h-2.5 md:h-3 rounded-full transition-all duration-1000" style="width:<?= $progressPct ?>%"></div>
      </div>
      <div class="text-right mt-1 text-white/60 text-xs"><?= round($progressPct) ?>%</div>
    </div>

    <!-- Subject Tabs -->
    <div class="flex gap-2 mb-5 overflow-x-auto pb-2" id="subject-tabs">
      <?php foreach($subjects as $subj): ?>
      <a href="?tab=lessons&subject=<?= $subj['id'] ?>"
         onclick="showSkeletons(event, this)"
         class="flex items-center gap-2 px-3 md:px-4 py-2 rounded-xl text-sm font-semibold transition flex-shrink-0
         <?= $activeSubjectId===$subj['id'] ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-white text-gray-600 hover:bg-blue-50 hover:text-blue-700 border border-gray-200' ?>">
        <i data-lucide="<?= clean($subj['icon']) ?>" class="w-4 h-4"></i>
        <span class="truncate"><?= clean($subj['name']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Skeleton grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5 hidden" id="skeletons-grid">
      <?php for($s=0;$s<6;$s++): ?>
      <div class="skeleton-card">
        <div class="skeleton skeleton-thumb"></div>
        <div class="p-4 space-y-3">
          <div class="skeleton skeleton-line w-full"></div>
          <div class="skeleton skeleton-line" style="width:70%"></div>
          <div class="skeleton skeleton-btn w-full"></div>
        </div>
      </div>
      <?php endfor; ?>
    </div>

    <!-- LESSON CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5 cards-loaded" id="lessons-grid">
      <?php foreach($lessons as $lesson): ?>
      <?php
        $thumb     = $lesson['thumbnail'] ?? '';
        $completed = (bool)$lesson['completed'];
        $lessonUrl = "lesson.php?id={$lesson['id']}";
        $typeIcons  = ['video'=>'play-circle','pdf'=>'file-text','book'=>'book-open'];
        $typeLabels = ['video'=>t('video'),'pdf'=>t('pdf'),'book'=>t('book')];
        $typeIcon   = $typeIcons[$lesson['type']] ?? 'book-open';
        $typeLabel  = $typeLabels[$lesson['type']] ?? t('lesson');
      ?>
      <div class="card lesson-card overflow-hidden" data-lesson-id="<?= $lesson['id'] ?>">
        <a href="<?= $lessonUrl ?>" class="block relative overflow-hidden group" style="height:160px;">
          <?php if($thumb): ?>
          <img src="<?= clean($thumb) ?>" alt="" loading="lazy"
               class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          <?php else: ?>
          <div class="thumb-placeholder group-hover:brightness-95 transition">
            <div class="flex flex-col items-center gap-2">
              <div class="w-14 h-14 rounded-2xl bg-white/70 flex items-center justify-center shadow-sm">
                <i data-lucide="<?= $typeIcon ?>" class="w-7 h-7 text-blue-500"></i>
              </div>
              <span class="text-xs font-semibold text-blue-400"><?= $typeLabel ?></span>
            </div>
          </div>
          <?php endif; ?>
          <div class="absolute top-2 <?= $dir==='rtl'?'right-2':'left-2' ?> bg-black/60 text-white text-xs px-2 py-1 rounded-lg font-medium backdrop-blur-sm">
            <?= $lesson['type']==='video' ? '' : ($lesson['type']==='pdf' ? '' : '') ?>
            <?= $typeLabel ?>
          </div>
          <div class="absolute top-2 <?= $dir==='rtl'?'left-2':'right-2' ?> bg-yellow-400 text-yellow-900 text-xs px-2 py-1 rounded-lg font-bold">
            +<?= $lesson['xp_reward'] ?> XP
          </div>
          <?php if($completed): ?>
          <div class="absolute inset-0 bg-green-900/40 flex items-center justify-center">
            <div class="bg-green-500 rounded-full p-2.5 shadow-lg">
              <i data-lucide="check" class="w-7 h-7 text-white"></i>
            </div>
          </div>
          <?php else: ?>
          <div class="absolute inset-0 bg-black/0 group-hover:bg-black/15 transition flex items-center justify-center">
            <div class="w-11 h-11 rounded-full bg-white/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition transform scale-75 group-hover:scale-100 shadow-lg">
              <i data-lucide="<?= $lesson['type']==='video' ? 'play' : 'external-link' ?>" class="w-5 h-5 text-blue-700"></i>
            </div>
          </div>
          <?php endif; ?>
        </a>
        <div class="p-4">
          <a href="<?= $lessonUrl ?>" class="block mb-2 hover:text-blue-600 transition">
            <h3 class="font-bold text-gray-900 line-clamp-2 text-sm leading-snug"><?= clean($lesson['title']) ?></h3>
          </a>
          <?php if($lesson['duration']): ?>
          <p class="text-gray-400 text-xs mb-3 flex items-center gap-1">
            <i data-lucide="clock" class="w-3 h-3"></i>
            <?= $lesson['duration'] ?> min
          </p>
          <?php else: ?>
          <div class="mb-3"></div>
          <?php endif; ?>
          <a href="<?= $lessonUrl ?>" class="open-lesson-btn <?= $completed ? 'completed-btn' : 'incomplete' ?>">
            <?php if($completed): ?>
              <i data-lucide="check-circle-2" class="w-4 h-4 flex-shrink-0"></i>
              <?= t('completed') ?> — <?= t('open_lesson') ?>
            <?php else: ?>
              <i data-lucide="<?= $lesson['type']==='video' ? 'play' : 'book-open' ?>" class="w-4 h-4 flex-shrink-0"></i>
              <?= t('open_lesson') ?>
            <?php endif; ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if(empty($lessons)): ?>
      <div class="col-span-full text-center py-16">
        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
        <p class="text-gray-400"><?= t('no_lessons') ?></p>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif($tab === 'leaderboard'): ?>
    <!-- LEADERBOARD -->
    <div class="max-w-2xl mx-auto">
      <div class="card p-4 md:p-6">
        <h2 class="text-lg md:text-xl font-black text-gray-900 mb-1 flex items-center gap-2">
          <i data-lucide="trophy" class="w-5 h-5 md:w-6 md:h-6 text-yellow-500"></i>
          <?= t('top_students') ?>
        </h2>
        <p class="text-gray-500 text-sm mb-6"><?= clean($gradeName) ?></p>
        <div class="space-y-3">
          <?php foreach($leaderboard as $pos => $student): ?>
          <?php
            $medals = ['','',''];
            $medal  = $medals[$pos] ?? '';
            $isMe   = $student['id'] == $userId;
          ?>
          <div class="flex items-center gap-3 md:gap-4 p-3 md:p-4 rounded-2xl transition <?= $isMe ? 'bg-blue-50 border-2 border-blue-400' : 'bg-gray-50 hover:bg-gray-100' ?>">
            <div class="text-xl w-8 text-center"><?= $medal ?></div>
            <div class="w-9 h-9 md:w-10 md:h-10 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-white text-sm"
                 style="background:<?= levelBadgeColor((int)$student['level']) ?>">
              <?= mb_strtoupper(mb_substr($student['full_name'],0,1)) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-bold text-gray-900 text-sm truncate">
                <?= clean($student['full_name']) ?>
                <?php if($isMe): ?><span class="text-blue-500 font-normal text-xs">(<?= t('you') ?>)</span><?php endif; ?>
              </div>
              <div class="text-gray-500 text-xs"><?= t('level') ?> <?= $student['level'] ?></div>
            </div>
            <div class="font-black text-yellow-600 text-sm"><?= number_format((int)$student['xp_points']) ?> XP</div>
          </div>
          <?php endforeach; ?>
          <?php if(empty($leaderboard)): ?>
          <p class="text-center text-gray-400 py-8"><?= t('no_results') ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php elseif($tab === 'achievements'): ?>
    <!-- ACHIEVEMENTS -->
    <div class="max-w-3xl mx-auto">
      <div class="card p-4 md:p-6">
        <h2 class="text-lg md:text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
          <i data-lucide="star" class="w-5 h-5 md:w-6 md:h-6 text-yellow-500"></i>
          <?= t('achievements') ?>
        </h2>
        <?php
        $completionCount = $db->prepare("SELECT COUNT(*) FROM user_lesson_completions WHERE user_id = :uid");
        $completionCount->execute([':uid' => $userId]);
        $cc = (int)$completionCount->fetchColumn();
        $achievements = [
          ['', t('achievement_1_title'), t('achievement_1_desc'), $cc>=1],
          ['', t('achievement_2_title'), t('achievement_2_desc'), $cc>=5],
          ['', t('achievement_3_title'), t('achievement_3_desc'), $cc>=10],
          ['', t('achievement_4_title'), t('achievement_4_desc'), $userLevel>=3],
          ['', t('achievement_5_title'), t('achievement_5_desc'), $userLevel>=5],
          ['', t('achievement_6_title'), t('achievement_6_desc'), $userXP>=500],
          ['', t('achievement_7_title'), t('achievement_7_desc'), $userXP>=1000],
          ['', t('achievement_8_title'), t('achievement_8_desc'), $userXP>=5000],
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
          <?php foreach($achievements as [$emoji,$title,$desc,$unlocked]): ?>
          <div class="flex items-center gap-3 md:gap-4 p-3 md:p-4 rounded-2xl border <?= $unlocked ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200 bg-gray-50 opacity-60' ?>">
            <div class="text-2xl md:text-3xl flex-shrink-0 <?= $unlocked ? '' : 'grayscale' ?>"><?= $emoji ?></div>
            <div>
              <div class="font-bold text-gray-900 text-sm"><?= $title ?></div>
              <div class="text-gray-500 text-xs"><?= $desc ?></div>
              <?php if($unlocked): ?>
              <div class="text-green-600 text-xs font-semibold mt-1"><?= t('unlocked') ?></div>
              <?php else: ?>
              <div class="text-gray-400 text-xs mt-1"><?= t('locked') ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- MAZAR AI FAB -->
<div id="mazar-fab">
  <div id="fab-tooltip"> MAZAR AI — <?= t('ask_question') ?></div>
  <a href="mazar-ai.php" id="fab-btn" aria-label="MAZAR AI">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
      <path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
    </svg>        
    <span id="fab-online"></span>
  </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="../assets/js/xp-system.js"></script>
<script>
  lucide.createIcons();

  window.MAZAR_XP    = <?= $userXP ?>;
  window.MAZAR_LEVEL = <?= $userLevel ?>;
  window.MAZAR_CSRF  = '<?= csrfToken() ?>';
  window.MAZAR_AJAX  = '../ajax/complete_lesson.php';

  // Mobile sidebar toggle
  function toggleSidebar() {
    const sidebar = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
  }

  // Skeleton loading on tab switch
  function showSkeletons(e, link) {
    var currentSubject = <?= $activeSubjectId ?>;
    var linkHref = link.href;
    var url = new URL(linkHref);
    var targetSubject = parseInt(url.searchParams.get('subject') || '0');
    if (targetSubject === currentSubject) return;

    var grid = document.getElementById('lessons-grid');
    var skeletons = document.getElementById('skeletons-grid');
    if (grid) grid.style.display = 'none';
    if (skeletons) skeletons.classList.remove('hidden');
  }

  function mazarDashboard() {
    return {
      init() {
        <?php if($welcome): ?>
        showToast('<?= t('welcome_toast') ?>', 'success');
        <?php endif; ?>
      }
    };
  }
</script>
</body>
</html>
