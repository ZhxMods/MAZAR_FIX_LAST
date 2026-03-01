<?php
// ============================================================
//  MAZAR — includes/auth_check.php
//  Guards student-only pages.
//  Students → allowed in.
//  Staff / Admin / Super Admin → redirected to admin dashboard.
//  Not logged in → redirected to login.
// ============================================================

if (!defined('SESS_USER_ID'))    require_once dirname(__DIR__) . '/config.php';
if (!function_exists('redirect')) require_once __DIR__ . '/functions.php';

// Not logged in at all
if (empty($_SESSION[SESS_USER_ID])) {
    redirect('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Staff / admin / super_admin should never land on student pages
if (in_array($_SESSION[SESS_ROLE] ?? '', ['staff', 'admin', 'super_admin'])) {
    redirect('/admin/dashboard.php');
}

// Refresh XP/level + banned check from DB on every student page load
if (function_exists('getDB')) {
    $__stmt = getDB()->prepare("SELECT xp_points, level, status FROM users WHERE id = ?");
    $__stmt->execute([$_SESSION[SESS_USER_ID]]);
    $__user = $__stmt->fetch();
    if (!$__user || $__user['status'] !== 'active') {
        session_destroy();
        redirect('/login.php?msg=banned');
    }
    $_SESSION[SESS_XP]    = $__user['xp_points'];
    $_SESSION[SESS_LEVEL] = $__user['level'];
}