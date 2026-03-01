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
$pageTitle = 'Gérer les niveaux';
$msg       = '';
$msgType   = 'success';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nameAr = trim($_POST['name_ar'] ?? '');
        $nameFr = trim($_POST['name_fr'] ?? '');
        $nameEn = trim($_POST['name_en'] ?? '');
        $slug   = trim($_POST['slug']    ?? '');
        $order  = (int)($_POST['order_num'] ?? 0);

        if (!$nameFr || !$slug) {
            $msg = 'Nom (FR) et slug sont obligatoires.';
            $msgType = 'error';
        } else {
            // Sanitize slug
            $slug = strtolower(preg_replace('/[^a-z0-9\-]/', '', $slug));
            try {
                if ($action === 'add') {
                    $db->prepare(
                        "INSERT INTO levels (name_ar, name_fr, name_en, slug, order_num) VALUES (?,?,?,?,?)"
                    )->execute([$nameAr, $nameFr, $nameEn, $slug, $order]);
                    logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Added level: {$nameFr}");
                    $msg = 'Niveau ajouté avec succès !';
                } else {
                    $id = (int)($_POST['edit_id'] ?? 0);
                    $db->prepare(
                        "UPDATE levels SET name_ar=?, name_fr=?, name_en=?, slug=?, order_num=? WHERE id=?"
                    )->execute([$nameAr, $nameFr, $nameEn, $slug, $order, $id]);
                    logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Edited level #{$id}");
                    $msg = 'Niveau modifié.';
                }
            } catch (Exception $e) {
                $msg = 'Slug déjà utilisé ou erreur de base de données.';
                $msgType = 'error';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['delete_id'] ?? 0);
        try {
            $db->prepare("DELETE FROM levels WHERE id=?")->execute([$id]);
            logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Deleted level #{$id}");
            $msg = 'Niveau supprimé.';
        } catch (Exception $e) {
            $msg = 'Impossible de supprimer : des matières ou utilisateurs y sont liés.';
            $msgType = 'error';
        }
    }
}

// ── Fetch ─────────────────────────────────────────────────────
$levels = $db->query(
    "SELECT l.*, 
            (SELECT COUNT(*) FROM subjects s WHERE s.level_id=l.id) AS subject_count,
            (SELECT COUNT(*) FROM users u WHERE u.grade_level_id=l.id) AS student_count
     FROM levels l ORDER BY l.order_num ASC"
)->fetchAll();

require dirname(__DIR__) . '/admin/_layout.php';
?>

<?php if($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold flex items-center gap-2
     <?= $msgType==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= $msgType==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Action Bar -->
<div class="flex justify-between items-center mb-5">
  <p class="text-gray-500 text-sm"><?= count($levels) ?> niveaux au total</p>
  <button onclick="openAddModal()"
          class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl transition text-sm">
    <i data-lucide="plus" class="w-4 h-4"></i>
    Ajouter un niveau
  </button>
</div>

<!-- Table -->
<div class="admin-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="dt-table w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-left">
          <th class="px-4 py-3 font-semibold">#</th>
          <th class="px-4 py-3 font-semibold">Nom FR</th>
          <th class="px-4 py-3 font-semibold">Nom AR</th>
          <th class="px-4 py-3 font-semibold">Nom EN</th>
          <th class="px-4 py-3 font-semibold">Slug</th>
          <th class="px-4 py-3 font-semibold">Ordre</th>
          <th class="px-4 py-3 font-semibold">Matières</th>
          <th class="px-4 py-3 font-semibold">Étudiants</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach($levels as $level): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-400 text-xs"><?= $level['id'] ?></td>
          <td class="px-4 py-3 font-semibold text-gray-900"><?= htmlspecialchars($level['name_fr']) ?></td>
          <td class="px-4 py-3 text-gray-600" dir="rtl"><?= htmlspecialchars($level['name_ar']) ?></td>
          <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($level['name_en']) ?></td>
          <td class="px-4 py-3"><code class="bg-gray-100 px-2 py-0.5 rounded text-xs text-blue-700"><?= htmlspecialchars($level['slug']) ?></code></td>
          <td class="px-4 py-3 text-gray-600 font-semibold"><?= $level['order_num'] ?></td>
          <td class="px-4 py-3"><span class="bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $level['subject_count'] ?></span></td>
          <td class="px-4 py-3"><span class="bg-green-50 text-green-700 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $level['student_count'] ?></span></td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <button onclick='openEditModal(<?= json_encode($level) ?>)'
                      class="text-blue-600 hover:bg-blue-50 p-1.5 rounded-lg transition">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
              </button>
              <form method="POST" onsubmit="return confirm('Supprimer ce niveau ? Toutes ses matières et cours seront supprimés.')">
                <?= csrfField() ?>
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="delete_id" value="<?= $level['id'] ?>">
                <button type="submit" class="text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ MODAL ══ -->
<div id="level-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 id="modal-title" class="font-black text-gray-900 text-lg">Ajouter un niveau</h2>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl hover:bg-gray-100">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="px-7 py-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action"  id="form-action"  value="add">
      <input type="hidden" name="edit_id" id="form-edit-id" value="">

      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="label-sm">Nom (FR) *</label>
          <input type="text" name="name_fr" id="f-name_fr" class="inp" required placeholder="1ère Année Primaire">
        </div>
        <div>
          <label class="label-sm">Nom (AR)</label>
          <input type="text" name="name_ar" id="f-name_ar" class="inp" dir="rtl" placeholder="السنة الأولى">
        </div>
        <div>
          <label class="label-sm">Nom (EN)</label>
          <input type="text" name="name_en" id="f-name_en" class="inp" placeholder="1st Primary">
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label-sm">Slug * <span class="text-gray-400 font-normal">(ex: 1-primaire)</span></label>
          <input type="text" name="slug" id="f-slug" class="inp" required placeholder="1-primaire">
        </div>
        <div>
          <label class="label-sm">Ordre d'affichage</label>
          <input type="number" name="order_num" id="f-order" class="inp" min="0" value="0">
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
function openAddModal() {
  document.getElementById('modal-title').textContent = 'Ajouter un niveau';
  document.getElementById('form-action').value = 'add';
  document.getElementById('form-edit-id').value = '';
  ['name_fr','name_ar','name_en','slug','order'].forEach(k => {
    const el = document.getElementById('f-' + k);
    if (el) el.value = k === 'order' ? '0' : '';
  });
  document.getElementById('level-modal').classList.remove('hidden');
  lucide.createIcons();
}
function openEditModal(level) {
  document.getElementById('modal-title').textContent = 'Modifier le niveau #' + level.id;
  document.getElementById('form-action').value = 'edit';
  document.getElementById('form-edit-id').value = level.id;
  document.getElementById('f-name_fr').value = level.name_fr || '';
  document.getElementById('f-name_ar').value = level.name_ar || '';
  document.getElementById('f-name_en').value = level.name_en || '';
  document.getElementById('f-slug').value    = level.slug    || '';
  document.getElementById('f-order').value   = level.order_num || 0;
  document.getElementById('level-modal').classList.remove('hidden');
  lucide.createIcons();
}
function closeModal() { document.getElementById('level-modal').classList.add('hidden'); }
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>
