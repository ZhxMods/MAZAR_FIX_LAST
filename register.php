<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in?
if (!empty($_SESSION[SESS_USER_ID])) {
    redirect('student/dashboard.php');
}

$lang     = getCurrentLang();
$dir      = getDirection();
$levels   = getAllLevels();
$errors   = [];
$formData = ['full_name' => '', 'email' => '', 'grade_level_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = t('invalid_request');
    } else {
        $fullName  = cleanInput(trim($_POST['full_name'] ?? ''));
        $email     = sanitizeEmail(trim($_POST['email'] ?? ''));
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['confirm_password'] ?? '';
        $gradeId   = (int)($_POST['grade_level_id'] ?? 0);

        // Preserve form data on error
        $formData = [
            'full_name'      => $fullName,
            'email'          => $email,
            'grade_level_id' => $gradeId,
        ];

        // Validation
        if (!$fullName || !$email || !$password || !$gradeId) {
            $errors[] = t('fill_all_fields');
        } elseif (!validateEmail($email)) {
            $errors[] = t('invalid_email');
        } elseif (strlen($password) < 8) {
            $errors[] = t('password_too_short');
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = t('password_requirements');
        } elseif ($password !== $password2) {
            $errors[] = t('pass_mismatch');
        } else {
            $db       = getDB();
            
            // Check if email exists
            $existing = $db->prepare("SELECT id FROM users WHERE email = :email");
            $existing->execute([':email' => $email]);
            
            if ($existing->fetch()) {
                $errors[] = t('email_taken');
            } else {
                // Create user
                $hash = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost'   => 4,
                    'threads'     => 3
                ]);
                
                $stmt = $db->prepare("
                    INSERT INTO users (full_name, email, password, grade_level_id, role, xp_points, level, status, created_at) 
                    VALUES (:name, :email, :pass, :grade, 'student', 0, 1, 'active', NOW())
                ");
                $stmt->execute([
                    ':name'  => $fullName,
                    ':email' => $email,
                    ':pass'  => $hash,
                    ':grade' => $gradeId
                ]);

                $newId = (int)$db->lastInsertId();
                logActivity($newId, 'register', 'New student registered');
                auditLog('register', "New user registered: {$email}", $newId);

                // Auto-login
                session_regenerate_id(true);
                $_SESSION[SESS_USER_ID]  = $newId;
                $_SESSION[SESS_ROLE]     = 'student';
                $_SESSION[SESS_USERNAME] = $fullName;
                $_SESSION[SESS_GRADE]    = $gradeId;
                $_SESSION[SESS_XP]       = 0;
                $_SESSION[SESS_LEVEL]    = 1;

                redirect('student/dashboard.php?welcome=1');
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
  <title><?= t('register_title') ?> — <?= t('site_name') ?></title>
  <meta name="description" content="<?= t('register_meta_desc') ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    body { font-family: <?= $lang === 'ar' ? "'Cairo'" : "'Poppins'" ?>, sans-serif; }
    .gradient-bg { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); }
    .password-toggle { cursor: pointer; transition: opacity 0.2s; }
    .password-toggle:hover { opacity: 0.7; }
    .password-strength { height: 4px; border-radius: 2px; transition: all 0.3s; }
    .strength-weak { background: #ef4444; width: 33%; }
    .strength-medium { background: #f59e0b; width: 66%; }
    .strength-strong { background: #10b981; width: 100%; }
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

  <!-- Card -->
  <div class="bg-white rounded-3xl shadow-2xl p-8">
    <h1 class="text-2xl font-black text-gray-900 mb-1"><?= t('register_title') ?></h1>
    <p class="text-gray-500 text-sm mb-6">
      <?= t('have_account') ?>
      <a href="login.php" class="text-blue-600 font-semibold hover:underline"><?= t('login') ?></a>
    </p>

    <!-- Errors -->
    <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 <?= count($errors) > 0 ? 'shake' : '' ?>">
      <?php foreach ($errors as $e): ?>
      <p class="text-red-600 text-sm flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= clean($e) ?>
      </p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate autocomplete="on">
      <?= csrfField() ?>

      <!-- Full Name -->
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="user" class="w-4 h-4 inline <?= $dir === 'rtl' ? 'ml-1' : 'mr-1' ?>"></i>
          <?= t('full_name') ?>
        </label>
        <input
          type="text"
          name="full_name"
          value="<?= clean($formData['full_name']) ?>"
          placeholder="<?= t('full_name_placeholder') ?>"
          required
          autocomplete="name"
          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 transition"
        >
      </div>

      <!-- Email -->
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="mail" class="w-4 h-4 inline <?= $dir === 'rtl' ? 'ml-1' : 'mr-1' ?>"></i>
          <?= t('email') ?>
        </label>
        <input
          type="email"
          name="email"
          value="<?= clean($formData['email']) ?>"
          placeholder="<?= t('email_placeholder') ?>"
          required
          autocomplete="email"
          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 transition"
        >
      </div>

      <!-- Grade Level -->
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="graduation-cap" class="w-4 h-4 inline <?= $dir === 'rtl' ? 'ml-1' : 'mr-1' ?>"></i>
          <?= t('grade_level') ?>
        </label>
        <select
          name="grade_level_id"
          required
          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 cursor-pointer transition"
        >
          <option value=""><?= t('select_grade') ?></option>
          <?php foreach ($levels as $lv): ?>
          <option
            value="<?= $lv['id'] ?>"
            <?= ((string)$formData['grade_level_id'] === (string)$lv['id']) ? 'selected' : '' ?>
          >
            <?= clean($lv['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Password -->
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="lock" class="w-4 h-4 inline <?= $dir === 'rtl' ? 'ml-1' : 'mr-1' ?>"></i>
          <?= t('password') ?>
        </label>
        <div class="relative">
          <input
            type="password"
            name="password"
            id="password"
            placeholder="<?= t('password_placeholder') ?>"
            required
            autocomplete="new-password"
            class="w-full px-4 py-3 pr-12 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 transition"
            oninput="checkPasswordStrength(this.value)"
          >
          <button type="button" 
                  class="password-toggle absolute <?= $dir==='rtl'?'left-3':'right-3' ?> top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                  onclick="togglePassword('password', this)"
                  aria-label="<?= t('toggle_password') ?>">
            <i data-lucide="eye" class="w-5 h-5"></i>
          </button>
        </div>
        <!-- Password Strength Indicator -->
        <div class="mt-2">
          <div class="password-strength" id="password-strength"></div>
          <p class="text-xs text-gray-500 mt-1" id="password-hint"><?= t('password_hint') ?></p>
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          <i data-lucide="shield-check" class="w-4 h-4 inline <?= $dir === 'rtl' ? 'ml-1' : 'mr-1' ?>"></i>
          <?= t('confirm_password') ?>
        </label>
        <div class="relative">
          <input
            type="password"
            name="confirm_password"
            id="confirm_password"
            placeholder="<?= t('confirm_password_placeholder') ?>"
            required
            autocomplete="new-password"
            class="w-full px-4 py-3 pr-12 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white text-gray-800 transition"
          >
          <button type="button" 
                  class="password-toggle absolute <?= $dir==='rtl'?'left-3':'right-3' ?> top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                  onclick="togglePassword('confirm_password', this)"
                  aria-label="<?= t('toggle_password') ?>">
            <i data-lucide="eye" class="w-5 h-5"></i>
          </button>
        </div>
      </div>

      <!-- Submit -->
      <button
        type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-blue-200 text-base hover:shadow-xl hover:-translate-y-0.5"
      >
        <i data-lucide="user-plus" class="w-5 h-5"></i>
        <?= t('register') ?>
      </button>
    </form>

    <!-- Language Switcher -->
    <div class="flex justify-center gap-3 mt-6">
      <?php foreach (['ar', 'fr', 'en'] as $l): ?>
      <a
        href="?lang=<?= $l ?>"
        class="text-xs font-bold px-3 py-1.5 rounded-lg transition
               <?= $lang === $l ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>"
      >
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
  
  // Password strength checker
  function checkPasswordStrength(password) {
    const strengthBar = document.getElementById('password-strength');
    const hint = document.getElementById('password-hint');
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    strengthBar.className = 'password-strength';
    
    if (password.length === 0) {
      strengthBar.style.width = '0';
      hint.textContent = '<?= t('password_hint') ?>';
      hint.className = 'text-xs text-gray-500 mt-1';
    } else if (strength <= 2) {
      strengthBar.classList.add('strength-weak');
      hint.textContent = '<?= t('password_weak') ?>';
      hint.className = 'text-xs text-red-500 mt-1';
    } else if (strength <= 4) {
      strengthBar.classList.add('strength-medium');
      hint.textContent = '<?= t('password_medium') ?>';
      hint.className = 'text-xs text-yellow-600 mt-1';
    } else {
      strengthBar.classList.add('strength-strong');
      hint.textContent = '<?= t('password_strong') ?>';
      hint.className = 'text-xs text-green-600 mt-1';
    }
  }
</script>
</body>
</html>
