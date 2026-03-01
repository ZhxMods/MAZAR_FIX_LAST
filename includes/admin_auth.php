<?php
// ============================================================
//  MAZAR — includes/admin_auth.php
//  Include at top of every admin page
//  Roles allowed: staff | admin | super_admin
// ============================================================

if (!defined('SESS_USER_ID'))    require_once dirname(__DIR__) . '/config.php';
if (!function_exists('redirect')) require_once __DIR__ . '/functions.php';
if (!function_exists('isSuperAdmin')) require_once __DIR__ . '/permissions.php';

$__role = $_SESSION[SESS_ROLE] ?? '';

if (empty($_SESSION[SESS_USER_ID]) || !in_array($__role, ['staff', 'admin', 'super_admin'])) {
    redirect(dirname($_SERVER['SCRIPT_NAME'], 2) . '/login.php?msg=unauthorized');
}
