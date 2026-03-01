<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION[SESS_USER_ID])) {
    logActivity($_SESSION[SESS_USER_ID], 'logout', 'User logged out');
}

session_unset();
session_destroy();
redirect('login.php?msg=logout');
