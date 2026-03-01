<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

requireAtLeastStaff();

$lang      = getCurrentLang();
$dir       = getDirection();
$db        = getDB();
$pageTitle = 'Gérer les quiz';
$msg       = '';
$msgType   = 'success';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if (($action === 'add') && canAddQuiz()) {
        $lessonId  = (int)($_POST['lesson_id']  ?? 0);
        $titleFr   = trim($_POST['title_fr']    ?? '');
        $titleAr   = trim($_POST['title_ar']    ?? '');
        $titleEn   = trim($_POST['title_en']    ?? '');
        $passScore = (int)($_POST['pass_score'] ?? 60);

        if (!$lessonId || !$titleFr) {
            $msg = 'Cours et titre (FR) sont obligatoires.';
            $msgType = 'error';
        } else {
            $db->prepare(
                "INSERT INTO quizzes (lesson_id, title_fr, title_ar, title_en, pass_score) VALUES (?,?,?,?,?)"
            )->execute([$lessonId, $titleFr, $titleAr, $titleEn, $passScore]);
            logActivity($_SESSION[SESS_USER_ID], 'quiz_add', "Added quiz: {$titleFr}");
            $msg = 'Quiz ajouté avec succès !';
        }
    }

    if ($action === 'edit' && canEditQuiz()) {
        $id        = (int)($_POST['edit_id']    ?? 0);
        $lessonId  = (int)($_POST['lesson_id']  ?? 0);
        $titleFr   = trim($_POST['title_fr']    ?? '');
        $titleAr   = trim($_POST['title_ar']    ?? '');
        $titleEn   = trim($_POST['title_en']    ?? '');
        $passScore = (int)($_POST['pass_score'] ?? 60);

        if (!$lessonId || !$titleFr) {
            $msg = 'Cours et titre (FR) sont obligatoires.';
            $msgType = 'error';
        } else {
            $db->prepare(
                "UPDATE quizzes SET lesson_id=?, title_fr=?, title_ar=?, title_en=?, pass_score=? WHERE id=?"
            )->execute([$lessonId, $titleFr, $titleAr, $titleEn, $passScore, $id]);
            logActivity($_SESSION[SESS_USER_ID], 'quiz_edit', "Edited quiz #{$id}");
            $msg = 'Quiz modifié.';
        }
    }

    if ($action === 'delete' && canDeleteQuiz()) {
        $id = (int)($_POST['delete_id'] ?? 0);
        $db->prepare("DELETE FROM quizzes WHERE id=?")->execute([$id]);
        logActivity($_SESSION[SESS_USER_ID], 'quiz_delete', "Deleted quiz #{$id}");
        $msg = 'Quiz supprimé.';
    }
}

// ── Fetch ─────────────────────────────────────────────────────
$quizzes = $db->query(
    "SELECT q.id, q.title_fr, q.title_ar, q.pass_score, q.lesson_id,
            l.title_fr AS lesson_title,
            lv.name_fr AS level_name,
            s.name_fr  AS subject_name,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id=q.id) AS question_count
     FROM quizzes q
     JOIN lessons l  ON l.id  = q.lesson_id
     JOIN levels  lv ON lv.id = l.level_id
     JOIN subjects s ON s.id  = l.subject_id
     ORDER BY lv.order_num ASC, q.id DESC"
)->fetchAll();

// All published lessons for the select
$lessons = $db->query(
    "SELECT l.id, l.title_fr, lv.name_fr AS level_name, s.name_fr AS subject_name
     FROM lessons l
     JOIN levels  lv ON lv.id = l.level_id
     JOIN subjects s ON s.id  = l.subject_id
     WHERE l.published=1
     ORDER BY lv.order_num ASC, l.order_num ASC"
)->fetchAll();

require dirname(__DIR__) . '/admin/_layout.php';
?>

<?php if($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold flex items-center gap-2
     <?= $msgType==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= $msgType==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if(!canEditQuiz()): ?>
<div class="mb-4 p-3 bg-cyan-50 border border-cyan-200 rounded-xl text-cyan-700 text-xs font-semibold flex items-center gap-2">
  <i data-lucide="info" class="w-4 h-4"></i>
  Mode Staff — vous pouvez ajouter des quiz, mais pas les modifier ou supprimer.
</div>
<?php endif; ?>

<div class="flex justify-between items-center mb-5">
  <p class="text-gray-500 text-sm"><?= count($quizzes) ?> quiz au total</p>
  <?php if(canAddQuiz()): ?>
  <button onclick="openAddModal()"
          class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">
    <i data-lucide="plus" class="w-4 h-4"></i>
    Ajouter un quiz
  </button>
  <?php endif; ?>
</div>

<!-- Table -->
<div class="admin-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="dt-table w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-left">
          <th class="px-4 py-3 font-semibold">#</th>
          <th class="px-4 py-3 font-semibold">Titre du Quiz</th>
          <th class="px-4 py-3 font-semibold">Cours lié</th>
          <th class="px-4 py-3 font-semibold">Niveau</th>
          <th class="px-4 py-3 font-semibold">Matière</th>
          <th class="px-4 py-3 font-semibold">Score min.</th>
          <th class="px-4 py-3 font-semibold">Questions</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach($quizzes as $quiz): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-400 text-xs"><?= $quiz['id'] ?></td>
          <td class="px-4 py-3">
            <div class="font-semibold text-gray-900"><?= htmlspecialchars($quiz['title_fr']) ?></div>
            <?php if($quiz['title_ar']): ?><div class="text-gray-400 text-xs" dir="rtl"><?= htmlspecialchars($quiz['title_ar']) ?></div><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-gray-600 text-xs max-w-[140px] truncate"><?= htmlspecialchars($quiz['lesson_title']) ?></td>
          <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($quiz['level_name']) ?></td>
          <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($quiz['subject_name']) ?></td>
          <td class="px-4 py-3">
            <span class="bg-yellow-50 text-yellow-700 font-bold text-xs px-2 py-0.5 rounded-full"><?= $quiz['pass_score'] ?>%</span>
          </td>
          <td class="px-4 py-3">
            <span class="bg-blue-50 text-blue-700 font-semibold text-xs px-2 py-0.5 rounded-full">
              <?= $quiz['question_count'] ?> question<?= $quiz['question_count']!==1?'s':'' ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <!-- Manage Questions -->
              <a href="manage_quiz_questions.php?quiz_id=<?= $quiz['id'] ?>"
                 class="flex items-center gap-1 text-xs bg-purple-50 text-purple-700 hover:bg-purple-100 px-2.5 py-1.5 rounded-lg font-semibold transition">
                <i data-lucide="list" class="w-3.5 h-3.5"></i>
                Questions
              </a>

              <?php if(canEditQuiz()): ?>
              <button onclick='openEditModal(<?= json_encode($quiz) ?>)'
                      class="text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
              </button>
              <?php endif; ?>

              <?php if(canDeleteQuiz()): ?>
              <form method="POST" onsubmit="return confirm('Supprimer ce quiz et toutes ses questions ?')">
                <?= csrfField() ?>
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="delete_id" value="<?= $quiz['id'] ?>">
                <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($quizzes)): ?>
        <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun quiz.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ ADD / EDIT MODAL ══════════════════════════════════════ -->
<?php if(canAddQuiz()): ?>
<div id="quiz-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 id="modal-title" class="font-black text-gray-900 text-lg">Ajouter un quiz</h2>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl hover:bg-gray-100">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="px-7 py-6 space-y-5">
      <?= csrfField() ?>
      <input type="hidden" name="action"  id="form-action"  value="add">
      <input type="hidden" name="edit_id" id="form-edit-id" value="">

      <!-- Lesson -->
      <div>
        <label class="label-sm">Cours associé *</label>
        <select name="lesson_id" id="f-lesson" class="inp" required>
          <option value="">— Sélectionner un cours —</option>
          <?php foreach($lessons as $l): ?>
          <option value="<?= $l['id'] ?>" data-label="<?= htmlspecialchars($l['level_name'].' › '.$l['subject_name'].' › '.$l['title_fr']) ?>">
            <?= htmlspecialchars($l['level_name'].' › '.$l['subject_name'].' › '.$l['title_fr']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Titles -->
      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="label-sm">Titre (FR) *</label>
          <input type="text" name="title_fr" id="f-title_fr" class="inp" required placeholder="Quiz sur les dérivées">
        </div>
        <div>
          <label class="label-sm">Titre (AR)</label>
          <input type="text" name="title_ar" id="f-title_ar" class="inp" dir="rtl" placeholder="اختبار">
        </div>
        <div>
          <label class="label-sm">Titre (EN)</label>
          <input type="text" name="title_en" id="f-title_en" class="inp" placeholder="Quiz title">
        </div>
      </div>

      <!-- Pass Score -->
      <div>
        <label class="label-sm">Score minimum pour réussir (%)</label>
        <input type="number" name="pass_score" id="f-pass" class="inp" min="1" max="100" value="60">
        <p class="text-gray-400 text-xs mt-1">L'étudiant doit obtenir au moins ce score pour passer.</p>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i> Enregistrer
        </button>
        <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition">Annuler</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
  .inp { width:100%; padding:.625rem 1rem; border:1px solid #e5e7eb; border-radius:.75rem; font-size:.875rem; outline:none; background:#fff; color:#111827; transition: border-color .15s, box-shadow .15s; }
  .inp:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  .label-sm { display:block; font-size:.8125rem; font-weight:600; color:#374151; margin-bottom:.375rem; }
</style>

<script>
function openAddModal() {
  document.getElementById('modal-title').textContent = 'Ajouter un quiz';
  document.getElementById('form-action').value = 'add';
  document.getElementById('form-edit-id').value = '';
  document.getElementById('f-lesson').value   = '';
  document.getElementById('f-title_fr').value = '';
  document.getElementById('f-title_ar').value = '';
  document.getElementById('f-title_en').value = '';
  document.getElementById('f-pass').value     = '60';
  document.getElementById('quiz-modal').classList.remove('hidden');
  lucide.createIcons();
}
function openEditModal(quiz) {
  document.getElementById('modal-title').textContent = 'Modifier le quiz #' + quiz.id;
  document.getElementById('form-action').value = 'edit';
  document.getElementById('form-edit-id').value = quiz.id;
  document.getElementById('f-lesson').value    = quiz.lesson_id  || '';
  document.getElementById('f-title_fr').value  = quiz.title_fr   || '';
  document.getElementById('f-title_ar').value  = quiz.title_ar   || '';
  document.getElementById('f-title_en').value  = quiz.title_en   || '';
  document.getElementById('f-pass').value      = quiz.pass_score || 60;
  document.getElementById('quiz-modal').classList.remove('hidden');
  lucide.createIcons();
}
function closeModal() { document.getElementById('quiz-modal').classList.add('hidden'); }
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>
