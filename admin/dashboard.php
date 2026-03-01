<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';

$lang      = getCurrentLang();
$dir       = getDirection();
$pageTitle = t('admin_dashboard');

$db = getDB();

// ── Quick Stats ───────────────────────────────────────────────
$totalStudents  = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalLessons   = $db->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalPublished = $db->query("SELECT COUNT(*) FROM lessons WHERE published=1")->fetchColumn();
$totalXP        = $db->query("SELECT COALESCE(SUM(xp_points),0) FROM users WHERE role='student'")->fetchColumn();
$totalComplete  = $db->query("SELECT COUNT(*) FROM user_lesson_completions")->fetchColumn();
$totalSubjects  = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();

// ── Recent Activities ─────────────────────────────────────────
$recentStmt = $db->query(
    "SELECT al.action, al.details, al.created_at, u.full_name
     FROM activity_log al
     JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 20"
);
$activities = $recentStmt->fetchAll();

// ── Top Students ─────────────────────────────────────────────
$topStudents = $db->query(
    "SELECT u.full_name, u.xp_points, u.level, l.name_{$lang} AS grade
     FROM users u
     JOIN levels l ON l.id = u.grade_level_id
     WHERE u.role='student' AND u.status='active'
     ORDER BY u.xp_points DESC LIMIT 5"
)->fetchAll();

require dirname(__DIR__) . '/admin/_layout.php';
?>

<!-- ── Stats Cards ── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
  <?php
  $stats = array(
    array(t('total_students'),    $totalStudents,                           'users',       '#3B82F6'),
    array(t('total_lessons'),     $totalLessons,                            'book-open',   '#10B981'),
    array(t('total_xp_awarded'),  number_format((int)$totalXP) . ' XP',    'zap',         '#F59E0B'),
    array('Complétion Cours',     $totalComplete,                           'check-circle','#8B5CF6'),
  );
  foreach ($stats as $stat):
    $label = $stat[0]; $val = $stat[1]; $icon = $stat[2]; $color = $stat[3];
  ?>
  <div class="stat-card">
    <div class="flex items-start justify-between mb-3">
      <div class="w-11 h-11 rounded-2xl flex items-center justify-center" style="background:<?= $color ?>18">
        <i data-lucide="<?= $icon ?>" class="w-5 h-5" style="color:<?= $color ?>"></i>
      </div>
      <span class="text-green-500 text-xs font-semibold bg-green-50 px-2 py-1 rounded-lg">Live</span>
    </div>
    <div class="text-2xl font-black text-gray-900"><?= $val ?></div>
    <div class="text-gray-500 text-sm mt-1"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-6">

  <!-- Recent Activities -->
  <div class="admin-card p-6">
    <h2 class="font-black text-gray-900 mb-5 flex items-center gap-2">
      <i data-lucide="activity" class="w-5 h-5 text-blue-600"></i>
      <?= t('recent_activities') ?>
    </h2>
    <div class="space-y-3 max-h-80 overflow-y-auto">
      <?php if (empty($activities)): ?>
      <p class="text-gray-400 text-sm text-center py-6"><?= t('no_results') ?></p>
      <?php endif; ?>
      <?php foreach ($activities as $act): ?>
      <?php
        $icons = array(
            'login'           => '🔐',
            'logout'          => '👋',
            'register'        => '🎉',
            'lesson_complete' => '📚',
            'xp_earned'       => '⚡',
        );
        $ico  = isset($icons[$act['action']]) ? $icons[$act['action']] : '📌';
        // FIX: use date() instead of DateTime object for PHP 5.x safety
        $time = date('d/m H:i', strtotime($act['created_at']));
      ?>
      <div class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 transition">
        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 text-sm"><?= $ico ?></div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($act['full_name']) ?></div>
          <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($act['details'] ? $act['details'] : $act['action']) ?></div>
        </div>
        <div class="text-xs text-gray-400 flex-shrink-0"><?= $time ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top Students -->
  <div class="admin-card p-6">
    <h2 class="font-black text-gray-900 mb-5 flex items-center gap-2">
      <i data-lucide="trophy" class="w-5 h-5 text-yellow-500"></i>
      Top Étudiants — Toutes Classes
    </h2>
    <div class="space-y-3">
      <?php if (empty($topStudents)): ?>
      <p class="text-gray-400 text-sm text-center py-6"><?= t('no_results') ?></p>
      <?php endif; ?>
      <?php foreach ($topStudents as $pos => $stu): ?>
      <?php
        if ($pos === 0)      { $posCls = 'bg-yellow-400 text-yellow-900'; }
        elseif ($pos === 1)  { $posCls = 'bg-gray-300 text-gray-800'; }
        else                 { $posCls = 'bg-amber-700 text-white'; }
      ?>
      <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50">
        <div class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-black <?= $posCls ?>">
          <?= $pos + 1 ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm text-gray-900 truncate"><?= htmlspecialchars($stu['full_name']) ?></div>
          <div class="text-gray-400 text-xs"><?= htmlspecialchars($stu['grade']) ?> · Niv. <?= $stu['level'] ?></div>
        </div>
        <div class="font-black text-yellow-600 text-sm"><?= number_format($stu['xp_points']) ?> XP</div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-2 gap-3 mt-6">
      <a href="manage_lessons.php" class="flex items-center gap-2 bg-blue-600 text-white rounded-xl px-4 py-3 text-sm font-semibold hover:bg-blue-700 transition">
        <i data-lucide="book-plus" class="w-4 h-4"></i>
        <?= t('manage_lessons') ?>
      </a>
      <a href="manage_users.php" class="flex items-center gap-2 bg-slate-700 text-white rounded-xl px-4 py-3 text-sm font-semibold hover:bg-slate-800 transition">
        <i data-lucide="users" class="w-4 h-4"></i>
        <?= t('manage_users') ?>
      </a>
    </div>
  </div>

</div>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>