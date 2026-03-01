<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$lang = getCurrentLang();
$dir  = getDirection();

// Quick stats
try {
    $db           = getDB();
    $totalStudents = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $totalLessons  = $db->query("SELECT COUNT(*) FROM lessons WHERE published=1")->fetchColumn();
    $totalSubjects = $db->query("SELECT COUNT(DISTINCT id) FROM subjects")->fetchColumn();
} catch (Exception $e) {
    $totalStudents = $totalLessons = $totalSubjects = 0;
}

// Levels grouped
$levels = getAllLevels();

// Determine dashboard URL based on role
$isLoggedIn  = !empty($_SESSION[SESS_USER_ID]);
$sessionRole = $_SESSION[SESS_ROLE] ?? 'student';
$dashUrl     = in_array($sessionRole, ['staff', 'admin', 'super_admin'])
               ? 'admin/dashboard.php'
               : 'student/dashboard.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('site_name') ?> — <?= t('tagline') ?></title>
  <meta name="description" content="<?= t('hero_subtitle') ?>">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { primary: { 50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' } },
        fontFamily: { arabic: ['Cairo','Tajawal','sans-serif'] }
      }}
    }
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

  <!-- Alpine.js -->
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    * { box-sizing: border-box; }
    body { font-family: <?= $lang === 'ar' ? "'Cairo', sans-serif" : "'Poppins', sans-serif" ?>; }
    .gradient-hero { background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 50%, #3b82f6 100%); }
    .card-hover { transition: transform .25s, box-shadow .25s; }
    .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(59,130,246,.2); }
    .stat-counter { font-variant-numeric: tabular-nums; }
    .feature-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    .float-anim { animation: float 3s ease-in-out infinite; }
    .nav-glass { backdrop-filter: blur(12px); background: rgba(255,255,255,0.95); }
    <?php if($dir==='rtl'): ?>
    .space-x-4 > * + * { margin-right: 1rem; margin-left: 0; }
    <?php endif; ?>
  </style>
</head>

<body class="bg-gray-50 text-gray-800" x-data="{ mobileOpen: false }">

<!-- ════════════════════════════════════════════════
     NAVIGATION
════════════════════════════════════════════════ -->
<nav class="nav-glass fixed top-0 left-0 right-0 z-50 border-b border-gray-200 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">

      <!-- Logo -->
      <a href="/" class="flex items-center gap-2">
        <img src="assets/images/mazar.avif" alt="MAZAR" class="w-9 h-9 rounded-xl object-contain">
        <span class="text-xl font-black text-gray-900"><?= t('site_name') ?></span>
      </a>

      <!-- Desktop Nav -->
      <div class="hidden md:flex items-center gap-6">
        <a href="#levels"   class="text-gray-600 hover:text-blue-600 font-medium text-sm transition"><?= t('our_levels') ?></a>
        <a href="#features" class="text-gray-600 hover:text-blue-600 font-medium text-sm transition"><?= t('features') ?></a>

        <!-- Language Switcher -->
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
          <?php foreach(['ar','fr','en'] as $l): ?>
          <a href="?lang=<?= $l ?>"
             class="px-3 py-1 rounded-md text-xs font-bold transition
             <?= $lang === $l ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800' ?>">
            <?= strtoupper($l) ?>
          </a>
          <?php endforeach; ?>
        </div>

        <?php if ($isLoggedIn): ?>
          <a href="<?= $dashUrl ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
            <?= t('dashboard') ?>
          </a>
        <?php else: ?>
          <a href="login.php"    class="text-gray-600 hover:text-blue-600 text-sm font-medium transition"><?= t('login') ?></a>
          <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
            <?= t('register') ?>
          </a>
        <?php endif; ?>
      </div>

      <!-- Mobile hamburger -->
      <button @click="mobileOpen=!mobileOpen" class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100">
        <i data-lucide="menu" class="w-6 h-6"></i>
      </button>
    </div>
  </div>

  <!-- Mobile Menu -->
  <div x-show="mobileOpen" x-transition class="md:hidden border-t border-gray-200 bg-white px-4 py-4 space-y-3">
    <a href="#levels"   class="block text-gray-700 font-medium py-2"><?= t('our_levels') ?></a>
    <a href="#features" class="block text-gray-700 font-medium py-2"><?= t('features') ?></a>
    <div class="flex gap-2 py-2">
      <?php foreach(['ar','fr','en'] as $l): ?>
      <a href="?lang=<?= $l ?>"
         class="px-3 py-1 rounded-lg text-xs font-bold <?= $lang===$l ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600' ?>">
        <?= strtoupper($l) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($isLoggedIn): ?>
      <a href="<?= $dashUrl ?>" class="block text-center bg-blue-600 text-white py-2 rounded-lg font-semibold"><?= t('dashboard') ?></a>
    <?php else: ?>
      <a href="login.php"    class="block text-center border border-blue-600 text-blue-600 py-2 rounded-lg font-semibold"><?= t('login') ?></a>
      <a href="register.php" class="block text-center bg-blue-600 text-white py-2 rounded-lg font-semibold"><?= t('register') ?></a>
    <?php endif; ?>
  </div>
</nav>

<!-- ════════════════════════════════════════════════
     HERO SECTION
════════════════════════════════════════════════ -->
<section class="gradient-hero min-h-screen flex items-center pt-16 overflow-hidden relative">
  <!-- Decorative blobs -->
  <div class="absolute top-20 <?= $dir==='rtl'?'left-10':'right-10' ?> w-72 h-72 bg-white opacity-5 rounded-full blur-3xl"></div>
  <div class="absolute bottom-20 <?= $dir==='rtl'?'right-20':'left-20' ?> w-96 h-96 bg-blue-300 opacity-10 rounded-full blur-3xl"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 grid lg:grid-cols-2 gap-16 items-center">
    <!-- Text -->
    <div class="animate__animated animate__fadeInLeft">
      <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-2 mb-6">
        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
        <span class="text-white/90 text-sm font-medium"><?= t('tagline') ?></span>
      </div>
      <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white leading-tight mb-6">
        <?= t('hero_title') ?>
      </h1>
      <p class="text-white/80 text-lg leading-relaxed mb-8 max-w-lg">
        <?= t('hero_subtitle') ?>
      </p>
      <div class="flex flex-wrap gap-4">
        <?php if ($isLoggedIn): ?>
          <a href="<?= $dashUrl ?>"
             class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold px-8 py-4 rounded-xl hover:bg-blue-50 transition shadow-lg shadow-blue-900/30 text-base">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <?= t('dashboard') ?>
          </a>
        <?php else: ?>
          <a href="register.php"
             class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold px-8 py-4 rounded-xl hover:bg-blue-50 transition shadow-lg shadow-blue-900/30 text-base">
            <i data-lucide="rocket" class="w-5 h-5"></i>
            <?= t('get_started') ?>
          </a>
        <?php endif; ?>
        <a href="#levels"
           class="inline-flex items-center gap-2 border-2 border-white/40 text-white font-semibold px-6 py-4 rounded-xl hover:bg-white/10 transition text-base">
          <i data-lucide="play-circle" class="w-5 h-5"></i>
          <?= t('learn_more') ?>
        </a>
      </div>
    </div>

    <!-- Hero Card -->
    <div class="animate__animated animate__fadeInRight hidden lg:block">
      <div class="relative">
        <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-8 float-anim">
          <!-- Mock Dashboard Preview -->
          <div class="flex items-center gap-3 mb-6">
            <img src="assets/images/mazar.avif" alt="MAZAR" class="w-12 h-12 rounded-2xl object-contain flex-shrink-0">
            <div>
              <div class="text-white font-bold">Ahmed Al-Fassi</div>
              <div class="text-white/60 text-sm">2ème Bac · Niveau 7</div>
            </div>
            <div class="<?= $dir==='rtl'?'mr-auto':'ml-auto' ?> bg-yellow-400 text-yellow-900 px-3 py-1 rounded-full text-xs font-bold">
              2,850 XP
            </div>
          </div>
          <!-- XP Bar -->
          <div class="mb-6">
            <div class="flex justify-between text-white/80 text-xs mb-2">
              <span>Niveau 7</span><span>Niveau 8</span>
            </div>
            <div class="bg-white/20 rounded-full h-3">
              <div class="bg-gradient-to-r from-yellow-400 to-orange-400 h-3 rounded-full" style="width:78%"></div>
            </div>
          </div>
          <!-- Mini Lesson Cards -->
          <div class="space-y-3">
            <?php $mockLessons = [['Dérivées et Intégrales','video','#3B82F6','Play'],['Chimie Organique','pdf','#10B981','FileText'],['Philosophie','video','#8B5CF6','Play']]; ?>
            <?php foreach($mockLessons as [$title,$type,$color,$icon]): ?>
            <div class="flex items-center gap-3 bg-white/10 rounded-xl p-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:<?= $color ?>20">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4" style="color:<?= $color ?>"></i>
              </div>
              <span class="text-white/90 text-sm flex-1"><?= $title ?></span>
              <span class="text-green-400 text-xs font-bold">✓ +10 XP</span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════
     STATS BAR
════════════════════════════════════════════════ -->
<section class="bg-blue-700 py-10">
  <div class="max-w-5xl mx-auto px-4 grid grid-cols-3 gap-6 text-center">
    <?php $stats = [
      [$totalStudents, t('stats_students'), 'users'],
      [$totalLessons,  t('stats_lessons'),  'book-open'],
      [$totalSubjects, t('stats_subjects'), 'layers'],
    ]; ?>
    <?php foreach($stats as [$val,$label,$icon]): ?>
    <div>
      <div class="flex items-center justify-center gap-2 mb-1">
        <i data-lucide="<?= $icon ?>" class="w-5 h-5 text-blue-200"></i>
        <span class="text-3xl font-black text-white stat-counter"><?= number_format((int)$val) ?>+</span>
      </div>
      <div class="text-blue-200 text-sm font-medium"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ════════════════════════════════════════════════
     GRADE LEVELS
════════════════════════════════════════════════ -->
<section id="levels" class="py-24 bg-white">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-14">
      <h2 class="text-3xl font-black text-gray-900 mb-3"><?= t('our_levels') ?></h2>
      <p class="text-gray-500 max-w-xl mx-auto">Du primaire jusqu'au Bac, MAZAR couvre tous les niveaux.</p>
      <div class="w-16 h-1 bg-blue-600 mx-auto mt-4 rounded-full"></div>
    </div>

    <?php
    $groups = [
      ['label'=>'École Primaire','icon'=>'🏫','color'=>'from-green-500 to-emerald-600','range'=>range(1,6)],
      ['label'=>'Collège',       'icon'=>'📚','color'=>'from-blue-500 to-indigo-600',  'range'=>range(7,9)],
      ['label'=>'Lycée / Bac',   'icon'=>'🎓','color'=>'from-purple-500 to-violet-600','range'=>range(10,13)],
    ];
    ?>

    <div class="grid md:grid-cols-3 gap-8">
      <?php foreach($groups as $gi => $group): ?>
      <div class="card-hover bg-white border border-gray-100 rounded-3xl overflow-hidden shadow-sm">
        <div class="bg-gradient-to-br <?= $group['color'] ?> p-8 text-center">
          <div class="text-5xl mb-3"><?= $group['icon'] ?></div>
          <h3 class="text-xl font-black text-white"><?= $group['label'] ?></h3>
        </div>
        <div class="p-6 space-y-2">
          <?php foreach($group['range'] as $idx): ?>
            <?php if (!empty($levels[$idx-1])): ?>
            <a href="<?= $isLoggedIn ? $dashUrl : 'register.php' ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-50 group transition">
              <div class="w-2 h-2 rounded-full bg-blue-400 group-hover:bg-blue-600 transition flex-shrink-0"></div>
              <span class="text-gray-700 group-hover:text-blue-700 text-sm font-medium transition">
                <?= htmlspecialchars($levels[$idx-1]['name']) ?>
              </span>
              <i data-lucide="arrow-right" class="w-4 h-4 text-gray-300 group-hover:text-blue-500 transition <?= $dir==='rtl'?'mr-auto rotate-180':'ml-auto' ?>"></i>
            </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════
     FEATURES
════════════════════════════════════════════════ -->
<section id="features" class="py-24 bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-14">
      <h2 class="text-3xl font-black text-gray-900 mb-3"><?= t('features') ?></h2>
      <div class="w-16 h-1 bg-blue-600 mx-auto mt-4 rounded-full"></div>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php $features = [
        [t('feature_1_title'), t('feature_1_desc'), 'play-circle', '#3B82F6'],
        [t('feature_2_title'), t('feature_2_desc'), 'trophy',      '#F59E0B'],
        [t('feature_3_title'), t('feature_3_desc'), 'globe',       '#10B981'],
        [t('feature_4_title'), t('feature_4_desc'), 'brain',       '#8B5CF6'],
      ]; ?>
      <?php foreach($features as [$title,$desc,$icon,$color]): ?>
      <div class="card-hover bg-white rounded-2xl p-7 shadow-sm border border-gray-100">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-5" style="background:<?= $color ?>15">
          <i data-lucide="<?= $icon ?>" class="w-6 h-6" style="color:<?= $color ?>"></i>
        </div>
        <h3 class="font-bold text-gray-900 mb-2"><?= $title ?></h3>
        <p class="text-gray-500 text-sm leading-relaxed"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════
     CTA
════════════════════════════════════════════════ -->
<section class="gradient-hero py-24">
  <div class="max-w-3xl mx-auto px-4 text-center">
    <div class="text-5xl mb-6">🚀</div>
    <h2 class="text-3xl sm:text-4xl font-black text-white mb-4"><?= t('cta_title') ?></h2>
    <p class="text-white/80 text-lg mb-10"><?= t('cta_subtitle') ?></p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <?php if ($isLoggedIn): ?>
        <a href="<?= $dashUrl ?>"
           class="inline-flex items-center justify-center gap-2 bg-white text-blue-700 font-bold px-10 py-4 rounded-xl hover:bg-blue-50 transition shadow-lg text-lg">
          <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <?= t('dashboard') ?>
        </a>
      <?php else: ?>
        <a href="register.php"
           class="inline-flex items-center justify-center gap-2 bg-white text-blue-700 font-bold px-10 py-4 rounded-xl hover:bg-blue-50 transition shadow-lg text-lg">
          <i data-lucide="user-plus" class="w-5 h-5"></i>
          <?= t('register') ?>
        </a>
        <a href="login.php"
           class="inline-flex items-center justify-center gap-2 border-2 border-white/40 text-white font-semibold px-8 py-4 rounded-xl hover:bg-white/10 transition text-lg">
          <?= t('login') ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════ -->
<footer class="bg-gray-900 text-gray-400 py-10">
  <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="flex items-center gap-2">
      <img src="assets/images/mazar.avif" alt="MAZAR" class="w-8 h-8 rounded-lg object-contain">
      <span class="text-white font-bold text-lg"><?= t('site_name') ?></span>
    </div>
    <p class="text-sm">© <?= date('Y') ?> <?= t('site_name') ?> — <?= t('footer_rights') ?></p>
    <div class="flex gap-4 text-sm">
      <?php foreach(['ar'=>'العربية','fr'=>'Français','en'=>'English'] as $l=>$label): ?>
      <a href="?lang=<?= $l ?>" class="hover:text-white transition <?= $lang===$l?'text-white font-semibold':'' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</footer>

<script>
  lucide.createIcons();
</script>
</body>
</html>