<?php
// ============================================================
//  MAZAR — student/quiz.php
//  Quiz taking interface + results display
//  Requires: lesson completed, quiz has questions
// ============================================================
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$lang   = getCurrentLang();
$dir    = getDirection();
$userId = (int)$_SESSION[SESS_USER_ID];

$quizId = (int)($_GET['id'] ?? 0);
if (!$quizId) redirect('quizzes.php');

$db = getDB();

// ── Fetch quiz with lesson/subject/level info ──────────────
$stmt = $db->prepare(
    "SELECT q.id, q.pass_score,
            q.title_{$lang}  AS title_loc, q.title_fr, q.title_ar,
            l.id             AS lesson_id,
            l.title_{$lang}  AS lesson_title_loc, l.title_fr AS lesson_title_fr,
            lv.name_{$lang}  AS level_name,
            s.name_{$lang}   AS subject_name,
            s.icon           AS subject_icon,
            s.color          AS subject_color,
            IF(ulc.id IS NOT NULL, 1, 0) AS lesson_completed
     FROM quizzes q
     JOIN lessons  l   ON l.id  = q.lesson_id
     JOIN levels   lv  ON lv.id = l.level_id
     JOIN subjects s   ON s.id  = l.subject_id
     LEFT JOIN user_lesson_completions ulc ON ulc.lesson_id=l.id AND ulc.user_id=?
     WHERE q.id = ? AND l.published = 1"
);
$stmt->execute([$userId, $quizId]);
$quiz = $stmt->fetch();

if (!$quiz) redirect('quizzes.php');

// ── Lesson must be completed ───────────────────────────────
if (!$quiz['lesson_completed']) {
    redirect('lesson.php?id=' . $quiz['lesson_id']);
}

// ── Fetch questions + options ──────────────────────────────
$qStmt = $db->prepare(
    "SELECT id, question_{$lang} AS question_loc, question_fr
     FROM quiz_questions WHERE quiz_id=? ORDER BY order_num ASC, id ASC"
);
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

if (empty($questions)) redirect('quizzes.php');

// Fetch all options keyed by question_id
$oStmt = $db->prepare(
    "SELECT qo.id, qo.question_id,
            qo.option_{$lang} AS option_loc, qo.option_fr,
            qo.is_correct
     FROM quiz_options qo
     JOIN quiz_questions qq ON qq.id = qo.question_id
     WHERE qq.quiz_id = ?
     ORDER BY qo.id ASC"
);
$oStmt->execute([$quizId]);
$allOptions = [];
foreach ($oStmt->fetchAll() as $opt) {
    $allOptions[$opt['question_id']][] = $opt;
}

// ── Handle submission ──────────────────────────────────────
$submitted    = false;
$score        = 0;
$passed       = false;
$answers      = [];  // [question_id => chosen_option_id]
$correctCount = 0;
$xpAwarded    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['submit_quiz'])) {
    $submitted = true;
    $totalQ    = count($questions);

    foreach ($questions as $q) {
        $chosenOptId = (int)($_POST['answer_' . $q['id']] ?? 0);
        $answers[$q['id']] = $chosenOptId;
        // Check correctness
        foreach ($allOptions[$q['id']] as $opt) {
            if ($opt['id'] == $chosenOptId && $opt['is_correct']) {
                $correctCount++;
                break;
            }
        }
    }

    $score  = $totalQ > 0 ? round(($correctCount / $totalQ) * 100) : 0;
    $passed = $score >= $quiz['pass_score'];

    // Check if this is first pass ever (for XP award)
    $prevPassed = $db->prepare(
        "SELECT COUNT(*) FROM user_quiz_attempts WHERE user_id=? AND quiz_id=? AND passed=1"
    );
    $prevPassed->execute([$userId, $quizId]);
    $wasPreviouslyPassed = (bool)$prevPassed->fetchColumn();

    // Record attempt
    $db->prepare(
        "INSERT INTO user_quiz_attempts (user_id, quiz_id, score, passed) VALUES (?,?,?,?)"
    )->execute([$userId, $quizId, $score, $passed ? 1 : 0]);

    // Award XP if passed for first time
    if ($passed && !$wasPreviouslyPassed) {
        awardXP($userId, XP_QUIZ, "Quiz #{$quizId} passed");
        $xpAwarded = true;
        logActivity($userId, 'quiz_pass', "Passed quiz #{$quizId} with {$score}%");
    } else {
        logActivity($userId, 'quiz_attempt', "Attempted quiz #{$quizId}: {$score}%");
    }
}

// ── Previous attempt info (before submission) ─────────────
$prevAttempt = null;
if (!$submitted) {
    $paStmt = $db->prepare(
        "SELECT score, passed, created_at FROM user_quiz_attempts
         WHERE user_id=? AND quiz_id=? ORDER BY id DESC LIMIT 1"
    );
    $paStmt->execute([$userId, $quizId]);
    $prevAttempt = $paStmt->fetch();

    $attemptCount = $db->prepare(
        "SELECT COUNT(*) FROM user_quiz_attempts WHERE user_id=? AND quiz_id=?"
    );
    $attemptCount->execute([$userId, $quizId]);
    $totalAttempts = (int)$attemptCount->fetchColumn();
}

// ── Misc ───────────────────────────────────────────────────
$title       = $quiz['title_loc'] ?: $quiz['title_fr'];
$lessonTitle = $quiz['lesson_title_loc'] ?: $quiz['lesson_title_fr'];
$userXP      = (int)$_SESSION[SESS_XP];
$userLevel   = (int)$_SESSION[SESS_LEVEL];
$progressPct = xpProgressPercent($userXP, $userLevel);

// ── FIX BUG 1: REMOVED shuffle() ──────────────────────────
// Options are shown in consistent DB order (by id ASC).
// This ensures the letter (A,B,C,D) assigned during quiz taking
// matches the letter shown in the results review section.
// Previously, shuffle() randomized display order but the review
// used original DB order, causing letter mismatches.
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> — <?= t('site_name') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <link rel="stylesheet" href="../assets/css/xp-animations.css">
  <style>
    body { font-family: <?= $lang==='ar' ? "'Cairo'" : "'Poppins'" ?>, sans-serif; background:#f1f5f9; color:#1e293b; }

    /* ── Top Nav ── */
    .top-nav { background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%); }

    /* ── Progress bar ── */
    .quiz-progress-bar { height:6px; background:#e2e8f0; border-radius:99px; overflow:hidden; }
    .quiz-progress-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#3b82f6,#1d4ed8); transition:width .5s cubic-bezier(.4,0,.2,1); }

    /* ── Question card ── */
    .question-card {
      background:#fff; border-radius:1.25rem;
      box-shadow:0 2px 8px rgba(0,0,0,.07);
      border:2px solid transparent;
      transition:border-color .2s, box-shadow .2s;
    }
    .question-card.active { border-color:#3b82f6; box-shadow:0 8px 32px rgba(59,130,246,.15); }

    /* ── Option Buttons ── */
    .option-btn {
      width:100%; text-align:<?= $dir==='rtl'?'right':'left' ?>;
      padding:.875rem 1.25rem; border-radius:.875rem;
      border:2px solid #e2e8f0; background:#f8fafc;
      cursor:pointer; transition:all .18s; font-family:inherit;
      display:flex; align-items:center; gap:.875rem;
      font-size:.88rem; font-weight:600; color:#374151;
    }
    .option-btn:hover:not(:disabled) { border-color:#3b82f6; background:#eff6ff; color:#1e40af; }
    .option-btn.selected { border-color:#3b82f6; background:#eff6ff; color:#1e40af; }
    .option-btn.correct  { border-color:#10b981; background:#f0fdf4; color:#065f46; }
    .option-btn.wrong    { border-color:#ef4444; background:#fef2f2; color:#991b1b; }
    .option-btn.missed   { border-color:#f59e0b; background:#fffbeb; color:#92400e; }
    .option-btn:disabled { cursor:default; }

    /* Option letter bubble */
    .opt-letter {
      width:32px; height:32px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:.75rem; font-weight:800; flex-shrink:0;
      background:#e2e8f0; color:#64748b;
      transition:background .18s, color .18s;
    }
    .option-btn.selected .opt-letter { background:#3b82f6; color:#fff; }
    .option-btn.correct  .opt-letter { background:#10b981; color:#fff; }
    .option-btn.wrong    .opt-letter { background:#ef4444; color:#fff; }
    .option-btn.missed   .opt-letter { background:#f59e0b; color:#fff; }

    /* ── Question number nav ── */
    .q-nav-dot {
      width:32px; height:32px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:.72rem; font-weight:800; cursor:pointer;
      border:2px solid #e2e8f0; background:#fff; color:#64748b;
      transition:all .18s;
    }
    .q-nav-dot:hover  { border-color:#3b82f6; color:#3b82f6; }
    .q-nav-dot.answered { background:#3b82f6; border-color:#3b82f6; color:#fff; }
    .q-nav-dot.current  { border-color:#1d4ed8; box-shadow:0 0 0 3px rgba(59,130,246,.25); }
    .q-nav-dot.correct  { background:#10b981; border-color:#10b981; color:#fff; }
    .q-nav-dot.wrong    { background:#ef4444; border-color:#ef4444; color:#fff; }

    /* ── Timer ── */
    .timer-pill {
      display:inline-flex; align-items:center; gap:.4rem;
      padding:.35rem .9rem; border-radius:999px;
      font-size:.78rem; font-weight:800; font-variant-numeric:tabular-nums;
    }
    .timer-normal  { background:#dbeafe; color:#1e40af; }
    .timer-warning { background:#fef3c7; color:#92400e; animation:timerPulse .8s ease-in-out infinite; }
    .timer-danger  { background:#fee2e2; color:#991b1b; animation:timerPulse .5s ease-in-out infinite; }
    @keyframes timerPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }

    /* ── Results ── */
    .score-ring-wrap { position:relative; width:140px; height:140px; margin:0 auto; }
    .score-ring-svg  { transform:rotate(-90deg); }
    .score-ring-bg   { fill:none; stroke:#e2e8f0; stroke-width:10; }
    .score-ring-fill { fill:none; stroke-width:10; stroke-linecap:round;
                       transition:stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1) .3s; }
    .score-ring-text { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
                       text-align:center; }

    /* ── Result option review ── */
    .review-correct { border-color:#10b981; background:#f0fdf4; }
    .review-wrong   { border-color:#ef4444; background:#fef2f2; }

    /* ── Animations ── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .fade-up   { animation:fadeUp .4s ease both; }
    .fade-up-d1{ animation-delay:.1s; }
    .fade-up-d2{ animation-delay:.2s; }
    .fade-up-d3{ animation-delay:.3s; }

    @keyframes resultPop { from{opacity:0;transform:scale(.7) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .result-pop { animation:resultPop .6s cubic-bezier(.34,1.56,.64,1) both; }

    /* ── Sticky sidebar ── */
    .sticky-sidebar { position:sticky; top:1.5rem; }

    /* ── Submit btn ── */
    .submit-btn {
      width:100%; padding:1rem; border-radius:.875rem; border:none;
      font-family:inherit; font-size:.95rem; font-weight:800; cursor:pointer;
      display:flex; align-items:center; justify-content:center; gap:.6rem;
      transition:all .22s cubic-bezier(.34,1.56,.64,1);
    }
    .submit-btn.ready {
      background:linear-gradient(135deg,#2563eb,#1d4ed8);
      color:#fff; box-shadow:0 6px 24px rgba(37,99,235,.4);
    }
    .submit-btn.ready:hover { transform:translateY(-2px); box-shadow:0 10px 32px rgba(37,99,235,.55); }
    .submit-btn.disabled-btn { background:#e2e8f0; color:#94a3b8; cursor:not-allowed; }

    /* ── Toast ── */
    #toast-container { position:fixed; top:20px; <?= $dir==='rtl'?'left':'right' ?>:20px; z-index:9999; }
  </style>
</head>
<body dir="<?= $dir ?>">

<div id="toast-container" class="space-y-2"></div>

<!-- ══ TOP NAV ══════════════════════════════════════════════ -->
<nav class="top-nav px-4 sm:px-6 py-3 flex items-center gap-3 sticky top-0 z-50 shadow-lg">
  <a href="quizzes.php"
     class="flex items-center gap-1.5 text-white/80 hover:text-white text-sm font-semibold transition bg-white/10 hover:bg-white/20 px-3 py-2 rounded-xl flex-shrink-0">
    <i data-lucide="arrow-<?= $dir==='rtl'?'right':'left' ?>" class="w-4 h-4"></i>
    <span class="hidden sm:inline">Quiz</span>
  </a>

  <div class="flex-1 min-w-0">
    <div class="flex items-center gap-2 text-white/70 text-xs sm:text-sm">
      <span class="text-white font-bold truncate"><?= htmlspecialchars($title) ?></span>
    </div>
    <div class="text-white/50 text-xs truncate hidden sm:block"><?= htmlspecialchars($quiz['subject_name']) ?> · <?= htmlspecialchars($lessonTitle) ?></div>
  </div>

  <?php if (!$submitted): ?>
  <!-- Timer display -->
  <div id="timer-wrap" class="flex-shrink-0">
    <span id="timer-pill" class="timer-pill timer-normal">
      <i data-lucide="clock" class="w-3.5 h-3.5"></i>
      <span id="timer-display">--:--</span>
    </span>
  </div>
  <?php endif; ?>

  <!-- XP badge -->
  <div class="flex items-center gap-2 bg-yellow-400/20 border border-yellow-400/30 rounded-xl px-3 py-1.5 flex-shrink-0">
    <i data-lucide="zap" class="w-3.5 h-3.5 text-yellow-300"></i>
    <span class="text-yellow-200 font-bold text-xs sm:text-sm"><?= $userXP ?> XP</span>
  </div>
</nav>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

  <?php if ($submitted): ?>
  <!-- ════════════════════════════════════════════════
       RESULTS VIEW
  ════════════════════════════════════════════════ -->

  <?php
    $circumference = 2 * M_PI * 54; // radius=54
    $offset = $circumference - ($score / 100 * $circumference);
    $ringColor = $passed ? '#10b981' : ($score >= 40 ? '#f59e0b' : '#ef4444');
  ?>

  <div class="max-w-3xl mx-auto">

    <!-- Score Card -->
    <div class="bg-white rounded-3xl shadow-lg overflow-hidden mb-6 result-pop fade-up">
      <!-- Header band -->
      <div class="h-2" style="background:<?= $ringColor ?>"></div>
      <div class="p-8 text-center">
        <!-- Ring score -->
        <div class="score-ring-wrap mb-4">
          <svg class="score-ring-svg" width="140" height="140" viewBox="0 0 140 140">
            <circle class="score-ring-bg" cx="70" cy="70" r="54"/>
            <circle class="score-ring-fill"
                    cx="70" cy="70" r="54"
                    stroke="<?= $ringColor ?>"
                    stroke-dasharray="<?= $circumference ?>"
                    stroke-dashoffset="<?= $circumference ?>"
                    id="result-ring"/>
          </svg>
          <div class="score-ring-text">
            <div class="text-3xl font-black text-gray-900" id="score-count">0</div>
            <div class="text-xs font-semibold text-gray-400">/ 100</div>
          </div>
        </div>

        <!-- Pass/Fail badge -->
        <?php if ($passed): ?>
        <div class="inline-flex items-center gap-2 bg-green-100 text-green-700 font-black text-lg px-5 py-2 rounded-full mb-3">
          🎉 Félicitations ! Quiz réussi !
        </div>
        <?php else: ?>
        <div class="inline-flex items-center gap-2 bg-red-100 text-red-700 font-black text-lg px-5 py-2 rounded-full mb-3">
          😔 Quiz non réussi
        </div>
        <?php endif; ?>

        <p class="text-gray-500 text-sm mb-5">
          <strong class="text-gray-800"><?= $correctCount ?></strong> bonne<?= $correctCount > 1 ? 's' : '' ?> réponse<?= $correctCount > 1 ? 's' : '' ?> sur <strong class="text-gray-800"><?= count($questions) ?></strong>
          · Score minimum : <strong><?= $quiz['pass_score'] ?>%</strong>
        </p>

        <!-- XP awarded -->
        <?php if ($xpAwarded): ?>
        <div class="inline-flex items-center gap-2 bg-yellow-50 border border-yellow-200 text-yellow-700 font-bold text-sm px-4 py-2.5 rounded-xl mb-5">
          <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
          +<?= XP_QUIZ ?> XP gagnés ! Nouveau total : <?= $userXP ?> XP
        </div>
        <?php endif; ?>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-3 mb-6">
          <?php
            $statsData = [
              ['✅', $correctCount, 'Correctes'],
              ['❌', count($questions) - $correctCount, 'Incorrectes'],
              ['📊', $score . '%', 'Score'],
            ];
          ?>
          <?php foreach($statsData as [$ico,$val,$lbl]): ?>
          <div class="bg-gray-50 rounded-2xl p-3">
            <div class="text-xl mb-1"><?= $ico ?></div>
            <div class="font-black text-gray-900 text-xl"><?= $val ?></div>
            <div class="text-gray-400 text-xs"><?= $lbl ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-3">
          <a href="quiz.php?id=<?= $quizId ?>"
             class="flex-1 flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            Réessayer le quiz
          </a>
          <a href="quizzes.php"
             class="flex-1 flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition">
            <i data-lucide="grid" class="w-4 h-4"></i>
            Tous les quiz
          </a>
          <a href="lesson.php?id=<?= $quiz['lesson_id'] ?>"
             class="flex-1 flex items-center justify-center gap-2 bg-purple-100 hover:bg-purple-200 text-purple-700 font-bold py-3 rounded-xl transition">
            <i data-lucide="book-open" class="w-4 h-4"></i>
            Revoir la leçon
          </a>
        </div>
      </div>
    </div>

    <!-- Review Questions -->
    <div class="space-y-4 fade-up fade-up-d1">
      <h2 class="font-black text-gray-900 text-lg flex items-center gap-2">
        <i data-lucide="clipboard-list" class="w-5 h-5 text-blue-600"></i>
        Correction détaillée
      </h2>

      <?php foreach ($questions as $qi => $q): ?>
      <?php
        $opts        = $allOptions[$q['id']] ?? [];
        $chosenId    = $answers[$q['id']] ?? 0;
        $qText       = $q['question_loc'] ?: $q['question_fr'];
        $isCorrectQ  = false;
        foreach ($opts as $opt) {
            if ($opt['id'] == $chosenId && $opt['is_correct']) { $isCorrectQ = true; break; }
        }
        $letters = ['A','B','C','D'];
      ?>
      <div class="bg-white rounded-2xl p-5 border-2 <?= $isCorrectQ ? 'border-green-200' : 'border-red-200' ?>">
        <div class="flex items-start gap-3 mb-4">
          <div class="w-8 h-8 rounded-xl flex-shrink-0 flex items-center justify-center font-black text-sm text-white
               <?= $isCorrectQ ? 'bg-green-500' : 'bg-red-500' ?>">
            <?= $qi + 1 ?>
          </div>
          <div>
            <p class="font-semibold text-gray-900 text-sm leading-relaxed"><?= htmlspecialchars($qText) ?></p>
            <span class="text-xs font-bold <?= $isCorrectQ ? 'text-green-600' : 'text-red-600' ?>">
              <?= $isCorrectQ ? '✓ Correct' : '✗ Incorrect' ?>
            </span>
          </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-2">
          <?php foreach ($opts as $oi => $opt):
            $optText  = $opt['option_loc'] ?: $opt['option_fr'];
            $isChosen = ($opt['id'] == $chosenId);
            $isCrt    = $opt['is_correct'];
            if ($isCrt)              $cls = 'correct';
            elseif ($isChosen)       $cls = 'wrong';
            else                     $cls = '';
          ?>
          <div class="option-btn <?= $cls ?>" style="cursor:default;">
            <div class="opt-letter <?= $cls ?>"><?= $letters[$oi] ?? chr(65+$oi) ?></div>
            <span class="flex-1"><?= htmlspecialchars($optText) ?></span>
            <?php if ($isCrt): ?>
              <i data-lucide="check-circle" class="w-4 h-4 text-green-500 flex-shrink-0"></i>
            <?php elseif ($isChosen): ?>
              <i data-lucide="x-circle" class="w-4 h-4 text-red-500 flex-shrink-0"></i>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>

  <?php else: ?>
  <!-- ════════════════════════════════════════════════
       QUIZ TAKING VIEW
  ════════════════════════════════════════════════ -->

  <!-- Quiz header info -->
  <div class="bg-white rounded-2xl p-4 sm:p-5 mb-5 flex flex-col sm:flex-row sm:items-center gap-4 fade-up">
    <div class="flex items-center gap-3 flex-1 min-w-0">
      <div class="w-11 h-11 rounded-xl flex-shrink-0 flex items-center justify-center" style="background:<?= htmlspecialchars($quiz['subject_color']) ?>20">
        <i data-lucide="<?= htmlspecialchars($quiz['subject_icon']) ?>" class="w-5 h-5" style="color:<?= htmlspecialchars($quiz['subject_color']) ?>"></i>
      </div>
      <div class="min-w-0">
        <h1 class="font-black text-gray-900 text-lg leading-tight truncate"><?= htmlspecialchars($title) ?></h1>
        <p class="text-gray-400 text-xs"><?= htmlspecialchars($quiz['subject_name']) ?> · <?= htmlspecialchars($lessonTitle) ?></p>
      </div>
    </div>
    <div class="flex items-center gap-3 flex-shrink-0 flex-wrap">
      <div class="flex items-center gap-1.5 bg-blue-50 text-blue-700 text-xs font-bold px-3 py-1.5 rounded-xl">
        <i data-lucide="help-circle" class="w-3.5 h-3.5"></i>
        <?= count($questions) ?> questions
      </div>
      <div class="flex items-center gap-1.5 bg-yellow-50 text-yellow-700 text-xs font-bold px-3 py-1.5 rounded-xl">
        <i data-lucide="target" class="w-3.5 h-3.5"></i>
        Min. <?= $quiz['pass_score'] ?>%
      </div>
      <div class="flex items-center gap-1.5 bg-purple-50 text-purple-700 text-xs font-bold px-3 py-1.5 rounded-xl">
        <i data-lucide="zap" class="w-3.5 h-3.5"></i>
        +<?= XP_QUIZ ?> XP
      </div>
      <?php if (!empty($prevAttempt)): ?>
      <div class="flex items-center gap-1.5 bg-gray-50 text-gray-600 text-xs font-semibold px-3 py-1.5 rounded-xl border border-gray-200">
        <i data-lucide="history" class="w-3.5 h-3.5"></i>
        Dernier : <?= $prevAttempt['score'] ?>%
        <?= $prevAttempt['passed'] ? ' ✅' : ' ❌' ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <form method="POST" id="quiz-form" onsubmit="return handleSubmit(event)">
    <?= csrfField() ?>
    <input type="hidden" name="submit_quiz" value="1">

    <div class="flex flex-col lg:flex-row gap-5">

      <!-- ── Questions Column ── -->
      <div class="flex-1 space-y-4" id="questions-container">

        <?php foreach ($questions as $qi => $q):
          $qText  = $q['question_loc'] ?: $q['question_fr'];
          $opts   = $allOptions[$q['id']] ?? [];
          $letters = ['A','B','C','D'];
        ?>
        <div class="question-card p-5 sm:p-6 fade-up" id="qcard-<?= $q['id'] ?>"
             style="animation-delay:<?= $qi * 0.08 ?>s">

          <!-- Question number + text -->
          <div class="flex items-start gap-3 mb-4">
            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-sm flex-shrink-0">
              <?= $qi + 1 ?>
            </div>
            <p class="font-semibold text-gray-900 leading-relaxed pt-1"><?= htmlspecialchars($qText) ?></p>
          </div>

          <!-- Options -->
          <div class="grid sm:grid-cols-2 gap-2.5">
            <?php foreach ($opts as $oi => $opt):
              $optText = $opt['option_loc'] ?: $opt['option_fr'];
            ?>
            <label class="option-btn" id="opt-<?= $opt['id'] ?>">
              <input type="radio" name="answer_<?= $q['id'] ?>"
                     value="<?= $opt['id'] ?>"
                     class="sr-only"
                     onchange="selectOption(<?= $q['id'] ?>, <?= $opt['id'] ?>, this)">
              <div class="opt-letter" id="optletter-<?= $opt['id'] ?>"><?= $letters[$oi] ?? chr(65+$oi) ?></div>
              <span class="flex-1"><?= htmlspecialchars($optText) ?></span>
            </label>
            <?php endforeach; ?>
          </div>

        </div>
        <?php endforeach; ?>

      </div><!-- /questions column -->

      <!-- ── Sticky Sidebar ── -->
      <div class="lg:w-72 xl:w-80 flex-shrink-0">
        <div class="sticky-sidebar space-y-4">

          <!-- Progress card -->
          <div class="bg-white rounded-2xl p-5 fade-up fade-up-d1">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-bold text-gray-800 text-sm">Progression</h3>
              <span class="text-xs text-gray-400 font-semibold" id="answered-count">0 / <?= count($questions) ?></span>
            </div>
            <div class="quiz-progress-bar mb-4">
              <div class="quiz-progress-fill" id="quiz-progress-fill" style="width:0%"></div>
            </div>
            <!-- Question nav dots -->
            <div class="flex flex-wrap gap-2 justify-center">
              <?php foreach ($questions as $qi => $q): ?>
              <div class="q-nav-dot" id="nav-<?= $q['id'] ?>" onclick="scrollToQuestion(<?= $q['id'] ?>)" title="Question <?= $qi+1 ?>">
                <?= $qi + 1 ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Submit card -->
          <div class="bg-white rounded-2xl p-5 fade-up fade-up-d2">
            <div class="text-xs text-gray-500 mb-3 leading-relaxed">
              Répondez à toutes les questions avant de soumettre. Vous pouvez revenir en arrière.
            </div>
            <button type="button" id="submit-btn"
                    onclick="confirmSubmit()"
                    class="submit-btn disabled-btn" disabled>
              <i data-lucide="send" class="w-4 h-4"></i>
              Soumettre le quiz
            </button>
            <div class="mt-3 text-center">
              <span class="text-yellow-600 text-xs font-bold">
                <i data-lucide="zap" class="w-3.5 h-3.5 inline"></i>
                Réussir = +<?= XP_QUIZ ?> XP
              </span>
            </div>
          </div>

          <!-- Quiz info -->
          <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-5 text-white fade-up fade-up-d3">
            <div class="text-white/70 text-xs font-semibold mb-1">Score minimum requis</div>
            <div class="text-3xl font-black"><?= $quiz['pass_score'] ?>%</div>
            <div class="mt-3 text-white/70 text-xs">
              Soit <strong class="text-white"><?= ceil(count($questions) * $quiz['pass_score'] / 100) ?></strong> bonne<?= ceil(count($questions) * $quiz['pass_score'] / 100) > 1 ? 's' : '' ?> réponse<?= ceil(count($questions) * $quiz['pass_score'] / 100) > 1 ? 's' : '' ?> sur <?= count($questions) ?>
            </div>
            <div class="mt-3 text-white/70 text-xs flex items-center gap-1">
              <i data-lucide="info" class="w-3.5 h-3.5"></i>
              XP accordé à la première réussite uniquement
            </div>
          </div>

        </div>
      </div>

    </div><!-- /flex row -->

    <!-- Confirmation modal (hidden) -->
    <div id="confirm-modal" class="hidden fixed inset-0 bg-black/55 z-50 flex items-center justify-center p-4" onclick="if(event.target===this)closeConfirm()">
      <div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl">
        <div class="text-5xl mb-4">🤔</div>
        <h3 class="font-black text-gray-900 text-xl mb-2">Soumettre le quiz ?</h3>
        <p class="text-gray-500 text-sm mb-6">
          Vous avez répondu à <strong id="modal-answered">0</strong> / <?= count($questions) ?> questions.
          Cette action est irréversible.
        </p>
        <div class="flex gap-3">
          <button type="button" onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition">
            Annuler
          </button>
          <button type="button" onclick="finalSubmit()" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition">
            Confirmer
          </button>
        </div>
      </div>
    </div>

  </form>
  <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
<script src="../assets/js/xp-system.js"></script>
<script>
// ── Quiz state ───────────────────────────────────────────────
const TOTAL_Q     = <?= count($questions) ?>;
const MIN_SCORE   = <?= $quiz['pass_score'] ?>;
const answered    = {}; // {questionId: optionId}

// ── Timer (10 min per quiz, counts down) ─────────────────────
<?php if (!$submitted): ?>
const TIMER_SECS  = <?= max(60, count($questions) * 90) ?>; // 90s per question
let   timeLeft    = TIMER_SECS;
let   timerHandle = null;

function startTimer() {
  timerHandle = setInterval(() => {
    timeLeft--;
    renderTimer();
    if (timeLeft <= 0) {
      clearInterval(timerHandle);
      finalSubmit();
    }
  }, 1000);
}

function renderTimer() {
  const m   = Math.floor(timeLeft / 60);
  const s   = timeLeft % 60;
  const txt = m + ':' + (s < 10 ? '0' : '') + s;
  const el  = document.getElementById('timer-display');
  const pill = document.getElementById('timer-pill');
  if (el) el.textContent = txt;
  if (pill) {
    pill.className = 'timer-pill ' + (
      timeLeft <= 30  ? 'timer-danger'  :
      timeLeft <= 120 ? 'timer-warning' : 'timer-normal'
    );
  }
}

startTimer();
renderTimer();

// ── Select option ───────────────────────────────────────────
function selectOption(qId, optId, input) {
  // Remove selected from siblings
  const card = document.getElementById('qcard-' + qId);
  card.querySelectorAll('.option-btn').forEach(btn => {
    btn.classList.remove('selected');
    btn.querySelector('.opt-letter').classList.remove('selected');
  });

  // Mark selected
  const lbl = document.getElementById('opt-' + optId);
  if (lbl) {
    lbl.classList.add('selected');
    lbl.querySelector('.opt-letter').classList.add('selected');
  }

  // Activate card border
  card.classList.add('active');

  answered[qId] = optId;
  updateProgress();
}

function updateProgress() {
  const count = Object.keys(answered).length;
  const pct   = (count / TOTAL_Q) * 100;

  document.getElementById('answered-count').textContent = count + ' / ' + TOTAL_Q;
  document.getElementById('quiz-progress-fill').style.width = pct + '%';

  // Update nav dots
  <?php foreach ($questions as $q): ?>
  (function() {
    const dot = document.getElementById('nav-<?= $q['id'] ?>');
    if (dot) {
      dot.classList.toggle('answered', !!answered[<?= $q['id'] ?>]);
    }
  })();
  <?php endforeach; ?>

  // Enable/style submit button
  const btn = document.getElementById('submit-btn');
  if (count === TOTAL_Q) {
    btn.disabled = false;
    btn.className = 'submit-btn ready';
  }
}

function scrollToQuestion(qId) {
  const card = document.getElementById('qcard-' + qId);
  if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  // Mark current nav dot
  document.querySelectorAll('.q-nav-dot').forEach(d => d.classList.remove('current'));
  const dot = document.getElementById('nav-' + qId);
  if (dot) dot.classList.add('current');
}

function confirmSubmit() {
  const count = Object.keys(answered).length;
  document.getElementById('modal-answered').textContent = count;
  document.getElementById('confirm-modal').classList.remove('hidden');
}

function closeConfirm() {
  document.getElementById('confirm-modal').classList.add('hidden');
}

function finalSubmit() {
  clearInterval(timerHandle);
  closeConfirm();
  document.getElementById('quiz-form').submit();
}

function handleSubmit(e) {
  e.preventDefault();
  confirmSubmit();
  return false;
}

// Highlight active question on scroll
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting && e.intersectionRatio > 0.5) {
      const id = e.target.id.replace('qcard-', '');
      document.querySelectorAll('.q-nav-dot').forEach(d => d.classList.remove('current'));
      const dot = document.getElementById('nav-' + id);
      if (dot) dot.classList.add('current');
    }
  });
}, { threshold: 0.5 });

document.querySelectorAll('.question-card').forEach(c => observer.observe(c));
<?php endif; ?>

// ── Results animations ────────────────────────────────────────
<?php if ($submitted): ?>
window.addEventListener('load', () => {
  // Animate score ring
  const ring = document.getElementById('result-ring');
  const score = <?= $score ?>;
  const circumference = 2 * Math.PI * 54;
  const offset = circumference - (score / 100 * circumference);
  if (ring) {
    requestAnimationFrame(() => {
      ring.style.strokeDashoffset = offset;
    });
  }

  // Count up score number
  const countEl = document.getElementById('score-count');
  if (countEl) {
    let cur = 0;
    const target = <?= $score ?>;
    const step   = Math.ceil(target / 40);
    const iv     = setInterval(() => {
      cur = Math.min(cur + step, target);
      countEl.textContent = cur;
      if (cur >= target) clearInterval(iv);
    }, 20);
  }

  <?php if ($xpAwarded): ?>
  // Confetti for pass + XP
  setTimeout(() => {
    if (typeof confetti !== 'undefined') {
      confetti({ particleCount:100, spread:70, origin:{y:0.5},
                 colors:['#3B82F6','#FBBF24','#10B981','#8B5CF6'] });
    }
    showToast('+<?= XP_QUIZ ?> XP gagnés ! Quiz réussi ! 🎉', 'xp');
  }, 600);
  <?php elseif ($passed): ?>
  setTimeout(() => showToast('Quiz réussi ! 🎉', 'success'), 400);
  <?php else: ?>
  setTimeout(() => showToast('Courage ! Réessayez pour améliorer votre score. 💪', 'info'), 400);
  <?php endif; ?>
});
<?php endif; ?>

// Init lucide icons
if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>