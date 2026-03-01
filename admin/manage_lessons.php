<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

// ── COMPATIBILITY: alias SESS_UNAME → SESS_USERNAME ──────────
// Fixes "Undefined constant SESS_UNAME" in admin/_layout.php
if (!defined('SESS_UNAME') && defined('SESS_USERNAME')) {
    define('SESS_UNAME', SESS_USERNAME);
} elseif (!defined('SESS_UNAME')) {
    define('SESS_UNAME', 'username'); // fallback
}

$lang      = getCurrentLang();
$dir       = getDirection();
$db        = getDB();
$pageTitle = 'Gérer les cours';
$msg       = '';
$msgType   = 'success';

// ── Validate URL helper ───────────────────────────────────────
function isValidLessonUrl(string $url): bool {
    if (empty($url)) return false;
    // Must start with https:// or http://
    if (!preg_match('/^https?:\/\//i', $url)) return false;
    // Basic URL validation — accept YouTube, MediaFire, and direct file links
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// ── Handle POST Actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if (($action === 'add' || $action === 'edit') && canEditLesson()) {
        $titleAr   = trim($_POST['title_ar']    ?? '');
        $titleFr   = trim($_POST['title_fr']    ?? '');
        $titleEn   = trim($_POST['title_en']    ?? '');
        $descAr    = trim($_POST['desc_ar']     ?? '');
        $descFr    = trim($_POST['desc_fr']     ?? '');
        $descEn    = trim($_POST['desc_en']     ?? '');
        $type      = $_POST['type']             ?? 'video';
        $url       = trim($_POST['url']         ?? '');
        $levelId   = (int)($_POST['level_id']   ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $thumb     = trim($_POST['thumbnail']   ?? '');
        $duration  = (int)($_POST['duration']   ?? 0);
        $order     = (int)($_POST['order_num']  ?? 0);
        $published = isset($_POST['published']) ? 1 : 0;

        if (!$titleFr || !$url || !$levelId || !$subjectId) {
            $msg     = 'Titre (FR), URL, Niveau et Matière sont obligatoires.';
            $msgType = 'error';
        } elseif (!isValidLessonUrl($url)) {
            $msg     = 'URL invalide. Elle doit commencer par https:// ou http:// et être une URL valide.';
            $msgType = 'error';
        } else {
            // Auto-extract YouTube thumbnail if not set
            if (!$thumb) {
                preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $ytM);
                if (!empty($ytM[1])) {
                    $thumb = "https://img.youtube.com/vi/{$ytM[1]}/hqdefault.jpg";
                }
            }

            if ($action === 'add') {
                $db->prepare(
                    "INSERT INTO lessons
                     (title_ar,title_fr,title_en,desc_ar,desc_fr,desc_en,type,url,thumbnail,level_id,subject_id,duration,xp_reward,order_num,published)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,10,?,?)"
                )->execute([$titleAr,$titleFr,$titleEn,$descAr,$descFr,$descEn,$type,$url,$thumb,$levelId,$subjectId,$duration,$order,$published]);
                logActivity($_SESSION[SESS_USER_ID], 'lesson_add', "Added lesson: {$titleFr}");
                $msg = 'Cours ajouté avec succès !';
            } else {
                $id = (int)($_POST['edit_id'] ?? 0);
                $db->prepare(
                    "UPDATE lessons
                     SET title_ar=?,title_fr=?,title_en=?,desc_ar=?,desc_fr=?,desc_en=?,
                         type=?,url=?,thumbnail=?,level_id=?,subject_id=?,duration=?,order_num=?,published=?
                     WHERE id=?"
                )->execute([$titleAr,$titleFr,$titleEn,$descAr,$descFr,$descEn,$type,$url,$thumb,$levelId,$subjectId,$duration,$order,$published,$id]);
                logActivity($_SESSION[SESS_USER_ID], 'lesson_edit', "Edited lesson #{$id}");
                $msg = 'Cours modifié avec succès !';
            }
        }
    }

    if ($action === 'delete' && canDeleteLesson()) {
        $id = (int)($_POST['delete_id'] ?? 0);
        $db->prepare("DELETE FROM lessons WHERE id=?")->execute([$id]);
        logActivity($_SESSION[SESS_USER_ID], 'lesson_delete', "Deleted lesson #{$id}");
        $msg = 'Cours supprimé.';
    }

    if ($action === 'delete' && !canDeleteLesson()) {
        $msg     = 'Permission refusée.';
        $msgType = 'error';
    }
}

// ── Fetch Data ────────────────────────────────────────────────
$levels  = getAllLevels();
$lessons = $db->query(
    "SELECT l.id, l.title_fr, l.title_ar, l.desc_fr, l.desc_ar, l.desc_en,
            l.title_en, l.type, l.url, l.published, l.order_num, l.duration,
            l.level_id, l.subject_id, l.thumbnail,
            lv.name_fr AS level_name, s.name_fr AS subject_name
     FROM lessons l
     JOIN levels  lv ON lv.id = l.level_id
     JOIN subjects s  ON s.id  = l.subject_id
     ORDER BY lv.order_num ASC, l.order_num ASC, l.created_at DESC"
)->fetchAll();

$allSubjects = $db->query("SELECT id, name_fr AS name, level_id FROM subjects ORDER BY level_id, order_num")->fetchAll();
$subjectsByLevel = [];
foreach ($allSubjects as $s) {
    $subjectsByLevel[$s['level_id']][] = $s;
}

require dirname(__DIR__) . '/admin/_layout.php';
?>

<?php if($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold flex items-center gap-2
     <?= $msgType==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= $msgType==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Action Bar ── -->
<div class="flex justify-between items-center mb-5">
  <p class="text-gray-500 text-sm"><?= count($lessons) ?> cours au total</p>
  <?php if(canAddLesson()): ?>
  <button onclick="openAddModal()"
          class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">
    <i data-lucide="plus" class="w-4 h-4"></i>
    Ajouter un cours
  </button>
  <?php endif; ?>
</div>

<!-- ── Role badge ── -->
<?php if(!canDeleteLesson()): ?>
<div class="mb-4 p-3 bg-cyan-50 border border-cyan-200 rounded-xl text-cyan-700 text-xs font-semibold flex items-center gap-2">
  <i data-lucide="info" class="w-4 h-4"></i>
  Mode Staff — vous pouvez ajouter et modifier des cours, mais pas les supprimer.
</div>
<?php endif; ?>

<!-- ── Lessons Table ── -->
<div class="admin-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="dt-table w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-left">
          <th class="px-4 py-3 font-semibold">#</th>
          <th class="px-4 py-3 font-semibold">Titre</th>
          <th class="px-4 py-3 font-semibold">Description</th>
          <th class="px-4 py-3 font-semibold">Niveau</th>
          <th class="px-4 py-3 font-semibold">Matière</th>
          <th class="px-4 py-3 font-semibold">Type</th>
          <th class="px-4 py-3 font-semibold">Statut</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach($lessons as $lesson): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-400 text-xs"><?= $lesson['id'] ?></td>

          <td class="px-4 py-3">
            <div class="font-semibold text-gray-900"><?= htmlspecialchars($lesson['title_fr']) ?></div>
            <?php if($lesson['title_ar']): ?>
            <div class="text-gray-400 text-xs" dir="rtl"><?= htmlspecialchars($lesson['title_ar']) ?></div>
            <?php endif; ?>
            <!-- Thumbnail preview -->
            <?php if($lesson['thumbnail']): ?>
            <img src="<?= htmlspecialchars($lesson['thumbnail']) ?>" alt=""
                 class="w-16 h-10 object-cover rounded-md mt-1 border border-gray-100"
                 onerror="this.style.display='none'">
            <?php endif; ?>
          </td>

          <td class="px-4 py-3 max-w-xs">
            <?php if($lesson['desc_fr']): ?>
            <p class="text-gray-500 text-xs line-clamp-2"><?= htmlspecialchars($lesson['desc_fr']) ?></p>
            <?php else: ?>
            <span class="text-gray-300 text-xs italic">Pas de description</span>
            <?php endif; ?>
          </td>

          <td class="px-4 py-3 text-gray-600 text-xs"><?= htmlspecialchars($lesson['level_name']) ?></td>
          <td class="px-4 py-3 text-gray-600 text-xs"><?= htmlspecialchars($lesson['subject_name']) ?></td>

          <td class="px-4 py-3">
            <?php $typeColors=['video'=>'bg-blue-100 text-blue-700','pdf'=>'bg-green-100 text-green-700','book'=>'bg-purple-100 text-purple-700']; ?>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $typeColors[$lesson['type']] ?? 'bg-gray-100' ?>">
              <?= ucfirst($lesson['type']) ?>
            </span>
          </td>

          <td class="px-4 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $lesson['published'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
              <?= $lesson['published'] ? 'Publié' : 'Brouillon' ?>
            </span>
          </td>

          <td class="px-4 py-3">
            <div class="flex gap-2 items-center">
              <!-- Preview lesson page -->
              <a href="../student/lesson.php?id=<?= $lesson['id'] ?>" target="_blank"
                 class="text-gray-400 hover:text-purple-600 hover:bg-purple-50 p-1.5 rounded-lg transition" title="Prévisualiser">
                <i data-lucide="eye" class="w-4 h-4"></i>
              </a>
              <?php if(canEditLesson()): ?>
              <button onclick='openEditModal(<?= json_encode($lesson) ?>)'
                      class="text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition" title="Modifier">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
              </button>
              <?php endif; ?>
              <?php if(canDeleteLesson()): ?>
              <form method="POST" onsubmit="return confirm('Supprimer ce cours ?')">
                <?= csrfField() ?>
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="delete_id" value="<?= $lesson['id'] ?>">
                <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition" title="Supprimer">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($lessons)): ?>
        <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun cours.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ ADD / EDIT MODAL ══════════════════════════════════════ -->
<?php if(canEditLesson()): ?>
<div id="lesson-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal-box" style="max-width:760px;">
    <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 id="modal-title" class="font-black text-gray-900 text-lg">Ajouter un cours</h2>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl hover:bg-gray-100 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form id="lesson-form" method="POST" class="px-7 py-6 space-y-5">
      <?= csrfField() ?>
      <input type="hidden" name="action"  id="form-action"  value="add">
      <input type="hidden" name="edit_id" id="form-edit-id" value="">

      <!-- ── Section: Titres ── -->
      <div class="bg-blue-50 rounded-xl p-4">
        <h3 class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <i data-lucide="type" class="w-3.5 h-3.5"></i> Titres
        </h3>
        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="label-sm">Titre (AR)</label>
            <input type="text" name="title_ar" id="f-title_ar" class="inp" dir="rtl" placeholder="العنوان">
          </div>
          <div>
            <label class="label-sm">Titre (FR) <span class="text-red-500">*</span></label>
            <input type="text" name="title_fr" id="f-title_fr" class="inp" placeholder="Titre en français" required>
          </div>
          <div>
            <label class="label-sm">Titre (EN)</label>
            <input type="text" name="title_en" id="f-title_en" class="inp" placeholder="Title in English">
          </div>
        </div>
      </div>

      <!-- ── Section: Descriptions ── -->
      <div class="bg-purple-50 rounded-xl p-4">
        <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <i data-lucide="align-left" class="w-3.5 h-3.5"></i> Descriptions (optionnel)
        </h3>
        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="label-sm">Description (AR)</label>
            <textarea name="desc_ar" id="f-desc_ar" class="inp" rows="3" dir="rtl"
                      placeholder="وصف الدرس بالعربية..."></textarea>
          </div>
          <div>
            <label class="label-sm">Description (FR)</label>
            <textarea name="desc_fr" id="f-desc_fr" class="inp" rows="3"
                      placeholder="Description du cours en français..."></textarea>
          </div>
          <div>
            <label class="label-sm">Description (EN)</label>
            <textarea name="desc_en" id="f-desc_en" class="inp" rows="3"
                      placeholder="Course description in English..."></textarea>
          </div>
        </div>
      </div>

      <!-- ── Section: Classification ── -->
      <div class="bg-gray-50 rounded-xl p-4">
        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <i data-lucide="layers" class="w-3.5 h-3.5"></i> Classification
        </h3>
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="label-sm">Niveau <span class="text-red-500">*</span></label>
            <select name="level_id" id="f-level" class="inp" onchange="loadSubjects(this.value)" required>
              <option value="">— Sélectionner le niveau —</option>
              <?php foreach($levels as $lv): ?>
              <option value="<?= $lv['id'] ?>"><?= htmlspecialchars($lv['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label-sm">Matière <span class="text-red-500">*</span></label>
            <select name="subject_id" id="f-subject" class="inp" required>
              <option value="">— Sélectionner la matière —</option>
            </select>
          </div>
        </div>
      </div>

      <!-- ── Section: Contenu ── -->
      <div class="bg-green-50 rounded-xl p-4">
        <h3 class="text-xs font-bold text-green-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <i data-lucide="link" class="w-3.5 h-3.5"></i> Contenu & URL
        </h3>

        <div class="mb-4">
          <label class="label-sm">Type de contenu <span class="text-red-500">*</span></label>
          <select name="type" id="f-type" class="inp" onchange="updatePreviewType()">
            <option value="video">📹 Vidéo YouTube</option>
            <option value="pdf">📄 Document PDF (MediaFire ou direct)</option>
            <option value="book">📗 Livre (MediaFire ou direct)</option>
          </select>
        </div>

        <div>
          <label class="label-sm">URL <span class="text-red-500">*</span>
            <span class="text-gray-400 font-normal normal-case text-xs">(YouTube, MediaFire, ou lien direct)</span>
          </label>
          <div class="flex gap-3">
            <input type="url" name="url" id="f-url" class="inp flex-1"
                   placeholder="https://www.youtube.com/watch?v=... ou https://www.mediafire.com/..." required
                   oninput="updatePreview(this.value)">
            <a id="preview-test-link" href="#" target="_blank" rel="noopener"
               class="flex-shrink-0 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-1">
              <i data-lucide="external-link" class="w-4 h-4"></i> Tester
            </a>
          </div>

          <!-- URL hint -->
          <div class="mt-2 flex flex-wrap gap-2">
            <span class="text-xs text-gray-400">Exemples acceptés :</span>
            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded">youtube.com/watch?v=...</span>
            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded">youtu.be/...</span>
            <span class="text-xs bg-green-50 text-green-600 px-2 py-0.5 rounded">mediafire.com/file/...</span>
            <span class="text-xs bg-purple-50 text-purple-600 px-2 py-0.5 rounded">example.com/file.pdf</span>
          </div>

          <!-- Preview -->
          <div id="url-preview" class="mt-3 hidden">
            <div id="yt-preview" class="hidden">
              <iframe id="yt-iframe" class="w-full rounded-xl border border-gray-200" height="200" frameborder="0" allowfullscreen></iframe>
            </div>
            <div id="file-preview" class="hidden flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
              <div id="file-icon" class="text-4xl"></div>
              <div>
                <div id="file-name" class="font-semibold text-gray-800 text-sm"></div>
                <div class="text-gray-400 text-xs">Lien externe — s'ouvrira sur la page du cours</div>
              </div>
            </div>
          </div>
          <div id="url-warning" class="hidden mt-2 text-amber-600 text-xs flex items-center gap-1">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
            L'URL doit commencer par https:// ou http://
          </div>
          <div id="url-ok" class="hidden mt-2 text-green-600 text-xs flex items-center gap-1">
            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
            URL valide ✓
          </div>
        </div>
      </div>

      <!-- ── Section: Miniature & Paramètres ── -->
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label-sm">Miniature (URL)
            <span class="text-gray-400 font-normal text-xs">(auto pour YouTube)</span>
          </label>
          <input type="url" name="thumbnail" id="f-thumb" class="inp" placeholder="https://img.youtube.com/...">
          <!-- Thumb preview -->
          <div id="thumb-preview-wrap" class="mt-2 hidden">
            <img id="thumb-preview-img" src="" alt="" class="w-full h-24 object-cover rounded-xl border border-gray-200">
          </div>
        </div>
        <div>
          <label class="label-sm">Durée (minutes)</label>
          <input type="number" name="duration" id="f-duration" class="inp" min="0" placeholder="45">
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4 items-center">
        <div>
          <label class="label-sm">Ordre d'affichage</label>
          <input type="number" name="order_num" id="f-order" class="inp" min="0" value="0">
        </div>
        <div class="flex items-center gap-3 pt-5">
          <input type="checkbox" name="published" id="f-published" value="1" checked class="w-5 h-5 text-blue-600 rounded cursor-pointer">
          <label for="f-published" class="text-sm font-medium text-gray-700 cursor-pointer">Publier immédiatement</label>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i> Enregistrer
        </button>
        <button type="button" onclick="closeModal()" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<style>
  .inp { width:100%; padding:.625rem 1rem; border:1px solid #e5e7eb; border-radius:.75rem; font-size:.875rem; outline:none; transition: border-color .15s, box-shadow .15s; background:#fff; color:#111827; }
  .inp:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  textarea.inp { resize:vertical; }
  .label-sm { display:block; font-size:.8125rem; font-weight:600; color:#374151; margin-bottom:.375rem; }
</style>

<script>
const SUBJECTS_MAP = <?= json_encode($subjectsByLevel) ?>;

function loadSubjects(levelId, selectedId = null) {
  const sel = document.getElementById('f-subject');
  sel.innerHTML = '<option value="">— Sélectionner la matière —</option>';
  const subs = SUBJECTS_MAP[levelId] || [];
  subs.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.name;
    if (selectedId && s.id == selectedId) opt.selected = true;
    sel.appendChild(opt);
  });
}

function openAddModal() {
  document.getElementById('modal-title').textContent = 'Ajouter un cours';
  document.getElementById('form-action').value = 'add';
  document.getElementById('lesson-form').reset();
  document.getElementById('url-preview').classList.add('hidden');
  document.getElementById('url-warning').classList.add('hidden');
  document.getElementById('url-ok').classList.add('hidden');
  document.getElementById('thumb-preview-wrap').classList.add('hidden');
  document.getElementById('f-published').checked = true;
  document.getElementById('lesson-modal').classList.remove('hidden');
  lucide.createIcons();
}

function openEditModal(lesson) {
  document.getElementById('modal-title').textContent = 'Modifier Cours #' + lesson.id;
  document.getElementById('form-action').value   = 'edit';
  document.getElementById('form-edit-id').value  = lesson.id;

  // Titles
  document.getElementById('f-title_ar').value = lesson.title_ar || '';
  document.getElementById('f-title_fr').value = lesson.title_fr || '';
  document.getElementById('f-title_en').value = lesson.title_en || '';

  // Descriptions
  document.getElementById('f-desc_ar').value = lesson.desc_ar || '';
  document.getElementById('f-desc_fr').value = lesson.desc_fr || '';
  document.getElementById('f-desc_en').value = lesson.desc_en || '';

  // Content
  document.getElementById('f-type').value     = lesson.type      || 'video';
  document.getElementById('f-url').value      = lesson.url       || '';
  document.getElementById('f-thumb').value    = lesson.thumbnail || '';
  document.getElementById('f-duration').value = lesson.duration  || 0;
  document.getElementById('f-order').value    = lesson.order_num || 0;
  document.getElementById('f-published').checked = lesson.published == 1;

  // Subjects
  loadSubjects(lesson.level_id, lesson.subject_id);
  document.getElementById('f-level').value = lesson.level_id;

  // Preview
  if (lesson.url) updatePreview(lesson.url);
  if (lesson.thumbnail) showThumbPreview(lesson.thumbnail);

  document.getElementById('url-warning').classList.add('hidden');
  document.getElementById('url-ok').classList.add('hidden');
  document.getElementById('lesson-modal').classList.remove('hidden');
  lucide.createIcons();
}

function closeModal() {
  document.getElementById('lesson-modal').classList.add('hidden');
}

function updatePreview(url) {
  const preview  = document.getElementById('url-preview');
  const ytDiv    = document.getElementById('yt-preview');
  const fileDiv  = document.getElementById('file-preview');
  const warning  = document.getElementById('url-warning');
  const okBadge  = document.getElementById('url-ok');

  if (!url || url.trim() === '') {
    preview.classList.add('hidden');
    warning.classList.add('hidden');
    okBadge.classList.add('hidden');
    return;
  }

  if (!/^https?:\/\//i.test(url)) {
    preview.classList.add('hidden');
    warning.classList.remove('hidden');
    okBadge.classList.add('hidden');
    return;
  }

  warning.classList.add('hidden');
  okBadge.classList.remove('hidden');
  document.getElementById('preview-test-link').href = url;

  // YouTube detection
  const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
  if (ytMatch) {
    document.getElementById('yt-iframe').src = `https://www.youtube.com/embed/${ytMatch[1]}?rel=0`;
    ytDiv.classList.remove('hidden');
    fileDiv.classList.add('hidden');
    preview.classList.remove('hidden');

    // Auto-fill thumbnail
    const t = document.getElementById('f-thumb');
    if (!t.value) {
      const autoThumb = `https://img.youtube.com/vi/${ytMatch[1]}/hqdefault.jpg`;
      t.value = autoThumb;
      showThumbPreview(autoThumb);
    }
    return;
  }

  // Non-YouTube
  ytDiv.classList.add('hidden');
  const type  = document.getElementById('f-type').value;
  const icons = { video:'🎬', pdf:'📄', book:'📗' };
  const names = { video:'Fichier Vidéo', pdf:'Document PDF', book:'Livre' };
  document.getElementById('file-icon').textContent  = icons[type]  || '📎';
  document.getElementById('file-name').textContent  = names[type]  || 'Fichier externe';
  fileDiv.classList.remove('hidden');
  preview.classList.remove('hidden');
}

function showThumbPreview(url) {
  if (!url) { document.getElementById('thumb-preview-wrap').classList.add('hidden'); return; }
  document.getElementById('thumb-preview-img').src = url;
  document.getElementById('thumb-preview-wrap').classList.remove('hidden');
}

// Auto-update thumb preview when URL changes
document.addEventListener('DOMContentLoaded', function() {
  const thumbInp = document.getElementById('f-thumb');
  if (thumbInp) {
    thumbInp.addEventListener('input', function() { showThumbPreview(this.value); });
  }
});

function updatePreviewType() {
  const u = document.getElementById('f-url').value;
  if (u) updatePreview(u);
}
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>