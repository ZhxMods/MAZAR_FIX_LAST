<?php
// ============================================================
//  MAZAR — student/quizzes.php
//  All quizzes page — locked until lesson completed
// ============================================================
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$lang    = getCurrentLang();
$dir     = getDirection();
$userId  = (int)$_SESSION[SESS_USER_ID];
$gradeId = (int)$_SESSION[SESS_GRADE];

$db = getDB();

// ── Fetch grade info ───────────────────────────────────────
$lvlStmt = $db->prepare("SELECT name_{$lang} AS name FROM levels WHERE id = ?");
$lvlStmt->execute([$gradeId]);
$gradeName = $lvlStmt->fetchColumn() ?: '—';

// ── Fetch quizzes for student's grade with full status ────
$quizzes = $db->prepare(
    "SELECT q.id,
            q.title_{$lang} AS title_loc, q.title_fr, q.title_ar,
            q.pass_score,
            l.id            AS lesson_id,
            l.title_{$lang} AS lesson_title_loc, l.title_fr AS lesson_title_fr,
            lv.name_{$lang} AS level_name,
            s.name_{$lang}  AS subject_name,
            s.icon          AS subject_icon,
            s.color         AS subject_color,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS q_count,
            IF(ulc.id IS NOT NULL, 1, 0)                                     AS lesson_completed,
            (SELECT uqa.score  FROM user_quiz_attempts uqa WHERE uqa.user_id=? AND uqa.quiz_id=q.id ORDER BY uqa.id DESC LIMIT 1) AS last_score,
            (SELECT uqa.passed FROM user_quiz_attempts uqa WHERE uqa.user_id=? AND uqa.quiz_id=q.id ORDER BY uqa.id DESC LIMIT 1) AS last_passed,
            (SELECT COUNT(*)   FROM user_quiz_attempts uqa WHERE uqa.user_id=? AND uqa.quiz_id=q.id) AS attempt_count
     FROM quizzes q
     JOIN lessons  l  ON l.id  = q.lesson_id
     JOIN levels   lv ON lv.id = l.level_id
     JOIN subjects s  ON s.id  = l.subject_id
     LEFT JOIN user_lesson_completions ulc ON ulc.lesson_id=l.id AND ulc.user_id=?
     WHERE l.published = 1 AND l.level_id = ?
     ORDER BY s.order_num ASC, l.order_num ASC, q.id ASC"
);
$quizzes->execute([$userId, $userId, $userId, $userId, $gradeId]);
$allQuizzes = $quizzes->fetchAll();

// ── Stats ─────────────────────────────────────────────────
$total    = count($allQuizzes);
$passed   = count(array_filter($allQuizzes, fn($q) => $q['last_passed']));
$locked   = count(array_filter($allQuizzes, fn($q) => !$q['lesson_completed']));
$available = $total - $locked;

// Group by subject
$bySubject = [];
foreach ($allQuizzes as $quiz) {
    $key = $quiz['subject_name'];
    $bySubject[$key][] = $quiz;
}

$userXP    = (int)$_SESSION[SESS_XP];
$userLevel = (int)$_SESSION[SESS_LEVEL];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mes Quiz — <?= t('site_name') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <link rel="stylesheet" href="../assets/css/xp-animations.css">
  <style>
    body { font-family: <?= $lang==='ar' ? "'Cairo'" : "'Poppins'" ?>, sans-serif; background:#f1f5f9; }
    .sidebar { background: linear-gradient(180deg, #1e3a8a 0%, #1d4ed8 100%); }
    .card { background:#fff; border-radius:1rem; box-shadow:0 1px 3px rgba(0,0,0,.07); }

    /* Quiz card states */
    .quiz-card { transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
    .quiz-card.available:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(59,130,246,.15); }
    .quiz-card.passed-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(16,185,129,.15); }

    /* Status badges */
    .badge-locked    { background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0; }
    .badge-available { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
    .badge-passed    { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .badge-failed    { background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; }

    /* Action buttons */
    .btn-start {
      display:flex; align-items:center; justify-content:center; gap:.45rem;
      width:100%; padding:.65rem 1rem; border-radius:.75rem;
      font-size:.82rem; font-weight:700; border:none; cursor:pointer;
      text-decoration:none; transition:transform .18s, box-shadow .18s; color:#fff;
    }
    .btn-start.go {
      background:linear-gradient(135deg,#2563eb,#1d4ed8);
      box-shadow:0 4px 14px rgba(37,99,235,.3);
    }
    .btn-start.go:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(37,99,235,.45); }
    .btn-start.retry {
      background:linear-gradient(135deg,#d97706,#b45309);
      box-shadow:0 4px 14px rgba(217,119,6,.3);
    }
    .btn-start.retry:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(217,119,6,.4); }
    .btn-start.success {
      background:linear-gradient(135deg,#059669,#047857);
      box-shadow:0 4px 14px rgba(5,150,105,.3);
    }
    .btn-start.success:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(5,150,105,.4); }
    .btn-start.locked-btn {
      background:#e2e8f0; color:#94a3b8; cursor:not-allowed; box-shadow:none;
    }

    /* Score ring */
    .score-ring { position:relative; width:44px; height:44px; flex-shrink:0; }
    .score-ring svg { transform:rotate(-90deg); }
    .score-ring-text { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:.6rem; font-weight:800; }

    /* Active nav */
    .active-tab { background: rgba(255,255,255,0.15); }
    .xp-bar-fill { transition: width 1s cubic-bezier(.4,0,.2,1); }
    .level-badge { background: linear-gradient(135deg, <?= levelBadgeColor($userLevel) ?>, <?= levelBadgeColor(min($userLevel+1,10)) ?>); }

    /* Entrance animation */
    @keyframes fadeUp {
      from { opacity:0; transform:translateY(16px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .quiz-card { animation: fadeUp .4s ease both; }
    <?php for($i=1;$i<=20;$i++): ?>
    .quiz-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i-1)*.05 ?>s; }
    <?php endfor; ?>

    /* Locked overlay shimmer */
    .quiz-card.locked-card::after {
      content:'';
      position:absolute; inset:0;
      background:rgba(241,245,249,.55);
      pointer-events:none;
    }

    @media (max-width:640px) {
      .sidebar { display:none; }
    }
  </style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- ─── SIDEBAR ─── -->
<aside class="sidebar w-64 flex-shrink-0 flex flex-col h-full overflow-y-auto hidden md:flex">
  <div class="px-6 py-6 border-b border-white/10">
    <a href="/" class="flex items-center gap-3">
      <img src="../assets/images/mazar.avif" alt="MAZAR" class="w-10 h-10 rounded-xl object-contain">
      <span class="text-white font-black text-xl"><?= t('site_name') ?></span>
    </a>
  </div>

  <div class="px-6 py-5 border-b border-white/10">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-11 h-11 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
        <?= mb_strtoupper(mb_substr($_SESSION[SESS_USERNAME] ?? 'S', 0, 1)) ?>
      </div>
      <div class="overflow-hidden">
        <div class="text-white font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION[SESS_USERNAME] ?? '') ?></div>
        <div class="text-blue-200 text-xs truncate"><?= htmlspecialchars($gradeName) ?></div>
      </div>
    </div>
    <div class="flex items-center justify-between mb-2">
      <span class="text-blue-200 text-xs"><?= t('level') ?> <span class="text-white font-bold"><?= $userLevel ?></span></span>
      <span class="text-yellow-300 text-xs font-bold"><?= $userXP ?> XP</span>
    </div>
    <div class="bg-white/20 rounded-full h-2">
      <div class="xp-bar-fill bg-yellow-400 h-2 rounded-full" style="width:<?= xpProgressPercent($userXP,$userLevel) ?>%"></div>
    </div>
    <div class="text-right mt-1">
      <span class="text-blue-300 text-xs"><?= round(xpProgressPercent($userXP,$userLevel)) ?>% → <?= t('level') ?> <?= $userLevel+1 ?></span>
    </div>
  </div>

  <nav class="px-4 py-4 flex-1">
    <a href="dashboard.php?tab=lessons" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1">
      <i data-lucide="book-open" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('my_lessons') ?></span>
    </a>
    <a href="quizzes.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white font-semibold transition mb-1 active-tab">
      <i data-lucide="help-circle" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium">Mes Quiz</span>
    </a>
    <a href="dashboard.php?tab=leaderboard" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1">
      <i data-lucide="trophy" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('leaderboard') ?></span>
    </a>
    <a href="dashboard.php?tab=achievements" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/10 transition mb-1">
      <i data-lucide="star" class="w-5 h-5 flex-shrink-0"></i>
      <span class="text-sm font-medium"><?= t('achievements') ?></span>
    </a>
    <div class="mt-4 border-t border-white/10 pt-4">
      <a href="mazar-ai.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/70 hover:text-white hover:bg-white/10 transition">
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
      <a href="?lang=<?= $l ?>" class="flex-1 text-center py-1 rounded-lg text-xs font-bold transition <?= $lang===$l ? 'bg-white text-blue-700' : 'text-white/60 hover:bg-white/10' ?>"><?= strtoupper($l) ?></a>
      <?php endforeach; ?>
    </div>
    <a href="../logout.php" class="flex items-center gap-2 text-white/60 hover:text-white text-sm transition px-2 py-2 rounded-xl hover:bg-white/10">
      <i data-lucide="log-out" class="w-4 h-4"></i> <?= t('logout') ?>
    </a>
  </div>
</aside>

<!-- ─── MAIN ─── -->
<div class="flex-1 flex flex-col overflow-hidden">

  <!-- Header -->
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
    <div>
      <h1 class="font-black text-gray-900 text-lg flex items-center gap-2">
        <i data-lucide="help-circle" class="w-5 h-5 text-purple-600"></i>
        Mes Quiz
      </h1>
      <p class="text-gray-500 text-xs"><?= htmlspecialchars($gradeName) ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-2">
        <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
        <span class="font-bold text-yellow-700 text-sm"><?= $userXP ?> XP</span>
      </div>
      <div class="level-badge flex items-center gap-2 rounded-xl px-4 py-2">
        <i data-lucide="shield" class="w-4 h-4 text-white"></i>
        <span class="font-bold text-white text-sm">Niv. <?= $userLevel ?></span>
      </div>
    </div>
  </header>

  <main class="flex-1 overflow-y-auto p-6">

    <!-- Stats bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-7">
      <?php $statsCards = [
        ['Total Quiz', $total,     'help-circle','#8B5CF6'],
        ['Disponibles', $available,'play-circle', '#3B82F6'],
        ['Réussis',    $passed,    'check-circle','#10B981'],
        ['Verrouillés',$locked,    'lock',        '#94A3B8'],
      ]; foreach($statsCards as [$lbl,$val,$ico,$clr]): ?>
      <div class="card px-5 py-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?= $clr ?>18">
          <i data-lucide="<?= $ico ?>" class="w-5 h-5" style="color:<?= $clr ?>"></i>
        </div>
        <div>
          <div class="text-xl font-black text-gray-900"><?= $val ?></div>
          <div class="text-gray-500 text-xs"><?= $lbl ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- XP Reward info banner -->
    <div class="mb-6 p-4 bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-2xl flex items-center gap-3">
      <div class="w-10 h-10 bg-yellow-400 rounded-xl flex items-center justify-center flex-shrink-0">
        <i data-lucide="zap" class="w-5 h-5 text-yellow-900"></i>
      </div>
      <div>
        <div class="font-bold text-gray-800 text-sm">+<?= XP_QUIZ ?> XP par quiz réussi</div>
        <div class="text-gray-500 text-xs">Terminez d'abord la leçon associée pour débloquer le quiz. XP accordés une seule fois par quiz.</div>
      </div>
    </div>

    <?php if (empty($allQuizzes)): ?>
    <div class="card p-16 text-center">
      <i data-lucide="help-circle" class="w-14 h-14 text-gray-200 mx-auto mb-4"></i>
      <p class="text-gray-400 font-semibold">Aucun quiz disponible pour votre niveau pour le moment.</p>
      <a href="dashboard.php" class="mt-4 inline-flex items-center gap-2 text-blue-600 text-sm font-semibold hover:underline">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux cours
      </a>
    </div>
    <?php endif; ?>

    <!-- Quiz groups by subject -->
    <?php foreach ($bySubject as $subjectName => $quizList): 
      $firstQuiz = $quizList[0];
      $subjectColor = $firstQuiz['subject_color'] ?: '#3B82F6';
      $subjectIcon  = $firstQuiz['subject_icon']  ?: 'BookOpen';
    ?>
    <div class="mb-8">
      <!-- Subject header -->
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?= htmlspecialchars($subjectColor) ?>18; border:1.5px solid <?= htmlspecialchars($subjectColor) ?>30">
          <i data-lucide="<?= htmlspecialchars(strtolower($subjectIcon)) ?>" class="w-4 h-4" style="color:<?= htmlspecialchars($subjectColor) ?>"></i>
        </div>
        <h2 class="font-black text-gray-800 text-base"><?= htmlspecialchars($subjectName) ?></h2>
        <span class="bg-gray-100 text-gray-500 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($quizList) ?> quiz</span>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($quizList as $quiz):
          $qTitle      = $quiz['title_loc'] ?: $quiz['title_fr'];
          $lessonTitle = $quiz['lesson_title_loc'] ?: $quiz['lesson_title_fr'];
          $isLocked    = !$quiz['lesson_completed'];
          $hasPassed   = $quiz['last_passed'];
          $hasFailed   = !is_null($quiz['last_score']) && !$quiz['last_passed'];
          $hasAttempted = $quiz['attempt_count'] > 0;
          $score       = $quiz['last_score'];

          // Determine card state
          if ($isLocked)        { $state = 'locked';    $cardClass = 'locked-card'; }
          elseif ($hasPassed)   { $state = 'passed';    $cardClass = 'passed-card'; }
          elseif ($hasFailed)   { $state = 'failed';    $cardClass = 'available'; }
          else                  { $state = 'available'; $cardClass = 'available'; }

          // Score ring calculation
          $scoreCircum = 2 * M_PI * 18; // r=18
          $scoreDash   = $hasAttempted ? round(($score / 100) * $scoreCircum, 1) : 0;
          $scoreColor  = $hasPassed ? '#10b981' : ($hasFailed ? '#ef4444' : '#e2e8f0');
        ?>
        <div class="card quiz-card <?= $cardClass ?> p-5">

          <!-- Card top: badge + score ring -->
          <div class="flex items-start justify-between mb-3">
            <?php if ($isLocked): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full badge-locked">
              <i data-lucide="lock" style="width:11px;height:11px;"></i> Verrouillé
            </span>
            <?php elseif ($hasPassed): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full badge-passed">
              <i data-lucide="check-circle" style="width:11px;height:11px;"></i> Réussi
            </span>
            <?php elseif ($hasFailed): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full badge-failed">
              <i data-lucide="x-circle" style="width:11px;height:11px;"></i> À retenter
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full badge-available">
              <i data-lucide="play" style="width:11px;height:11px;"></i> Disponible
            </span>
            <?php endif; ?>

            <!-- Score ring (shown if attempted) -->
            <?php if ($hasAttempted): ?>
            <div class="score-ring">
              <svg width="44" height="44" viewBox="0 0 44 44">
                <circle cx="22" cy="22" r="18" fill="none" stroke="#e2e8f0" stroke-width="4"/>
                <circle cx="22" cy="22" r="18" fill="none"
                        stroke="<?= $scoreColor ?>" stroke-width="4"
                        stroke-dasharray="<?= $scoreDash ?> <?= $scoreCircum ?>"
                        stroke-linecap="round"/>
              </svg>
              <div class="score-ring-text" style="color:<?= $scoreColor ?>"><?= $score ?>%</div>
            </div>
            <?php else: ?>
            <div class="bg-purple-50 text-purple-600 font-black text-xs px-2 py-1.5 rounded-xl">
              +<?= XP_QUIZ ?> XP
            </div>
            <?php endif; ?>
          </div>

          <!-- Title -->
          <h3 class="font-bold text-gray-900 text-sm leading-snug mb-1 <?= $isLocked ? 'text-gray-400' : '' ?>">
            <?= htmlspecialchars($qTitle) ?>
          </h3>

          <!-- Lesson link info -->
          <p class="text-gray-400 text-xs mb-3 flex items-center gap-1.5 truncate">
            <i data-lucide="book-open" style="width:11px;height:11px;flex-shrink:0;"></i>
            <?= htmlspecialchars($lessonTitle) ?>
          </p>

          <!-- Meta row -->
          <div class="flex items-center gap-3 mb-4 text-xs text-gray-500">
            <span class="flex items-center gap-1">
              <i data-lucide="help-circle" style="width:11px;height:11px;"></i>
              <?= $quiz['q_count'] ?> question<?= $quiz['q_count'] != 1 ? 's' : '' ?>
            </span>
            <span class="flex items-center gap-1">
              <i data-lucide="target" style="width:11px;height:11px;"></i>
              Min. <?= $quiz['pass_score'] ?>%
            </span>
            <?php if ($quiz['attempt_count'] > 0): ?>
            <span class="flex items-center gap-1">
              <i data-lucide="refresh-cw" style="width:11px;height:11px;"></i>
              <?= $quiz['attempt_count'] ?> tentative<?= $quiz['attempt_count'] > 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
          </div>

          <!-- Action button -->
          <?php if ($isLocked): ?>
          <a href="lesson.php?id=<?= $quiz['lesson_id'] ?>" class="btn-start locked-btn">
            <i data-lucide="lock" style="width:14px;height:14px;"></i>
            Terminer la leçon d'abord
          </a>
          <?php elseif ($hasPassed): ?>
          <a href="quiz.php?id=<?= $quiz['id'] ?>" class="btn-start success">
            <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
            Refaire le quiz
          </a>
          <?php elseif ($hasFailed): ?>
          <a href="quiz.php?id=<?= $quiz['id'] ?>" class="btn-start retry">
            <i data-lucide="zap" style="width:14px;height:14px;"></i>
            Retenter — +<?= XP_QUIZ ?> XP
          </a>
          <?php else: ?>
          <a href="quiz.php?id=<?= $quiz['id'] ?>" class="btn-start go">
            <i data-lucide="play" style="width:14px;height:14px;"></i>
            Commencer le quiz — +<?= XP_QUIZ ?> XP
          </a>
          <?php endif; ?>

          <!-- Lock hint -->
          <?php if ($isLocked): ?>
          <p class="text-center text-gray-400 text-xs mt-2 flex items-center justify-center gap-1">
            <i data-lucide="arrow-right" style="width:10px;height:10px;"></i>
            Cours requis : <a href="lesson.php?id=<?= $quiz['lesson_id'] ?>" class="text-blue-500 hover:underline font-medium truncate max-w-[120px] inline-block align-bottom"><?= htmlspecialchars(mb_substr($lessonTitle, 0, 25)) ?><?= mb_strlen($lessonTitle) > 25 ? '…' : '' ?></a>
          </p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

  </main>
</div>

<script>lucide.createIcons();</script>
</body>
</html>