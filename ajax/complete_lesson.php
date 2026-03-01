<?php
// ============================================================
//  MAZAR — ajax/complete_lesson.php
//  POST: lesson_id, csrf_token, elapsed_secs
//  Anti-cheat: validates session timestamp before awarding XP
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Must be AJAX POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Must be logged in
if (empty($_SESSION[SESS_USER_ID])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'redirect' => '../login.php']);
    exit;
}

// CSRF check
if (!verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$lessonId   = (int)($_POST['lesson_id']    ?? 0);
$clientSecs = (int)($_POST['elapsed_secs'] ?? 0);
$userId     = (int)$_SESSION[SESS_USER_ID];

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Invalid lesson ID']);
    exit;
}

// ── Fetch lesson ──────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare("SELECT id, xp_reward, title_fr, duration, published FROM lessons WHERE id = ? AND published = 1");
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    exit;
}

// ── Server-side time validation (anti-cheat) ─────────────────
// Calculate minimum required seconds (same formula as lesson.php)
$durationSecs = (int)($lesson['duration'] * 60);
$requiredSecs = $durationSecs > 0 ? max(45, (int)($durationSecs * 0.80)) : 45;

// Check session start timestamp
$sessionKey   = 'ls_start_' . $userId . '_' . $lessonId;
$startTime    = isset($_SESSION[$sessionKey]) ? (int)$_SESSION[$sessionKey] : 0;
$serverElapsed = $startTime > 0 ? (time() - $startTime) : 0;

// We use a 70% tolerance on the server side:
// (server clock may diverge from client due to tab switching / pausing)
// The client sends actual qualified play time, server cross-checks wall-clock time.
// If the server wall-clock elapsed is >= 70% of required, we allow it.
// This prevents submitting the form without spending any real time.
$serverRequired = max(30, (int)($requiredSecs * 0.70));

if ($startTime === 0 || $serverElapsed < $serverRequired) {
    $minutesNeeded = ceil(($serverRequired - $serverElapsed) / 60);
    $hint = $serverElapsed > 0
        ? 'Encore ' . ($serverRequired - $serverElapsed) . ' sec nécessaires'
        : 'Ouvrez la leçon et regardez-la d\'abord';

    echo json_encode([
        'success'  => false,
        'message'  => 'too_early',
        'hint'     => $hint,
        'required' => $requiredSecs,
        'elapsed'  => $serverElapsed,
    ]);
    exit;
}

// ── Complete lesson (handles deduplication + XP internally) ──
$result = completeLesson($userId, $lessonId);

// Clear the session start key on success (clean up)
if ($result['success']) {
    unset($_SESSION[$sessionKey]);
}

echo json_encode($result);
exit;