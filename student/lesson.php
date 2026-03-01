<?php
// ============================================================
//  MAZAR — student/lesson.php  (v2 — PDF Reader + split assets)
//  - Inline PDF reader (PDF.js) for PDF/Book lessons
//  - Removed "Ouvrir sur YouTube/MediaFire" button
//  - CSS/JS split into separate files for InfinityFree perf
// ============================================================
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$lang   = getCurrentLang();
$dir    = getDirection();
$userId = (int)$_SESSION[SESS_USER_ID];

$lessonId = (int)($_GET['id'] ?? 0);
if (!$lessonId) redirect('dashboard.php');

$db = getDB();

// Fetch lesson with full details
$stmt = $db->prepare(
    "SELECT l.*,
            l.title_{$lang}  AS title_loc,
            l.desc_{$lang}   AS desc_loc,
            lv.name_{$lang}  AS level_name,
            s.name_{$lang}   AS subject_name,
            s.icon           AS subject_icon,
            s.color          AS subject_color,
            s.id             AS sid,
            s.level_id       AS slevel_id,
            IF(ulc.id IS NOT NULL, 1, 0) AS completed
     FROM lessons l
     JOIN levels   lv ON lv.id = l.level_id
     JOIN subjects s   ON s.id  = l.subject_id
     LEFT JOIN user_lesson_completions ulc
           ON ulc.lesson_id = l.id AND ulc.user_id = ?
     WHERE l.id = ? AND l.published = 1"
);
$stmt->execute([$userId, $lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) redirect('dashboard.php');

$title     = $lesson['title_loc'] ?: $lesson['title_fr'];
$desc      = $lesson['desc_loc']  ?: $lesson['desc_fr'];
$completed = (bool)$lesson['completed'];

// ── Anti-cheat: record session start time ──────────────────
$sessionKey = 'ls_start_' . $userId . '_' . $lessonId;
if (empty($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = time();
}

// ── Required seconds to unlock XP ─────────────────────────
$durationMins = (int)$lesson['duration'];
$durationSecs = $durationMins * 60;
$requiredSecs = $durationSecs > 0 ? max(45, (int)($durationSecs * 0.80)) : 45;

// ── Media detection ────────────────────────────────────────
$ytId         = youtubeId($lesson['url']);
$isYoutube    = (bool)$ytId;
$urlLower     = strtolower($lesson['url']);

// Detect MediaFire direct download link (download1*.mediafire.com/...)
$isMediaFireDirect = (bool)preg_match('/download\d*\.mediafire\.com/i', $lesson['url']);
// Regular MediaFire page link (mediafire.com/file/...)
$isMediaFirePage   = !$isMediaFireDirect && (strpos($urlLower, 'mediafire.com') !== false);
// Direct PDF URL (ends in .pdf or has .pdf?)
$isDirectPdf = !$isMediaFirePage && !$isMediaFireDirect && (
    substr($urlLower, -4) === '.pdf' ||
    strpos($urlLower, '.pdf?') !== false ||
    strpos($urlLower, '.pdf#') !== false
);

// Should we show the inline PDF reader?
// Yes for: direct .pdf links AND MediaFire direct download links
// (both are actual PDF file URLs that PDF.js can load)
$showPdfReader = ($lesson['type'] === 'pdf' || $lesson['type'] === 'book')
                 && ($isDirectPdf || $isMediaFireDirect)
                 && !$isYoutube;

// PDF URL to pass to JS reader
$pdfReaderUrl = $showPdfReader ? $lesson['url'] : '';

// Thumbnail
$thumb = $lesson['thumbnail'];
if (!$thumb && $isYoutube) {
    $thumb = "https://img.youtube.com/vi/{$ytId}/maxresdefault.jpg";
}

// ── Quiz for this lesson ───────────────────────────────────
$quizStmt = $db->prepare(
    "SELECT id, title_{$lang} AS qtitle, title_fr AS qtitle_fr,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = quizzes.id) AS q_count
     FROM quizzes WHERE lesson_id = ? LIMIT 1"
);
$quizStmt->execute([$lessonId]);
$quiz = $quizStmt->fetch();

// ── Related lessons (same subject) ────────────────────────
$relStmt = $db->prepare(
    "SELECT l.id, l.title_{$lang} AS title, l.title_fr, l.type, l.thumbnail, l.url, l.duration,
            IF(ulc.id IS NOT NULL, 1, 0) AS completed
     FROM lessons l
     LEFT JOIN user_lesson_completions ulc ON ulc.lesson_id = l.id AND ulc.user_id = ?
     WHERE l.subject_id = ? AND l.id != ? AND l.published = 1
     ORDER BY l.order_num ASC
     LIMIT 6"
);
$relStmt->execute([$userId, $lesson['sid'], $lessonId]);
$related = $relStmt->fetchAll();

// ── User state ─────────────────────────────────────────────
$userXP      = (int)$_SESSION[SESS_XP];
$userLevel   = (int)$_SESSION[SESS_LEVEL];
$progressPct = xpProgressPercent($userXP, $userLevel);
$nextLevelXP = xpForNextLevel($userLevel);

$_typeLabels = ['video' => 'Vidéo', 'pdf' => 'PDF', 'book' => 'Livre'];
$_typeIcons  = ['video' => 'play-circle', 'pdf' => 'file-text', 'book' => 'book-open'];
$_typeColors = ['video' => '#3B82F6', 'pdf' => '#10B981', 'book' => '#8B5CF6'];
$typeLabel   = $_typeLabels[$lesson['type']] ?? 'Cours';
$typeIcon    = $_typeIcons[$lesson['type']]  ?? 'book';
$typeColor   = $_typeColors[$lesson['type']] ?? '#6B7280';
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

  <!-- Lesson CSS (split file) -->
  <link rel="stylesheet" href="../assets/css/xp-animations.css">
  <link rel="stylesheet" href="../assets/css/lesson.css">

  <?php if ($dir === 'rtl'): ?>
  <style>
    body { font-family: 'Cairo', sans-serif; }
    #toast-container { right: auto; left: 20px; }
    @keyframes checkPop { 0%{transform:scale(0);opacity:0} 100%{transform:scale(1);opacity:1} }
  </style>
  <?php else: ?>
  <style>
    body { font-family: 'Poppins', sans-serif; }
    @keyframes checkPop { 0%{transform:scale(0);opacity:0} 100%{transform:scale(1);opacity:1} }
  </style>
  <?php endif; ?>

  <?php if ($showPdfReader): ?>
  <!-- PDF.js — loaded when lesson has an embeddable PDF (also needed for replay on completed) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <?php endif; ?>
</head>
<body dir="<?= $dir ?>">

<div id="toast-container" class="space-y-2"></div>

<!-- ══ TOP NAV ══ -->
<nav class="top-nav px-4 sm:px-6 py-3 flex items-center gap-3">
  <a href="dashboard.php?tab=lessons&subject=<?= $lesson['sid'] ?>"
     class="flex items-center gap-1.5 text-white/80 hover:text-white text-sm font-semibold transition bg-white/10 hover:bg-white/20 px-3 py-2 rounded-xl flex-shrink-0">
    <i data-lucide="arrow-<?= $dir === 'rtl' ? 'right' : 'left' ?>" class="w-4 h-4"></i>
    <span class="hidden sm:inline">Retour</span>
  </a>

  <div class="flex items-center gap-2 text-white/70 text-xs sm:text-sm flex-1 min-w-0 overflow-hidden">
    <span class="text-white/90 font-semibold truncate hidden sm:inline"><?= htmlspecialchars($lesson['subject_name']) ?></span>
    <i data-lucide="chevron-right" class="w-3.5 h-3.5 flex-shrink-0 hidden sm:inline"></i>
    <span class="text-white font-bold truncate"><?= htmlspecialchars($title) ?></span>
  </div>

  <div class="flex items-center gap-2 bg-yellow-400/20 border border-yellow-400/30 rounded-xl px-3 py-1.5 flex-shrink-0">
    <i data-lucide="zap" class="w-3.5 h-3.5 text-yellow-300"></i>
    <span class="text-yellow-200 font-bold text-xs sm:text-sm"><span id="header-xp"><?= $userXP ?></span> XP</span>
  </div>
</nav>

<!-- ══ MAIN LAYOUT ══ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <div class="flex flex-col lg:flex-row gap-6">

    <!-- ── LEFT COLUMN — Media + Info ── -->
    <div class="flex-1 min-w-0">

      <!-- ═══════════════════════════════════════════════
           MEDIA SECTION
      ══════════════════════════════════════════════════ -->
      <div class="fade-up">

        <?php if ($completed): ?>
        <!-- ══ COMPLETED STATE: thumbnail overlay + replay button ══ -->
        <div id="completed-media-wrapper" style="position:relative;width:100%;aspect-ratio:16/9;border-radius:1.25rem;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35);background:#0f172a;">
          <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                 style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;filter:brightness(.4) blur(3px);transform:scale(1.06);display:block;">
          <?php else: ?>
            <div style="position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg,<?= htmlspecialchars($lesson['subject_color'] ?: '#1d4ed8') ?>,#1e3a8a);opacity:.7;"></div>
          <?php endif; ?>
          <!-- dark overlay -->
          <div style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);"></div>
          <!-- centered content -->
          <div style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.9rem;z-index:2;">
            <!-- green check circle -->
            <div style="width:60px;height:60px;border-radius:50%;background:rgba(16,185,129,.2);border:2.5px solid #10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;animation:checkPop .5s cubic-bezier(.34,1.56,.64,1) both;">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <p style="color:rgba(255,255,255,.9);font-size:.85rem;font-weight:700;letter-spacing:.04em;margin:0;text-shadow:0 1px 8px rgba(0,0,0,.5);">Leçon terminée</p>
            <!-- replay button -->
            <button onclick="replayLesson()"
                    style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);backdrop-filter:blur(12px);border:1.5px solid rgba(255,255,255,.3);color:#fff;font-size:.82rem;font-weight:700;padding:.6rem 1.5rem;border-radius:2rem;cursor:pointer;letter-spacing:.02em;box-shadow:0 4px 20px rgba(0,0,0,.3);transition:background .2s,transform .18s;"
                    onmouseover="this.style.background='rgba(255,255,255,.25)';this.style.transform='scale(1.05)'"
                    onmouseout="this.style.background='rgba(255,255,255,.15)';this.style.transform='scale(1)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="1 4 1 10 7 10"></polyline>
                <path d="M3.51 15a9 9 0 1 0 .49-3.5"></path>
              </svg>
              Revoir la leçon
            </button>
          </div>
        </div>

        <!-- Actual content — hidden, revealed on replay click -->
        <div id="lesson-content-area" style="display:none;">

          <?php if ($isYoutube): ?>
          <div class="media-wrapper video-ratio">
            <div id="yt-player"></div>
          </div>

          <?php elseif ($showPdfReader): ?>
          <div class="pdf-reader-wrapper" id="pdf-reader-wrapper">
            <div class="pdf-toolbar" id="pdf-toolbar" style="display:none;">
              <div class="pdf-toolbar-left">
                <button class="pdf-btn" id="pdf-prev-btn" onclick="pdfPrevPage()" title="Page précédente">
                  <i data-lucide="chevron-left" style="width:14px;height:14px;"></i> Préc.
                </button>
                <button class="pdf-btn" id="pdf-next-btn" onclick="pdfNextPage()" title="Page suivante">
                  Suiv. <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </button>
              </div>
              <div class="pdf-toolbar-center">
                <input type="number" id="pdf-page-input" class="pdf-page-input" value="1" min="1"
                       onchange="pdfGoToPage(this.value)" onkeydown="if(event.key==='Enter')pdfGoToPage(this.value)">
                <span id="pdf-page-total" style="color:#64748b;">/ 0</span>
              </div>
              <div class="pdf-toolbar-right">
                <select class="pdf-zoom-select" id="pdf-zoom-select" onchange="pdfSetZoom(this.value)">
                  <option value="0.7">70%</option>
                  <option value="0.85">85%</option>
                  <option value="1.0">100%</option>
                  <option value="1.2" selected>120%</option>
                  <option value="1.5">150%</option>
                  <option value="1.8">180%</option>
                  <option value="2.0">200%</option>
                </select>
                <span style="color:#475569;font-size:.7rem;font-weight:600;padding:.25rem .6rem;background:rgba(255,255,255,.08);border-radius:.4rem;">📄 PDF</span>
              </div>
            </div>
            <div class="pdf-loading-state" id="pdf-loading-state">
              <div class="pdf-loading-spinner"></div>
              <div>
                <div style="color:#e2e8f0;font-weight:700;font-size:.9rem;margin-bottom:.25rem;">Chargement du PDF...</div>
                <div style="font-size:.78rem;color:#64748b;">Lecture du document en cours</div>
              </div>
            </div>
            <div class="pdf-error-state" id="pdf-error-state" style="display:none;">
              <div style="font-size:2.5rem;">📄</div>
              <div style="font-weight:700;font-size:.9rem;">Impossible de charger le PDF</div>
              <div class="pdf-err-msg" style="font-size:.78rem;max-width:320px;line-height:1.6;">Vérifiez le lien ou essayez plus tard.</div>
            </div>
            <div id="pdf-canvas-container" style="display:none;"></div>
            <div class="pdf-status-bar" id="pdf-status-bar" style="display:none;">
              <span id="pdf-status-text">Chargement...</span>
              <span style="color:#1e40af;font-weight:700;font-size:.7rem;">🔒 Document sécurisé MAZAR</span>
            </div>
          </div>
          <div style="text-align:center;margin-top:.5rem;color:#94a3b8;font-size:.72rem;font-weight:600;">
            <i data-lucide="keyboard" style="width:12px;height:12px;display:inline;vertical-align:middle;"></i>
            Utilisez ← → pour naviguer entre les pages
          </div>

          <?php elseif ($lesson['type'] === 'pdf' || $lesson['type'] === 'book'): ?>
          <div class="media-placeholder" style="border-radius:1.25rem;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <?php if ($thumb): ?>
              <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="media-thumb" style="border-radius:1.25rem;">
            <?php else: ?>
              <div style="text-align:center;padding:3rem 2rem;">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                  <i data-lucide="<?= $typeIcon ?>" style="width:40px;height:40px;color:#fff;"></i>
                </div>
                <div style="color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:.5rem;"><?= htmlspecialchars($title) ?></div>
                <div style="color:rgba(255,255,255,.7);font-size:.85rem;"><?= $typeLabel ?></div>
              </div>
            <?php endif; ?>
          </div>

          <?php else: ?>
          <div class="media-placeholder" style="border-radius:1.25rem;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <?php if ($thumb): ?>
              <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="media-thumb" style="border-radius:1.25rem;">
            <?php else: ?>
              <div style="text-align:center;padding:3rem;">
                <i data-lucide="play-circle" style="width:64px;height:64px;color:rgba(255,255,255,.6);"></i>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        </div><!-- /lesson-content-area -->

        <?php else: /* NOT completed — show content directly */ ?>

        <?php if ($isYoutube): ?>
        <!-- ── YouTube Video ── -->
        <div class="media-wrapper video-ratio">
          <div id="yt-player"></div>
        </div>

        <?php elseif ($showPdfReader): ?>
        <!-- ── Inline PDF Reader ── -->
        <div class="pdf-reader-wrapper" id="pdf-reader-wrapper">
          <div class="pdf-toolbar" id="pdf-toolbar" style="display:none;">
            <div class="pdf-toolbar-left">
              <button class="pdf-btn" id="pdf-prev-btn" onclick="pdfPrevPage()" title="Page précédente">
                <i data-lucide="chevron-left" style="width:14px;height:14px;"></i> Préc.
              </button>
              <button class="pdf-btn" id="pdf-next-btn" onclick="pdfNextPage()" title="Page suivante">
                Suiv. <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
              </button>
            </div>
            <div class="pdf-toolbar-center">
              <input type="number" id="pdf-page-input" class="pdf-page-input" value="1" min="1"
                     onchange="pdfGoToPage(this.value)" onkeydown="if(event.key==='Enter')pdfGoToPage(this.value)">
              <span id="pdf-page-total" style="color:#64748b;">/ 0</span>
            </div>
            <div class="pdf-toolbar-right">
              <select class="pdf-zoom-select" id="pdf-zoom-select" onchange="pdfSetZoom(this.value)">
                <option value="0.7">70%</option>
                <option value="0.85">85%</option>
                <option value="1.0">100%</option>
                <option value="1.2" selected>120%</option>
                <option value="1.5">150%</option>
                <option value="1.8">180%</option>
                <option value="2.0">200%</option>
              </select>
              <span style="color:#475569;font-size:.7rem;font-weight:600;padding:.25rem .6rem;background:rgba(255,255,255,.08);border-radius:.4rem;">📄 PDF</span>
            </div>
          </div>
          <div class="pdf-loading-state" id="pdf-loading-state">
            <div class="pdf-loading-spinner"></div>
            <div>
              <div style="color:#e2e8f0;font-weight:700;font-size:.9rem;margin-bottom:.25rem;">Chargement du PDF...</div>
              <div style="font-size:.78rem;color:#64748b;">Lecture du document en cours</div>
            </div>
          </div>
          <div class="pdf-error-state" id="pdf-error-state" style="display:none;">
            <div style="font-size:2.5rem;">📄</div>
            <div style="font-weight:700;font-size:.9rem;">Impossible de charger le PDF</div>
            <div class="pdf-err-msg" style="font-size:.78rem;max-width:320px;line-height:1.6;">Vérifiez le lien ou essayez plus tard.</div>
          </div>
          <div id="pdf-canvas-container" style="display:none;"></div>
          <div class="pdf-status-bar" id="pdf-status-bar" style="display:none;">
            <span id="pdf-status-text">Chargement...</span>
            <span style="color:#1e40af;font-weight:700;font-size:.7rem;">🔒 Document sécurisé MAZAR</span>
          </div>
        </div>
        <div style="text-align:center;margin-top:.5rem;color:#94a3b8;font-size:.72rem;font-weight:600;">
          <i data-lucide="keyboard" style="width:12px;height:12px;display:inline;vertical-align:middle;"></i>
          Utilisez ← → pour naviguer entre les pages
        </div>

        <?php elseif ($lesson['type'] === 'pdf' || $lesson['type'] === 'book'): ?>
        <!-- PDF/Book non-embeddable — thumbnail + info -->
        <div class="media-placeholder" style="border-radius:1.25rem;box-shadow:0 20px 60px rgba(0,0,0,.2);">
          <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="media-thumb" style="border-radius:1.25rem;">
          <?php else: ?>
            <div style="text-align:center;padding:3rem 2rem;">
              <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                <i data-lucide="<?= $typeIcon ?>" style="width:40px;height:40px;color:#fff;"></i>
              </div>
              <div style="color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:.5rem;"><?= htmlspecialchars($title) ?></div>
              <div style="color:rgba(255,255,255,.7);font-size:.85rem;"><?= $typeLabel ?></div>
            </div>
          <?php endif; ?>
        </div>
        <div style="margin-top:.75rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.75rem;padding:.75rem 1rem;display:flex;align-items:center;gap:.6rem;">
          <i data-lucide="info" style="width:16px;height:16px;color:#2563eb;flex-shrink:0;"></i>
          <span style="font-size:.8rem;color:#1e40af;font-weight:600;">
            Ce document est accessible depuis la page du cours. Lisez-le et revenez marquer la leçon comme terminée.
          </span>
        </div>

        <?php else: ?>
        <!-- Other / external video — thumbnail only -->
        <div class="media-placeholder" style="border-radius:1.25rem;box-shadow:0 20px 60px rgba(0,0,0,.2);">
          <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="media-thumb" style="border-radius:1.25rem;">
          <?php else: ?>
            <div style="text-align:center;padding:3rem;">
              <i data-lucide="play-circle" style="width:64px;height:64px;color:rgba(255,255,255,.6);"></i>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; /* completed / not completed */ ?>

      </div><!-- /fade-up media -->

      <!-- ── LESSON INFO CARD ── -->
      <div class="card mt-5 p-5 sm:p-6 fade-up fade-up-d1">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
          <span class="type-badge" style="background:<?= $typeColor ?>20; color:<?= $typeColor ?>; border:1px solid <?= $typeColor ?>30;">
            <i data-lucide="<?= $typeIcon ?>" style="width:12px;height:12px;"></i>
            <?= $typeLabel ?>
          </span>
          <span class="type-badge bg-blue-50 text-blue-700" style="border:1px solid #dbeafe;">
            <i data-lucide="layers" style="width:12px;height:12px;"></i>
            <?= htmlspecialchars($lesson['subject_name']) ?>
          </span>
          <span class="type-badge bg-gray-50 text-gray-600" style="border:1px solid #e5e7eb;">
            <i data-lucide="graduation-cap" style="width:12px;height:12px;"></i>
            <?= htmlspecialchars($lesson['level_name']) ?>
          </span>
          <?php if ($lesson['duration']): ?>
          <span class="type-badge bg-gray-50 text-gray-600" style="border:1px solid #e5e7eb;">
            <i data-lucide="clock" style="width:12px;height:12px;"></i>
            <?= $lesson['duration'] ?> min
          </span>
          <?php endif; ?>
          <span class="type-badge bg-yellow-50 text-yellow-700" style="border:1px solid #fde68a;">
            <i data-lucide="zap" style="width:12px;height:12px;"></i>
            +<?= $lesson['xp_reward'] ?> XP
          </span>
        </div>

        <h1 class="text-xl sm:text-2xl font-black text-gray-900 mb-1 leading-tight">
          <?= htmlspecialchars($title) ?>
        </h1>
        <?php if ($lang !== 'fr' && $lesson['title_fr'] && $lesson['title_fr'] !== $title): ?>
        <p class="text-gray-400 text-sm mb-4"><?= htmlspecialchars($lesson['title_fr']) ?></p>
        <?php endif; ?>

        <?php if ($desc): ?>
        <div class="mt-4 pt-4 border-t border-gray-100">
          <h2 class="text-sm font-bold text-gray-700 mb-2 flex items-center gap-2">
            <i data-lucide="align-left" class="w-4 h-4 text-blue-500"></i>
            Description
          </h2>
          <div style="font-size:.9rem;line-height:1.8;color:#475569;">
            <?= nl2br(htmlspecialchars($desc)) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <!-- NOTE: "Ouvrir sur YouTube / MediaFire" button REMOVED as requested -->

    </div><!-- /left column -->

    <!-- ── RIGHT COLUMN — Sidebar ── -->
    <div class="lg:w-80 xl:w-96 flex-shrink-0 space-y-4">

      <!-- ── COMPLETE LESSON CARD ── -->
      <div class="card p-5 fade-up fade-up-d1" id="complete-card">
        <h3 class="font-bold text-gray-800 text-sm mb-4 flex items-center gap-2">
          <i data-lucide="shield-check" class="w-4 h-4 text-blue-500"></i>
          Progression de la leçon
        </h3>

        <?php if ($completed): ?>
        <!-- Already completed -->
        <div class="progress-section completed-state">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-green-700">Leçon terminée ✓</span>
            <span class="time-pill ready">
              <i data-lucide="check-circle" style="width:10px;height:10px;"></i>
              Complet
            </span>
          </div>
          <div class="progress-track">
            <div class="progress-fill done" style="width:100%;"></div>
          </div>
          <p class="text-green-600 text-xs mt-2 font-medium">
            ✅ +<?= $lesson['xp_reward'] ?> XP déjà gagnés
          </p>
        </div>
        <button class="complete-btn-main done" disabled>
          <i data-lucide="check-circle-2" class="w-5 h-5"></i>
          Leçon terminée !
        </button>

        <?php else: ?>
        <!-- Progress tracking -->
        <div class="progress-section" id="progress-section">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-gray-600" id="progress-label">
              <?= $isYoutube ? 'Temps de visionnage' : ($durationMins > 0 ? 'Temps de lecture' : 'Présence sur la page') ?>
            </span>
            <span class="time-pill waiting" id="time-pill">
              <i data-lucide="clock" style="width:10px;height:10px;" id="time-pill-icon"></i>
              <span id="time-pill-text">En attente...</span>
            </span>
          </div>

          <div class="progress-track" style="margin-bottom:.6rem;">
            <div class="progress-fill" id="lesson-progress-bar" style="width:0%"></div>
          </div>

          <div class="flex justify-between items-center text-xs text-gray-400">
            <span>
              <span id="elapsed-display">0:00</span>
              <span class="text-gray-300"> / </span>
              <span id="required-display"><?= gmdate('G:i', $requiredSecs) ?></span>
            </span>
            <span id="remaining-hint" class="font-medium text-blue-500">
              <?= $durationMins > 0 ? 'Requis : ' . $durationMins . ' min' : 'Requis : 45 sec' ?>
            </span>
          </div>

          <p class="text-xs text-gray-400 mt-2 leading-relaxed" id="progress-hint-text">
            <?php if ($isYoutube): ?>
              Regardez la vidéo pour débloquer les <strong class="text-yellow-600">+<?= $lesson['xp_reward'] ?> XP</strong>
            <?php elseif ($showPdfReader): ?>
              Lisez le document pour débloquer les <strong class="text-yellow-600">+<?= $lesson['xp_reward'] ?> XP</strong>
            <?php else: ?>
              Restez sur cette page pour débloquer les <strong class="text-yellow-600">+<?= $lesson['xp_reward'] ?> XP</strong>
            <?php endif; ?>
          </p>
        </div>

        <button id="complete-btn-main"
                class="complete-btn-main locked"
                disabled
                onclick="completeLessonPage(<?= $lessonId ?>)">
          <i data-lucide="lock" class="w-5 h-5 lock-pulse" id="btn-icon"></i>
          <span id="btn-text">Terminer la leçon</span>
        </button>
        <p class="text-gray-400 text-xs text-center mt-2" id="btn-subtext">
          <?= $isYoutube ? 'Regardez la vidéo' : ($showPdfReader ? 'Lisez le document' : 'Restez sur la page') ?> pour débloquer ce bouton
        </p>
        <?php endif; ?>

        <!-- XP Progress Bar -->
        <div class="mt-4 pt-4 border-t border-gray-100">
          <div class="flex justify-between text-xs text-gray-500 mb-1.5">
            <span>Niveau <?= $userLevel ?></span>
            <span id="xp-display" class="font-semibold text-gray-700"><?= $userXP ?> XP</span>
            <span>Niveau <?= $userLevel + 1 ?></span>
          </div>
          <div class="xp-bar">
            <div class="xp-bar-inner" id="xp-bar-fill" style="width:<?= $progressPct ?>%"></div>
          </div>
          <p class="text-right text-xs text-gray-400 mt-1"><?= round($progressPct) ?>%</p>
        </div>
      </div>

      <!-- Quiz card -->
      <?php if ($quiz): ?>
      <div class="card p-5 fade-up fade-up-d2">
        <div class="flex items-center gap-2 mb-3">
          <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="help-circle" class="w-5 h-5 text-purple-600"></i>
          </div>
          <div>
            <h3 class="font-bold text-gray-800 text-sm">Quiz associé</h3>
            <p class="text-gray-400 text-xs"><?= $quiz['q_count'] ?> question<?= $quiz['q_count'] != 1 ? 's' : '' ?></p>
          </div>
        </div>
        <p class="text-gray-700 text-sm font-semibold mb-3"><?= htmlspecialchars($quiz['qtitle'] ?: $quiz['qtitle_fr']) ?></p>
        <a href="quiz.php?id=<?= $quiz['id'] ?>"
           class="flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold py-2.5 px-4 rounded-xl transition">
          <i data-lucide="play-circle" class="w-4 h-4"></i>
          Commencer le quiz
          <span class="bg-yellow-400 text-yellow-900 text-xs px-1.5 py-0.5 rounded-md font-black">+50 XP</span>
        </a>
      </div>
      <?php endif; ?>

      <!-- Related lessons -->
      <?php if (!empty($related)): ?>
      <div class="card p-5 fade-up fade-up-d3">
        <h3 class="font-bold text-gray-800 text-sm mb-3 flex items-center gap-2">
          <i data-lucide="list" class="w-4 h-4 text-blue-500"></i>
          Autres leçons — <?= htmlspecialchars($lesson['subject_name']) ?>
        </h3>
        <div class="space-y-2">
          <?php foreach ($related as $rel):
            $relTitle = $rel['title'] ?: $rel['title_fr'];
            $relYt    = youtubeId($rel['url']);
            $relThumb = $rel['thumbnail'] ?: ($relYt ? "https://img.youtube.com/vi/{$relYt}/hqdefault.jpg" : '');
            $_ri = ['video' => 'play', 'pdf' => 'file-text', 'book' => 'book-open'];
            $_rc = ['video' => '#3B82F6', 'pdf' => '#10B981', 'book' => '#8B5CF6'];
            $relIcon  = $_ri[$rel['type']] ?? 'book';
            $relColor = $_rc[$rel['type']] ?? '#6B7280';
          ?>
          <a href="lesson.php?id=<?= $rel['id'] ?>" class="related-card <?= $dir === 'rtl' ? 'rtl' : '' ?>">
            <?php if ($relThumb): ?>
            <img src="<?= htmlspecialchars($relThumb) ?>" alt="" class="related-thumb">
            <?php else: ?>
            <div class="related-thumb-placeholder">
              <i data-lucide="<?= $relIcon ?>" style="width:18px;height:18px;color:<?= $relColor ?>"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 leading-tight truncate"><?= htmlspecialchars($relTitle) ?></p>
              <div class="flex items-center gap-2 mt-1">
                <?php if ($rel['duration']): ?>
                <span class="text-gray-400 text-xs"><?= $rel['duration'] ?>min</span>
                <?php endif; ?>
                <?php if ($rel['completed']): ?>
                <span class="text-green-600 text-xs font-semibold flex items-center gap-0.5">
                  <i data-lucide="check-circle-2" style="width:11px;height:11px;"></i> Fait
                </span>
                <?php endif; ?>
              </div>
            </div>
            <i data-lucide="chevron-<?= $dir === 'rtl' ? 'left' : 'right' ?>" class="w-4 h-4 text-gray-300 flex-shrink-0"></i>
          </a>
          <?php endforeach; ?>
        </div>
        <a href="dashboard.php?tab=lessons&subject=<?= $lesson['sid'] ?>"
           class="mt-3 flex items-center justify-center gap-1 text-blue-600 hover:text-blue-700 text-xs font-semibold transition">
          Voir toutes les leçons
          <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
        </a>
      </div>
      <?php endif; ?>

    </div><!-- /right column -->
  </div>
</div>


<!-- ══ SCRIPTS ══ -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>

<!-- XP system (base) -->
<script src="../assets/js/xp-system.js"></script>

<!-- Lesson engine (split file) -->
<script src="../assets/js/lesson.js"></script>

<!-- Inject PHP config into JS -->
<script>
  /* Lesson config — injected by PHP */
  window.MAZAR_XP             = <?= $userXP ?>;
  window.MAZAR_LEVEL          = <?= $userLevel ?>;
  window.MAZAR_CSRF           = '<?= csrfToken() ?>';
  window.MAZAR_AJAX           = '../ajax/complete_lesson.php';

  window.LESSON_IS_YOUTUBE    = <?= $isYoutube    ? 'true' : 'false' ?>;
  window.LESSON_YT_ID         = '<?= $ytId ?>';
  window.LESSON_PDF_URL       = <?= json_encode($showPdfReader ? $pdfReaderUrl : '') ?>;
  window.LESSON_REQUIRED_SECS = <?= $requiredSecs ?>;
  window.LESSON_DURATION_MINS = <?= $durationMins ?>;
  window.LESSON_XP_REWARD     = <?= (int)$lesson['xp_reward'] ?>;
  window.LESSON_ALREADY_DONE  = <?= $completed ? 'true' : 'false' ?>;
</script>

<?php if ($completed): ?>
<script>
function replayLesson() {
  var overlay = document.getElementById('completed-media-wrapper');
  var content = document.getElementById('lesson-content-area');
  if (overlay) {
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity .35s';
    setTimeout(function() { overlay.style.display = 'none'; }, 350);
  }
  if (content) {
    content.style.display = 'block';
    // Re-init icons for newly visible elements
    if (typeof lucide !== 'undefined') lucide.createIcons();
    // Init PDF reader
    if (window.LESSON_PDF_URL && window.LESSON_PDF_URL !== '') {
      setTimeout(function() {
        if (typeof initPdfReader === 'function') initPdfReader(window.LESSON_PDF_URL);
      }, 200);
    }
    // Init YouTube
    if (window.LESSON_IS_YOUTUBE) {
      var tag = document.createElement('script');
      tag.src = 'https://www.youtube.com/iframe_api';
      document.head.appendChild(tag);
    }
  }
}
</script>
<?php endif; ?>

</body>
</html>