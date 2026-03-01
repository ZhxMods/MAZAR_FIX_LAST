<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

requireAtLeastStaff();

$lang    = getCurrentLang();
$dir     = getDirection();
$db      = getDB();
$quizId  = (int)($_GET['quiz_id'] ?? 0);

if (!$quizId) {
    redirect('manage_quizzes.php');
}

// Fetch quiz info
$quizStmt = $db->prepare(
    "SELECT q.*, l.title_fr AS lesson_title, lv.name_fr AS level_name, s.name_fr AS subject_name
     FROM quizzes q
     JOIN lessons l  ON l.id  = q.lesson_id
     JOIN levels  lv ON lv.id = l.level_id
     JOIN subjects s ON s.id  = l.subject_id
     WHERE q.id = ?"
);
$quizStmt->execute([$quizId]);
$quiz = $quizStmt->fetch();
if (!$quiz) redirect('manage_quizzes.php');

$pageTitle = 'Questions — ' . htmlspecialchars($quiz['title_fr']);
// Read flash message set after PRG redirect
$msg     = $_SESSION['flash_msg']  ?? '';
$msgType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    // ── Add Question ─────────────────────────────────────────
    if ($action === 'add_question' && canAddQuiz()) {
        $qFr    = trim($_POST['question_fr'] ?? '');
        $qAr    = trim($_POST['question_ar'] ?? '');
        $qEn    = trim($_POST['question_en'] ?? '');
        $order  = (int)($_POST['order_num']  ?? 0);

        $correct = (int)($_POST['correct_option'] ?? 1);

        if (!$qFr) {
            $msg = 'La question (FR) est obligatoire.'; $msgType = 'error';
        } else {
            $optionsFr = []; $optionsAr = []; $optionsEn = [];
            for ($i = 1; $i <= 4; $i++) {
                $optionsFr[] = trim($_POST["option_fr_{$i}"] ?? '');
                $optionsAr[] = trim($_POST["option_ar_{$i}"] ?? '');
                $optionsEn[] = trim($_POST["option_en_{$i}"] ?? '');
            }
            $filled = count(array_filter($optionsFr));
            if ($filled < 2) {
                $msg = 'Veuillez remplir au moins 2 options.'; $msgType = 'error';
            } elseif ($correct < 1 || $correct > 4 || !$optionsFr[$correct-1]) {
                $msg = 'Veuillez indiquer la bonne réponse parmi les options remplies.'; $msgType = 'error';
            } else {
                $db->prepare("INSERT INTO quiz_questions (quiz_id, question_fr, question_ar, question_en, order_num) VALUES (?,?,?,?,?)")
                   ->execute([$quizId, $qFr, $qAr, $qEn, $order]);
                $questionId = (int)$db->lastInsertId();

                for ($i = 0; $i < 4; $i++) {
                    if ($optionsFr[$i] === '') continue;
                    $db->prepare("INSERT INTO quiz_options (question_id, option_fr, option_ar, option_en, is_correct) VALUES (?,?,?,?,?)")
                       ->execute([$questionId, $optionsFr[$i], $optionsAr[$i], $optionsEn[$i], ($i+1 === $correct) ? 1 : 0]);
                }
                logActivity($_SESSION[SESS_USER_ID], 'quiz_question_add', "Added question to quiz #{$quizId}");
                $_SESSION['flash_msg']  = 'Question ajoutée avec succès !';
                $_SESSION['flash_type'] = 'success';
                redirect("manage_quiz_questions.php?quiz_id={$quizId}");
            }
        }
    }

    // ── Edit Question ─────────────────────────────────────────
    if ($action === 'edit_question' && canEditQuiz()) {
        $qId    = (int)($_POST['question_id'] ?? 0);
        $qFr    = trim($_POST['question_fr']  ?? '');
        $qAr    = trim($_POST['question_ar']  ?? '');
        $qEn    = trim($_POST['question_en']  ?? '');
        $order  = (int)($_POST['order_num']   ?? 0);
        $correct = (int)($_POST['correct_option'] ?? 1);

        if (!$qFr) {
            $msg = 'La question (FR) est obligatoire.'; $msgType = 'error';
        } else {
            $db->prepare("UPDATE quiz_questions SET question_fr=?, question_ar=?, question_en=?, order_num=? WHERE id=? AND quiz_id=?")
               ->execute([$qFr, $qAr, $qEn, $order, $qId, $quizId]);

            $db->prepare("DELETE FROM quiz_options WHERE question_id=?")->execute([$qId]);
            $optionsFr = []; $optionsAr = []; $optionsEn = [];
            for ($i = 1; $i <= 4; $i++) {
                $optionsFr[] = trim($_POST["option_fr_{$i}"] ?? '');
                $optionsAr[] = trim($_POST["option_ar_{$i}"] ?? '');
                $optionsEn[] = trim($_POST["option_en_{$i}"] ?? '');
            }
            for ($i = 0; $i < 4; $i++) {
                if ($optionsFr[$i] === '') continue;
                $db->prepare("INSERT INTO quiz_options (question_id, option_fr, option_ar, option_en, is_correct) VALUES (?,?,?,?,?)")
                   ->execute([$qId, $optionsFr[$i], $optionsAr[$i], $optionsEn[$i], ($i+1 === $correct) ? 1 : 0]);
            }
            logActivity($_SESSION[SESS_USER_ID], 'quiz_question_edit', "Edited question #{$qId}");
            $_SESSION['flash_msg']  = 'Question modifiée.';
            $_SESSION['flash_type'] = 'success';
            redirect("manage_quiz_questions.php?quiz_id={$quizId}");
        }
    }

    // ── Delete Question ───────────────────────────────────────
    if ($action === 'delete_question' && canDeleteQuiz()) {
        $qId = (int)($_POST['question_id'] ?? 0);
        $db->prepare("DELETE FROM quiz_questions WHERE id=? AND quiz_id=?")->execute([$qId, $quizId]);
        logActivity($_SESSION[SESS_USER_ID], 'quiz_question_delete', "Deleted question #{$qId}");
        $_SESSION['flash_msg']  = 'Question supprimée.';
        $_SESSION['flash_type'] = 'success';
        redirect("manage_quiz_questions.php?quiz_id={$quizId}");
    }
}

// ── Fetch Questions + Options ─────────────────────────────────
$questionsStmt = $db->prepare(
    "SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_num ASC, id ASC"
);
$questionsStmt->execute([$quizId]);
$questions = $questionsStmt->fetchAll();

$optStmt = $db->prepare(
    "SELECT qo.* FROM quiz_options qo
     JOIN quiz_questions qq ON qq.id = qo.question_id
     WHERE qq.quiz_id = ?
     ORDER BY qo.id ASC"
);
$optStmt->execute([$quizId]);
$allOptions = [];
foreach ($optStmt->fetchAll() as $opt) {
    $allOptions[$opt['question_id']][] = $opt;
}

require dirname(__DIR__) . '/admin/_layout.php';
?>

<?php if($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold flex items-center gap-2
     <?= $msgType==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= $msgType==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
  <a href="manage_quizzes.php" class="hover:text-blue-600 transition flex items-center gap-1">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux quiz
  </a>
  <span>/</span>
  <span class="text-gray-700 font-semibold"><?= htmlspecialchars($quiz['title_fr']) ?></span>
</div>

<!-- Quiz Info Card -->
<div class="admin-card p-5 mb-6 grid md:grid-cols-4 gap-4">
  <div>
    <div class="text-xs text-gray-400 font-semibold mb-0.5">Quiz</div>
    <div class="font-black text-gray-900"><?= htmlspecialchars($quiz['title_fr']) ?></div>
  </div>
  <div>
    <div class="text-xs text-gray-400 font-semibold mb-0.5">Cours lié</div>
    <div class="text-gray-700 text-sm truncate"><?= htmlspecialchars($quiz['lesson_title']) ?></div>
  </div>
  <div>
    <div class="text-xs text-gray-400 font-semibold mb-0.5">Niveau · Matière</div>
    <div class="text-gray-700 text-sm"><?= htmlspecialchars($quiz['level_name']) ?> · <?= htmlspecialchars($quiz['subject_name']) ?></div>
  </div>
  <div>
    <div class="text-xs text-gray-400 font-semibold mb-0.5">Score minimum</div>
    <div class="font-black text-yellow-600"><?= $quiz['pass_score'] ?>%</div>
  </div>
</div>

<?php if(!canEditQuiz()): ?>
<div class="mb-4 p-3 bg-cyan-50 border border-cyan-200 rounded-xl text-cyan-700 text-xs font-semibold flex items-center gap-2">
  <i data-lucide="info" class="w-4 h-4"></i>
  Mode Staff — vous pouvez ajouter des questions, mais pas les modifier ou supprimer.
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     EXISTING QUESTIONS
══════════════════════════════════════ -->
<div class="space-y-4 mb-8" id="questions-list">
  <?php if(empty($questions)): ?>
  <div class="admin-card p-10 text-center text-gray-400">
    <i data-lucide="help-circle" class="w-10 h-10 mx-auto mb-3 text-gray-300"></i>
    <p>Aucune question. Ajoutez la première question ci-dessous.</p>
  </div>
  <?php endif; ?>

  <?php foreach($questions as $qi => $q):
    $opts = $allOptions[$q['id']] ?? [];
    // FIX BUG 2: encode JSON safely into a data attribute to avoid single-quote issues in onclick
    $questionData = json_encode([
      'id'          => $q['id'],
      'question_fr' => $q['question_fr'],
      'question_ar' => $q['question_ar'],
      'question_en' => $q['question_en'],
      'order_num'   => $q['order_num'],
      'options'     => $opts,
    ]);
  ?>
  <div class="admin-card overflow-hidden">
    <!-- Question Header -->
    <div class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg bg-blue-600 text-white font-black text-sm flex items-center justify-center flex-shrink-0">
          <?= $qi + 1 ?>
        </span>
        <div>
          <div class="font-semibold text-gray-900"><?= htmlspecialchars($q['question_fr']) ?></div>
          <?php if($q['question_ar']): ?>
          <div class="text-gray-400 text-xs" dir="rtl"><?= htmlspecialchars($q['question_ar']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-gray-400 text-xs">Ordre: <?= $q['order_num'] ?></span>
        <?php if(canEditQuiz()): ?>
        <button
          data-question="<?= htmlspecialchars($questionData, ENT_QUOTES) ?>"
          onclick="openEditQuestionModal(JSON.parse(this.dataset.question))"
          class="text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition">
          <i data-lucide="edit-3" class="w-4 h-4"></i>
        </button>
        <?php endif; ?>
        <?php if(canDeleteQuiz()): ?>
        <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette question ?')">
          <?= csrfField() ?>
          <input type="hidden" name="action"      value="delete_question">
          <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
          <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Options -->
    <div class="px-5 py-4 grid sm:grid-cols-2 gap-3">
      <?php if(empty($opts)): ?>
      <p class="text-gray-400 text-sm col-span-2">Aucune option définie.</p>
      <?php endif; ?>
      <?php foreach($opts as $oi => $opt): ?>
      <div class="flex items-center gap-3 p-3 rounded-xl border <?= $opt['is_correct'] ? 'border-green-300 bg-green-50' : 'border-gray-100 bg-gray-50' ?>">
        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black flex-shrink-0
             <?= $opt['is_correct'] ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' ?>">
          <?= chr(65+$oi) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($opt['option_fr']) ?></div>
          <?php if($opt['option_ar']): ?>
          <div class="text-xs text-gray-400" dir="rtl"><?= htmlspecialchars($opt['option_ar']) ?></div>
          <?php endif; ?>
        </div>
        <?php if($opt['is_correct']): ?>
        <span class="flex-shrink-0 text-green-600 text-xs font-bold flex items-center gap-1">
          <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Correcte
        </span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════
     ADD QUESTION FORM
══════════════════════════════════════ -->
<?php if(canAddQuiz()): ?>
<div class="admin-card overflow-hidden">
  <div class="px-6 py-4 bg-blue-600 flex items-center gap-2">
    <i data-lucide="plus-circle" class="w-5 h-5 text-white"></i>
    <h2 class="font-black text-white">Ajouter une nouvelle question</h2>
  </div>
  <form method="POST" class="px-6 py-6 space-y-5">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add_question">

    <!-- Question texts -->
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="label-sm">Question (FR) *</label>
        <textarea name="question_fr" class="inp" rows="2" required placeholder="Entrez la question en français..."></textarea>
      </div>
      <div>
        <label class="label-sm">Question (AR)</label>
        <textarea name="question_ar" class="inp" rows="2" dir="rtl" placeholder="...السؤال بالعربية"></textarea>
      </div>
      <div>
        <label class="label-sm">Question (EN)</label>
        <textarea name="question_en" class="inp" rows="2" placeholder="Question in English..."></textarea>
      </div>
    </div>

    <!-- Options A–D -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <label class="label-sm mb-0">Options de réponse (A–D)</label>
        <span class="text-xs text-gray-400">Sélectionnez la bonne réponse avec le bouton radio</span>
      </div>
      <div class="space-y-3">
        <?php foreach(['A'=>1,'B'=>2,'C'=>3,'D'=>4] as $letter => $num): ?>
        <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-7 h-7 rounded-full bg-gray-300 text-gray-700 text-xs font-black flex items-center justify-center flex-shrink-0"><?= $letter ?></div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="correct_option" value="<?= $num ?>" <?= $num===1?'checked':'' ?>
                     class="w-4 h-4 text-green-600 cursor-pointer">
              <span class="text-sm font-semibold text-gray-700">✓ Bonne réponse</span>
            </label>
          </div>
          <div class="grid md:grid-cols-3 gap-3">
            <div>
              <label class="label-sm">Option <?= $letter ?> (FR) <?= $num<=2?'*':'' ?></label>
              <input type="text" name="option_fr_<?= $num ?>" class="inp" placeholder="Option <?= $letter ?>..." <?= $num<=2?'required':'' ?>>
            </div>
            <div>
              <label class="label-sm">Option <?= $letter ?> (AR)</label>
              <input type="text" name="option_ar_<?= $num ?>" class="inp" dir="rtl" placeholder="...الخيار">
            </div>
            <div>
              <label class="label-sm">Option <?= $letter ?> (EN)</label>
              <input type="text" name="option_en_<?= $num ?>" class="inp" placeholder="Option <?= $letter ?>...">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Order -->
    <div class="w-40">
      <label class="label-sm">Ordre d'affichage</label>
      <input type="number" name="order_num" class="inp" min="0" value="<?= count($questions) ?>">
    </div>

    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition flex items-center gap-2">
      <i data-lucide="plus" class="w-4 h-4"></i>
      Ajouter la question
    </button>
  </form>
</div>
<?php endif; ?>

<!-- ══ EDIT QUESTION MODAL ══════════════════════════════════ -->
<?php if(canEditQuiz()): ?>
<div id="edit-q-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeEditModal()">
  <div class="modal-box" style="max-width:780px">
    <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-black text-gray-900 text-lg">Modifier la question</h2>
      <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl hover:bg-gray-100">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="px-7 py-6 space-y-5">
      <?= csrfField() ?>
      <input type="hidden" name="action"      value="edit_question">
      <input type="hidden" name="question_id" id="eq-id" value="">

      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="label-sm">Question (FR) *</label>
          <textarea name="question_fr" id="eq-fr" class="inp" rows="2" required></textarea>
        </div>
        <div>
          <label class="label-sm">Question (AR)</label>
          <textarea name="question_ar" id="eq-ar" class="inp" rows="2" dir="rtl"></textarea>
        </div>
        <div>
          <label class="label-sm">Question (EN)</label>
          <textarea name="question_en" id="eq-en" class="inp" rows="2"></textarea>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-3">
          <label class="label-sm mb-0">Options de réponse (A–D)</label>
          <span class="text-xs text-gray-400">Sélectionnez la bonne réponse</span>
        </div>
        <div class="space-y-3" id="eq-options">
          <?php foreach(['A'=>1,'B'=>2,'C'=>3,'D'=>4] as $letter => $num): ?>
          <div class="p-4 rounded-xl border border-gray-200 bg-gray-50">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-7 h-7 rounded-full bg-gray-300 text-gray-700 text-xs font-black flex items-center justify-center flex-shrink-0"><?= $letter ?></div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="correct_option" id="eq-correct-<?= $num ?>" value="<?= $num ?>"
                       class="w-4 h-4 text-green-600 cursor-pointer">
                <span class="text-sm font-semibold text-gray-700">✓ Bonne réponse</span>
              </label>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
              <div>
                <label class="label-sm">Option <?= $letter ?> (FR)</label>
                <input type="text" name="option_fr_<?= $num ?>" id="eq-opt-fr-<?= $num ?>" class="inp">
              </div>
              <div>
                <label class="label-sm">Option <?= $letter ?> (AR)</label>
                <input type="text" name="option_ar_<?= $num ?>" id="eq-opt-ar-<?= $num ?>" class="inp" dir="rtl">
              </div>
              <div>
                <label class="label-sm">Option <?= $letter ?> (EN)</label>
                <input type="text" name="option_en_<?= $num ?>" id="eq-opt-en-<?= $num ?>" class="inp">
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="w-40">
        <label class="label-sm">Ordre</label>
        <input type="number" name="order_num" id="eq-order" class="inp" min="0" value="0">
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i> Enregistrer
        </button>
        <button type="button" onclick="closeEditModal()" class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition">Annuler</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
  .inp { width:100%; padding:.625rem 1rem; border:1px solid #e5e7eb; border-radius:.75rem; font-size:.875rem; outline:none; background:#fff; color:#111827; transition: border-color .15s, box-shadow .15s; }
  textarea.inp { resize:vertical; }
  .inp:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  .label-sm { display:block; font-size:.8125rem; font-weight:600; color:#374151; margin-bottom:.375rem; }
</style>

<script>
function openEditQuestionModal(q) {
  document.getElementById('eq-id').value    = q.id;
  document.getElementById('eq-fr').value    = q.question_fr || '';
  document.getElementById('eq-ar').value    = q.question_ar || '';
  document.getElementById('eq-en').value    = q.question_en || '';
  document.getElementById('eq-order').value = q.order_num   || 0;

  const opts = q.options || [];
  let correctIdx = 1;
  for (let i = 0; i < 4; i++) {
    const opt = opts[i] || {};
    document.getElementById('eq-opt-fr-' + (i+1)).value = opt.option_fr || '';
    document.getElementById('eq-opt-ar-' + (i+1)).value = opt.option_ar || '';
    document.getElementById('eq-opt-en-' + (i+1)).value = opt.option_en || '';
    if (opt.is_correct == 1) correctIdx = i + 1;
  }
  document.getElementById('eq-correct-' + correctIdx).checked = true;

  document.getElementById('edit-q-modal').classList.remove('hidden');
  lucide.createIcons();
}
function closeEditModal() { document.getElementById('edit-q-modal').classList.add('hidden'); }
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>