<?php
// ============================================================
//  MAZAR Educational Platform — config.php
//  PHP 8.3+ Compatible — Production Ready
// ============================================================

declare(strict_types=1);

// ── Error Handling (Production Safe) ─────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', $_ENV['DB_HOST'] ?? 'sql110.hstn.me');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'mseet_41230639_mazar');
define('DB_USER', $_ENV['DB_USER'] ?? 'mseet_41230639');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'mRHv9d6yWDk2');

// ── Site ─────────────────────────────────────────────────────
define('SITE_NAME', 'MAZAR');
define('BASE_URL', 'https://mazar.zya.me');
define('DEFAULT_LANG', 'fr');

// ── Session key names ─────────────────────────────────────────
define('SESS_USER_ID',   'mazar_uid');
define('SESS_ROLE',      'mazar_role');
define('SESS_LANG',      'mazar_lang');
define('SESS_GRADE',     'mazar_grade');
define('SESS_USERNAME',  'mazar_uname');
define('SESS_XP',        'mazar_xp');
define('SESS_LEVEL',     'mazar_lvl');

// ── XP Rewards ───────────────────────────────────────────────
define('XP_LESSON',  10);
define('XP_QUIZ',    50);

// ── Level Thresholds (XP needed to REACH that level) ─────────
define('LEVEL_THRESHOLDS', serialize([
    1  => 0,
    2  => 100,
    3  => 300,
    4  => 600,
    5  => 1000,
    6  => 1500,
    7  => 2200,
    8  => 3000,
    9  => 4000,
    10 => 5500,
]));

// ── AI Configuration Defaults ─────────────────────────────────
define('AI_CONFIG_TABLE', 'ai_config');
define('AI_DEFAULT_MODEL', 'gpt-4o');
define('AI_DEFAULT_PROVIDER', 'openai');

// ── Security Configuration ────────────────────────────────────
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_LIFETIME', 7200); // 2 hours

// ── Start session with secure settings ────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('MAZAR_SESS');
    
    // Secure session cookie settings
    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    
    session_set_cookie_params($cookieParams);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// ── Default language from session ────────────────────────────
if (!isset($_SESSION[SESS_LANG])) {
    $_SESSION[SESS_LANG] = DEFAULT_LANG;
}

// Validate and set language
$allowedLangs = ['ar', 'fr', 'en'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowedLangs, true)) {
    $_SESSION[SESS_LANG] = $_GET['lang'];
}

// ── Security Headers ─────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.tailwindcss.com unpkg.com cdnjs.cloudflare.com cdn.jsdelivr.net code.jquery.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com cdnjs.cloudflare.com; font-src 'self' fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' api.openai.com api.groq.com; frame-src 'self' www.youtube.com youtube.com;");
}
