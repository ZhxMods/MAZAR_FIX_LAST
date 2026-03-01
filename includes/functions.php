<?php
// ============================================================
//  MAZAR — includes/functions.php
//  PHP 8.3+ Compatible with Enhanced Security
// ============================================================

declare(strict_types=1);

if (!defined('SESS_LANG')) require_once dirname(__DIR__) . '/config.php';
if (!function_exists('getDB')) require_once __DIR__ . '/db.php';

// ── Translation ───────────────────────────────────────────────
function getCurrentLang(): string {
    return $_SESSION[SESS_LANG] ?? DEFAULT_LANG;
}

function getDirection(): string {
    return getCurrentLang() === 'ar' ? 'rtl' : 'ltr';
}

function loadTranslations(): array {
    static $trans = null;
    if ($trans === null) {
        $lang = getCurrentLang();
        $file = dirname(__DIR__) . '/lang/' . $lang . '.php';
        if (!file_exists($file)) {
            $file = dirname(__DIR__) . '/lang/fr.php';
        }
        $trans = include $file;
    }
    return $trans;
}

function t(string $key, array $replace = []): string {
    $translations = loadTranslations();
    $text = $translations[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string)$v, $text);
    }
    return $text;
}

// ── XP & Level ───────────────────────────────────────────────
function getLevelThresholds(): array {
    return unserialize(LEVEL_THRESHOLDS);
}

function calculateLevel(int $xp): int {
    $thresholds = getLevelThresholds();
    $level = 1;
    foreach ($thresholds as $lvl => $required) {
        if ($xp >= $required) $level = $lvl;
    }
    return $level;
}

function xpForNextLevel(int $currentLevel): int {
    $thresholds = getLevelThresholds();
    return $thresholds[$currentLevel + 1] ?? end($thresholds);
}

function xpProgressPercent(int $xp, int $level): float {
    $thresholds = getLevelThresholds();
    $current = $thresholds[$level] ?? 0;
    $next    = $thresholds[$level + 1] ?? $thresholds[10];
    if ($next <= $current) return 100.0;
    return min(100.0, round(($xp - $current) / ($next - $current) * 100, 1));
}

function levelBadgeColor(int $level): string {
    $colors = [
        1 => '#6B7280', 2 => '#3B82F6', 3 => '#10B981',
        4 => '#F59E0B', 5 => '#EF4444', 6 => '#8B5CF6',
        7 => '#EC4899', 8 => '#14B8A6', 9 => '#F97316', 10 => '#FACC15',
    ];
    return $colors[$level] ?? '#3B82F6';
}

// ── Award XP ──────────────────────────────────────────────────
function awardXP(int $userId, int $amount, string $reason = ''): array {
    $db = getDB();

    // Get current XP using prepared statement
    $stmt = $db->prepare("SELECT xp_points, level FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) return ['success' => false, 'message' => 'User not found'];

    $newXP    = (int)$user['xp_points'] + $amount;
    $newLevel = calculateLevel($newXP);

    $updateStmt = $db->prepare("UPDATE users SET xp_points = :xp, level = :lvl WHERE id = :id");
    $updateStmt->execute([':xp' => $newXP, ':lvl' => $newLevel, ':id' => $userId]);

    // Update session
    $_SESSION[SESS_XP]    = $newXP;
    $_SESSION[SESS_LEVEL] = $newLevel;

    // Log activity
    logActivity($userId, 'xp_earned', "Earned {$amount} XP — {$reason}");

    return [
        'success'         => true,
        'new_xp'          => $newXP,
        'new_level'       => $newLevel,
        'level_up'        => $newLevel > (int)$user['level'],
        'percent'         => xpProgressPercent($newXP, $newLevel),
        'next_level_xp'   => xpForNextLevel($newLevel),
    ];
}

// ── Lesson Completion ─────────────────────────────────────────
function hasCompletedLesson(int $userId, int $lessonId): bool {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM user_lesson_completions WHERE user_id = :uid AND lesson_id = :lid");
    $stmt->execute([':uid' => $userId, ':lid' => $lessonId]);
    return (bool)$stmt->fetch();
}

function completeLesson(int $userId, int $lessonId): array {
    if (hasCompletedLesson($userId, $lessonId)) {
        return ['success' => false, 'message' => 'Already completed'];
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO user_lesson_completions (user_id, lesson_id) VALUES (:uid, :lid)");
    $stmt->execute([':uid' => $userId, ':lid' => $lessonId]);

    $result = awardXP($userId, XP_LESSON, 'Lesson completed');

    // Log activity
    logActivity($userId, 'lesson_complete', "Completed lesson #{$lessonId}");

    return array_merge($result, ['message' => '+' . XP_LESSON . ' XP!']);
}

// ── Activity Log ──────────────────────────────────────────────
function logActivity(int $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (:uid, :action, :details)");
        $stmt->execute([':uid' => $userId, ':action' => $action, ':details' => $details]);
    } catch (Exception $e) {
        error_log('[MAZAR Activity] ' . $e->getMessage());
    }
}

// ── CSRF Helpers ──────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($token) || empty($storedToken)) {
        return false;
    }
    
    // Check token age
    if (!empty($_SESSION['csrf_token_time']) && 
        (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME)) {
        return false;
    }
    
    return hash_equals($storedToken, $token);
}

// ── Enhanced Sanitization ─────────────────────────────────────
function clean(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function cleanInput(string $input): string {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $input;
}

function sanitizeEmail(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validateEmail(string $email): bool {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

// ── Redirect ──────────────────────────────────────────────────
function redirect(string $url): void {
    // Prevent header injection
    $url = str_replace(["\r", "\n"], '', $url);
    header('Location: ' . $url);
    exit;
}

// ── IDOR Protection ───────────────────────────────────────────
function validateOwnership(int $resourceId, string $resourceType, ?int $userId = null): bool {
    if ($userId === null) {
        $userId = (int)($_SESSION[SESS_USER_ID] ?? 0);
    }
    
    if ($userId === 0 || $resourceId <= 0) {
        return false;
    }
    
    $db = getDB();
    
    switch ($resourceType) {
        case 'lesson':
            // Check if lesson belongs to user's grade level
            $stmt = $db->prepare("
                SELECT 1 FROM lessons l 
                JOIN subjects s ON l.subject_id = s.id 
                JOIN users u ON u.grade_level_id = s.level_id 
                WHERE l.id = :lid AND u.id = :uid
            ");
            $stmt->execute([':lid' => $resourceId, ':uid' => $userId]);
            return (bool)$stmt->fetch();
            
        case 'quiz':
            // Check if quiz is accessible to user
            $stmt = $db->prepare("
                SELECT 1 FROM quizzes q 
                JOIN lessons l ON q.lesson_id = l.id 
                JOIN subjects s ON l.subject_id = s.id 
                JOIN users u ON u.grade_level_id = s.level_id 
                WHERE q.id = :qid AND u.id = :uid
            ");
            $stmt->execute([':qid' => $resourceId, ':uid' => $userId]);
            return (bool)$stmt->fetch();
            
        case 'user':
            // Users can only access their own data (unless admin)
            $role = $_SESSION[SESS_ROLE] ?? '';
            if (in_array($role, ['admin', 'super_admin'], true)) {
                return true;
            }
            return $resourceId === $userId;
            
        default:
            return false;
    }
}

function requireOwnership(int $resourceId, string $resourceType): void {
    if (!validateOwnership($resourceId, $resourceType)) {
        http_response_code(403);
        redirect('/err/403.php');
    }
}

// ── Rate Limiting ─────────────────────────────────────────────
function checkRateLimit(string $action, string $identifier, int $maxAttempts = MAX_LOGIN_ATTEMPTS, int $window = LOGIN_LOCKOUT_TIME): bool {
    $key = 'rate_limit_' . $action . '_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Reset if window has passed
    if (time() - $data['first_attempt'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

function getRateLimitRemaining(string $action, string $identifier): int {
    $key = 'rate_limit_' . $action . '_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        return MAX_LOGIN_ATTEMPTS;
    }
    
    return max(0, MAX_LOGIN_ATTEMPTS - $_SESSION[$key]['count']);
}

// ── Get levels from DB ────────────────────────────────────────
function getAllLevels(): array {
    static $levels = null;
    if ($levels === null) {
        $lang  = getCurrentLang();
        $col   = "name_{$lang}";
        $db    = getDB();
        $stmt  = $db->prepare("SELECT id, {$col} AS name, slug, order_num FROM levels ORDER BY order_num ASC");
        $stmt->execute();
        $levels = $stmt->fetchAll();
    }
    return $levels;
}

// ── Get subjects for a level ──────────────────────────────────
function getSubjectsByLevel(int $levelId): array {
    $lang = getCurrentLang();
    $col  = "name_{$lang}";
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, {$col} AS name, icon, color FROM subjects WHERE level_id = :lid ORDER BY order_num ASC");
    $stmt->execute([':lid' => $levelId]);
    return $stmt->fetchAll();
}

// ── Get lessons for subject ───────────────────────────────────
function getLessonsBySubject(int $subjectId, int $userId = 0): array {
    $lang = getCurrentLang();
    $col  = "title_{$lang}";
    $db   = getDB();
    
    if ($userId > 0) {
        $sql = "SELECT l.id, l.{$col} AS title, l.type, l.url, l.thumbnail, l.xp_reward, l.duration,
                       IF(ulc.id IS NOT NULL, 1, 0) AS completed
                FROM lessons l
                LEFT JOIN user_lesson_completions ulc ON ulc.lesson_id = l.id AND ulc.user_id = :uid
                WHERE l.subject_id = :sid AND l.published = 1
                ORDER BY l.order_num ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $userId, ':sid' => $subjectId]);
    } else {
        $sql = "SELECT l.id, l.{$col} AS title, l.type, l.url, l.thumbnail, l.xp_reward, l.duration,
                       0 AS completed
                FROM lessons l
                WHERE l.subject_id = :sid AND l.published = 1
                ORDER BY l.order_num ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':sid' => $subjectId]);
    }
    
    return $stmt->fetchAll();
}

// ── Leaderboard for grade ─────────────────────────────────────
function getLeaderboard(int $levelId, int $limit = 5): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id, full_name, xp_points, level 
         FROM users 
         WHERE grade_level_id = :gid AND role = 'student' AND status = 'active'
         ORDER BY xp_points DESC LIMIT :lim"
    );
    $stmt->bindValue(':gid', $levelId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── YouTube ID from URL ───────────────────────────────────────
function youtubeId(string $url): string {
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return $m[1] ?? '';
    }
    return '';
}

// ── AI Configuration ──────────────────────────────────────────
function getAIConfig(): array {
    static $config = null;
    
    if ($config === null) {
        $db = getDB();
        try {
            $stmt = $db->query("SELECT * FROM ai_config WHERE id = 1 LIMIT 1");
            $config = $stmt->fetch();
            
            if (!$config) {
                // Return defaults if no config exists
                $config = [
                    'provider' => AI_DEFAULT_PROVIDER,
                    'model' => AI_DEFAULT_MODEL,
                    'api_key' => '',
                    'system_prompt' => 'You are MAZAR AI, an educational assistant for Moroccan students. Provide helpful, accurate, and age-appropriate educational responses.',
                    'enabled' => 1,
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ];
            }
        } catch (Exception $e) {
            // Table might not exist yet
            $config = [
                'provider' => AI_DEFAULT_PROVIDER,
                'model' => AI_DEFAULT_MODEL,
                'api_key' => '',
                'system_prompt' => 'You are MAZAR AI, an educational assistant for Moroccan students.',
                'enabled' => 1,
                'temperature' => 0.7,
                'max_tokens' => 1000
            ];
        }
    }
    
    return $config;
}

function isAIEnabled(): bool {
    $config = getAIConfig();
    return (bool)($config['enabled'] ?? true);
}

// ── Audit Logging ─────────────────────────────────────────────
function auditLog(string $action, string $details = '', ?int $userId = null): void {
    if ($userId === null) {
        $userId = (int)($_SESSION[SESS_USER_ID] ?? 0);
    }
    
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (:uid, :action, :details, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':action' => $action,
            ':details' => $details,
            ':ip' => $ip,
            ':ua' => $userAgent
        ]);
    } catch (Exception $e) {
        error_log('[MAZAR Audit] ' . $e->getMessage());
    }
}
