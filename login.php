<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in?
if (!empty($_SESSION[SESS_USER_ID])) {
    $role = $_SESSION[SESS_ROLE] ?? 'student';
    redirect($role === 'student' ? 'student/dashboard.php' : 'admin/dashboard.php');
}

$lang   = getCurrentLang();
$dir    = getDirection();
$errors = [];
$info   = '';

if (isset($_GET['msg'])) {
    $msgs = [
        'unauthorized' => t('access_unauthorized'),
        'banned'       => t('account_banned'),
        'logout'       => t('logout_success'),
        'session_expired' => t('session_expired')
    ];
    $info = $msgs[$_GET['msg']] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = t('invalid_request');
    } else {
        $email    = sanitizeEmail(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$email || !$password) {
            $errors[] = t('fill_all_fields');
        } elseif (!validateEmail($email)) {
            $errors[] = t('invalid_email');
        } else {
            // Check rate limiting
            if (!checkRateLimit('login', $email)) {
                $errors[] = t('too_many_attempts');
            } else {
                $db   = getDB();
                $stmt = $db->prepare("
                    SELECT id, full_name, password, role, grade_level_id, xp_points, level, status, email_verified 
                    FROM users 
                    WHERE email = :email
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password'])) {
                    $errors[] = t('invalid_credentials');
                    auditLog('login_failed', "Failed login attempt for: {$email}");
                } elseif ($user['status'] === 'banned') {
                    $errors[] = t('account_banned');
                    auditLog('login_blocked', "Banned user attempted login: {$email}", (int)$user['id']);
                } else {
                    // Successful login
                    session_regenerate_id(true);
                    $_SESSION['created'] = time();

                    $_SESSION[SESS_USER_ID]  = (int)$user['id'];
                    $_SESSION[SESS_ROLE]     = $user['role'];
                    $_SESSION[SESS_USERNAME] = $user['full_name'];
                    $_SESSION[SESS_GRADE]    = (int)$user['grade_level_id'];
                    $_SESSION[SESS_XP]       = (int)$user['xp_points'];
                    $_SESSION[SESS_LEVEL]    = (int)$user['level'];

                    // Clear rate limit on success
                    unset($_SESSION['rate_limit_login_' . md5($email)]);

                    logActivity((int)$user['id'], 'login', 'User logged in');
                    auditLog('login_success', 'User logged in successfully', (int)$user['id']);

                    $redirect = $_GET['redirect'] ?? '';
                    if ($redirect && strpos($redirect, '/') === 0) {
                        redirect($redirect);
                    }
                    redirect(in_array($user['role'], ['staff', 'admin', 'super_admin']) ? 'admin/dashboard.php' : 'student/dashboard.php');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('login_title') ?> — <?= t('site_name') ?></title>
  <meta name="description" content="<?= t('login_meta_desc') ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    body { font-family: <?= $lang==='ar' ? "'Cairo'" : "'Poppins'" ?>, sans-serif; }
    .gradient-bg { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); }
    .password-toggle { cursor: pointer; transition: opacity 0.2s; }
    .password-toggle:hover { opacity: 0.7; }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    .shake { animation: shake 0.5s ease-in-out; }
  </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center py-12 px-4">

<div class="w-full max-w-md">
  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="/" class="inline-flex items-center gap-2">
      <img src="assets/images/mazar.avif" alt="MAZAR" class="w-12 h-12 rounded-2xl object-contain shadow-lg">
      <span class="text-white font-black text-2xl"><?= t('site_name') ?></span>
    </a>
    <p class="text-blue-200 mt-2 text-sm"><?= t('tagline') ?></p>
  </div>

  <div class="bg-white rounded-3xl shadow-2xl p-8">
    <h1 class="text-2xl font-black text-gray-900 mb-1"><?= t('login_title') ?></h1>
    <p class="text-gray-500 text-sm mb-6">
      <?= t('no_account') ?> <a href="register.php" class="text-blue-600 font-semibold hover:underline"><?= t('register') ?></a>
    </p>

    <!-- Info message -->
    <?php if ($info): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 text-blue-700 text-sm flex items-center gap-2">
      <i data-lucide="info" class="w-4 h-4 flex-shrink-0"></i>
      <?= clean($info) ?>
    </div>
    <?php endif; ?>

    <!-- Errors -->
    <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 <?= count($errors) > 0 ? 'shake' : '' ?>">
      <?php foreach($errors as $e): ?>
      <p class="text-red-600 text-sm flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= clean($e) ?>
      </p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate autocomplete="on">
      <?= csrfField() ?>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="mail" class="w-4 h-4 inline <?= $dir==='rtl'?'ml-1':'mr-1' ?>"></i>
          <?= t('email') ?>
        </label>
        <input type="email" name="email"
               value="<?= clean($_POST['email'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 transition"
               placeholder="<?= t('email_placeholder') ?>" required autofocus autocomplete="email">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="lock" class="w-4 h-4 inline <?= $dir==='rtl'?'ml-1':'mr-1' ?>"></i>
          <?= t('password') ?>
        </label>
        <div class="relative">
          <input type="password" name="password" id="password"
                 class="w-full px-4 py-3 pr-12 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 transition"
                 placeholder="<?= t('password_placeholder') ?>" required autocomplete="current-password">
          <button type="button" 
                  class="password-toggle absolute <?= $dir==='rtl'?'left-3':'right-3' ?> top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                  onclick="togglePassword('password', this)"
                  aria-label="<?= t('toggle_password') ?>">
            <i data-lucide="eye" class="w-5 h-5" id="password-eye"></i>
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between mb-6">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="remember" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
          <span class="text-sm text-gray-600"><?= t('remember_me') ?></span>
        </label>
        <a href="forgot-password.php" class="text-sm text-blue-600 hover:underline"><?= t('forgot_password') ?></a>
      </div>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-blue-200 text-base hover:shadow-xl hover:-translate-y-0.5">
        <i data-lucide="log-in" class="w-5 h-5"></i>
        <?= t('login') ?>
      </button>
    </form>

    <!-- Language Switcher -->
    <div class="flex justify-center gap-3 mt-6">
      <?php foreach(['ar','fr','en'] as $l): ?>
      <a href="?lang=<?= $l ?>"
         class="text-xs font-bold px-3 py-1.5 rounded-lg transition <?= $lang===$l ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
        <?= strtoupper($l) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  
  <!-- Footer -->
  <p class="text-center text-blue-200 text-xs mt-6">
    &copy; <?= date('Y') ?> <?= t('site_name') ?> — <?= t('all_rights_reserved') ?>
  </p>
</div>

<script>
  lucide.createIcons();
  
  // Password toggle functionality
  function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const eyeIcon = button.querySelector('i');
    
    if (input.type === 'password') {
      input.type = 'text';
      eyeIcon.setAttribute('data-lucide', 'eye-off');
    } else {
      input.type = 'password';
      eyeIcon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
  }
</script>
</body>
</html>
