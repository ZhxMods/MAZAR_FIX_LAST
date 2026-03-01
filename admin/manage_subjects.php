<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

requireSuperAdmin();

$lang      = getCurrentLang();
$dir       = getDirection();
$db        = getDB();
$pageTitle = 'Gérer les matières';
$msg       = '';
$msgType   = 'success';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nameAr  = trim($_POST['name_ar']  ?? '');
        $nameFr  = trim($_POST['name_fr']  ?? '');
        $nameEn  = trim($_POST['name_en']  ?? '');
        $levelId = (int)($_POST['level_id'] ?? 0);
        $icon    = trim($_POST['icon']     ?? 'BookOpen');
        $color   = trim($_POST['color']    ?? '#3B82F6');
        $order   = (int)($_POST['order_num'] ?? 0);

        if (!$nameFr || !$levelId) {
            $msg = 'Nom (FR) et niveau sont obligatoires.';
            $msgType = 'error';
        } else {
            if ($action === 'add') {
                $db->prepare(
                    "INSERT INTO subjects (name_ar, name_fr, name_en, level_id, icon, color, order_num) VALUES (?,?,?,?,?,?,?)"
                )->execute([$nameAr, $nameFr, $nameEn, $levelId, $icon, $color, $order]);
                logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Added subject: {$nameFr}");
                $msg = 'Matière ajoutée avec succès !';
            } else {
                $id = (int)($_POST['edit_id'] ?? 0);
                $db->prepare(
                    "UPDATE subjects SET name_ar=?, name_fr=?, name_en=?, level_id=?, icon=?, color=?, order_num=? WHERE id=?"
                )->execute([$nameAr, $nameFr, $nameEn, $levelId, $icon, $color, $order, $id]);
                logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Edited subject #{$id}");
                $msg = 'Matière modifiée.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['delete_id'] ?? 0);
        try {
            $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
            logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Deleted subject #{$id}");
            $msg = 'Matière supprimée.';
        } catch (Exception $e) {
            $msg = 'Impossible de supprimer : des cours y sont liés.';
            $msgType = 'error';
        }
    }
}

// ── Fetch ─────────────────────────────────────────────────────
$subjects = $db->query(
    "SELECT s.*, lv.name_fr AS level_name,
            (SELECT COUNT(*) FROM lessons l WHERE l.subject_id=s.id) AS lesson_count
     FROM subjects s
     JOIN levels lv ON lv.id = s.level_id
     ORDER BY lv.order_num ASC, s.order_num ASC"
)->fetchAll();

$levels = getAllLevels();

// Common Lucide icons for subjects
$lucideIcons = ['BookOpen','Calculator','Zap','Globe','Star','Brain','Map','Leaf','Atom','Music','Palette','Flask','Code','Heart','Award'];

require dirname(__DIR__) . '/admin/_layout.php';
?>

<?php if($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold flex items-center gap-2
     <?= $msgType==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= $msgType==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex justify-between items-center mb-5">
  <p class="text-gray-500 text-sm"><?= count($subjects) ?> matières au total</p>
  <button onclick="openAddModal()"
          class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">
    <i data-lucide="plus" class="w-4 h-4"></i>
    Ajouter une matière
  </button>
</div>

<div class="admin-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="dt-table w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-left">
          <th class="px-4 py-3 font-semibold">#</th>
          <th class="px-4 py-3 font-semibold">Icône</th>
          <th class="px-4 py-3 font-semibold">Nom FR</th>
          <th class="px-4 py-3 font-semibold">Nom AR</th>
          <th class="px-4 py-3 font-semibold">Niveau</th>
          <th class="px-4 py-3 font-semibold">Cours</th>
          <th class="px-4 py-3 font-semibold">Ordre</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach($subjects as $subj): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-400 text-xs"><?= $subj['id'] ?></td>
          <td class="px-4 py-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:<?= htmlspecialchars($subj['color']) ?>20">
              <i data-lucide="<?= htmlspecialchars(strtolower($subj['icon'])) ?>" class="w-4 h-4" style="color:<?= htmlspecialchars($subj['color']) ?>"></i>
            </div>
          </td>
          <td class="px-4 py-3 font-semibold text-gray-900"><?= htmlspecialchars($subj['name_fr']) ?></td>
          <td class="px-4 py-3 text-gray-600" dir="rtl"><?= htmlspecialchars($subj['name_ar']) ?></td>
          <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($subj['level_name']) ?></td>
          <td class="px-4 py-3"><span class="bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $subj['lesson_count'] ?></span></td>
          <td class="px-4 py-3 text-gray-600 font-semibold"><?= $subj['order_num'] ?></td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <button onclick='openEditModal(<?= json_encode($subj) ?>)'
                      class="text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
              </button>
              <form method="POST" onsubmit="return confirm('Supprimer cette matière ? Tous ses cours seront supprimés.')">
                <?= csrfField() ?>
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="delete_id" value="<?= $subj['id'] ?>">
                <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($subjects)): ?>
        <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune matière.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ MODAL ══ -->
<div id="subject-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 id="modal-title" class="font-black text-gray-900 text-lg">Ajouter une matière</h2>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl hover:bg-gray-100">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="px-7 py-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action"  id="form-action"  value="add">
      <input type="hidden" name="edit_id" id="form-edit-id" value="">

      <!-- Names -->
      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="label-sm">Nom (FR) *</label>
          <input type="text" name="name_fr" id="f-name_fr" class="inp" required placeholder="Mathématiques">
        </div>
        <div>
          <label class="label-sm">Nom (AR)</label>
          <input type="text" name="name_ar" id="f-name_ar" class="inp" dir="rtl" placeholder="الرياضيات">
        </div>
        <div>
          <label class="label-sm">Nom (EN)</label>
          <input type="text" name="name_en" id="f-name_en" class="inp" placeholder="Mathematics">
        </div>
      </div>

      <!-- Level -->
      <div>
        <label class="label-sm">Niveau *</label>
        <select name="level_id" id="f-level" class="inp" required>
          <option value="">— Sélectionner le niveau —</option>
          <?php foreach($levels as $lv): ?>
          <option value="<?= $lv['id'] ?>"><?= htmlspecialchars($lv['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Icon + Color + Order -->
      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="label-sm">Icône Lucide</label>
          <select name="icon" id="f-icon" class="inp">
            <?php foreach($lucideIcons as $ic): ?>
            <option value="<?= $ic ?>"><?= $ic ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label-sm">Couleur</label>
          <div class="flex gap-2 items-center">
            <input type="color" name="color" id="f-color" value="#3B82F6"
                   class="w-12 h-11 rounded-xl border border-gray-200 cursor-pointer p-1">
            <input type="text" id="f-color-text" class="inp flex-1" placeholder="#3B82F6" readonly>
          </div>
        </div>
        <div>
          <label class="label-sm">Ordre</label>
          <input type="number" name="order_num" id="f-order" class="inp" min="0" value="0">
        </div>
      </div>

      <!-- Preview -->
      <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
        <p class="text-xs text-gray-500 mb-2 font-semibold">Aperçu :</p>
        <div class="flex items-center gap-3">
          <div id="preview-icon-box" class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#3B82F620">
            <i id="preview-icon" data-lucide="book-open" class="w-5 h-5" style="color:#3B82F6"></i>
          </div>
          <span id="preview-name" class="font-semibold text-gray-700">Nom de la matière</span>
        </div>
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

<style>
  .inp { width:100%; padding:.625rem 1rem; border:1px solid #e5e7eb; border-radius:.75rem; font-size:.875rem; outline:none; background:#fff; color:#111827; transition: border-color .15s, box-shadow .15s; }
  .inp:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  .label-sm { display:block; font-size:.8125rem; font-weight:600; color:#374151; margin-bottom:.375rem; }
</style>

<script>
// Sync color picker with text display and preview
document.getElementById('f-color').addEventListener('input', function() {
  const c = this.value;
  document.getElementById('f-color-text').value = c;
  document.getElementById('preview-icon-box').style.background = c + '20';
  document.getElementById('preview-icon').style.color = c;
});

document.getElementById('f-name_fr').addEventListener('input', function() {
  document.getElementById('preview-name').textContent = this.value || 'Nom de la matière';
});

document.getElementById('f-icon').addEventListener('change', function() {
  const el = document.getElementById('preview-icon');
  el.setAttribute('data-lucide', this.value.toLowerCase());
  lucide.createIcons();
});

function openAddModal() {
  document.getElementById('modal-title').textContent = 'Ajouter une matière';
  document.getElementById('form-action').value = 'add';
  document.getElementById('form-edit-id').value = '';
  ['name_fr','name_ar','name_en'].forEach(k => document.getElementById('f-' + k).value = '');
  document.getElementById('f-level').value = '';
  document.getElementById('f-icon').value = 'BookOpen';
  document.getElementById('f-color').value = '#3B82F6';
  document.getElementById('f-color-text').value = '#3B82F6';
  document.getElementById('f-order').value = '0';
  document.getElementById('preview-name').textContent = 'Nom de la matière';
  document.getElementById('subject-modal').classList.remove('hidden');
  lucide.createIcons();
}

function openEditModal(subj) {
  document.getElementById('modal-title').textContent = 'Modifier la matière #' + subj.id;
  document.getElementById('form-action').value = 'edit';
  document.getElementById('form-edit-id').value = subj.id;
  document.getElementById('f-name_fr').value = subj.name_fr || '';
  document.getElementById('f-name_ar').value = subj.name_ar || '';
  document.getElementById('f-name_en').value = subj.name_en || '';
  document.getElementById('f-level').value   = subj.level_id || '';
  document.getElementById('f-icon').value    = subj.icon  || 'BookOpen';
  document.getElementById('f-color').value   = subj.color || '#3B82F6';
  document.getElementById('f-color-text').value = subj.color || '#3B82F6';
  document.getElementById('f-order').value   = subj.order_num || 0;
  document.getElementById('preview-name').textContent = subj.name_fr || '';
  document.getElementById('preview-icon-box').style.background = (subj.color || '#3B82F6') + '20';
  document.getElementById('preview-icon').style.color = subj.color || '#3B82F6';
  document.getElementById('subject-modal').classList.remove('hidden');
  lucide.createIcons();
}

function closeModal() { document.getElementById('subject-modal').classList.add('hidden'); }
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>
