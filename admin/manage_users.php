<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

requireSuperAdmin();   // hard gate — super_admin only

$lang      = getCurrentLang();
$dir       = getDirection();
$db        = getDB();
$pageTitle = 'Gérer les utilisateurs';
$msg       = '';
$msgType   = 'success';

// ── Handle POST Actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    // Full user edit
    if ($uid && $action === 'edit_user') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $role     = $_POST['role']           ?? 'student';
        $gradeId  = (int)($_POST['grade_level_id'] ?? 0);
        $status   = $_POST['status']         ?? 'active';
        $newPass  = $_POST['new_password']   ?? '';

        if (!$fullName || !$email || !$gradeId) {
            $msg = 'Nom, email et niveau sont obligatoires.';
            $msgType = 'error';
        } else {
            // Check email uniqueness (excluding current user)
            $chk = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $chk->execute([$email, $uid]);
            if ($chk->fetch()) {
                $msg = 'Cet email est déjà utilisé.';
                $msgType = 'error';
            } else {
                $sql    = "UPDATE users SET full_name=?, email=?, role=?, grade_level_id=?, status=?";
                $params = [$fullName, $email, $role, $gradeId, $status];
                if ($newPass !== '') {
                    if (strlen($newPass) < 8) {
                        $msg = 'Le mot de passe doit contenir au moins 8 caractères.';
                        $msgType = 'error';
                        goto skip_edit;
                    }
                    $sql .= ", password=?";
                    $params[] = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                }
                $sql     .= " WHERE id=?";
                $params[] = $uid;
                $db->prepare($sql)->execute($params);
                logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Edited user #{$uid}");
                $msg = 'Utilisateur mis à jour.';
            }
        }
        skip_edit:;
    }

    if ($uid && $action === 'add_xp') {
        $amount = (int)($_POST['xp_amount'] ?? 0);
        if ($amount > 0 && $amount <= 9999) {
            awardXP($uid, $amount, 'Admin XP bonus');
            $msg = "+{$amount} XP ajoutés à l'utilisateur #{$uid}";
        } else {
            $msg = 'Montant XP invalide (1–9999)';
            $msgType = 'error';
        }
    }

    if ($uid && $action === 'reset_xp') {
        $db->prepare("UPDATE users SET xp_points=0, level=1 WHERE id=?")->execute([$uid]);
        logActivity($_SESSION[SESS_USER_ID], 'admin_action', "User #{$uid} XP reset");
        $msg = "XP remis à 0 pour l'utilisateur #{$uid}";
    }

    if ($uid && $action === 'delete_user') {
        if ($uid == $_SESSION[SESS_USER_ID]) {
            $msg = 'Vous ne pouvez pas supprimer votre propre compte.';
            $msgType = 'error';
        } else {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            logActivity($_SESSION[SESS_USER_ID], 'admin_action', "Deleted user #{$uid}");
            $msg = 'Utilisateur supprimé.';
        }
    }
}

// ── Fetch Data ────────────────────────────────────────────────
$users  = $db->query(
    "SELECT u.id, u.full_name, u.email, u.role, u.xp_points, u.level, u.status, u.created_at,
            u.grade_level_id,
            lv.name_fr AS grade,
            (SELECT COUNT(*) FROM user_lesson_completions ulc WHERE ulc.user_id=u.id) AS completions
     FROM users u
     JOIN levels lv ON lv.id = u.grade_level_id
     ORDER BY u.xp_points DESC"
)->fetchAll();

$levels = getAllLevels();

require dirname(__DIR__) . '/admin/_layout.php';
?>

<?php if($msg): ?>
<div class="mb-5 p-4 rounded-xl text-sm font-semibold flex items-center gap-2
     <?= $msgType==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= $msgType==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="grid grid-cols-3 gap-4 mb-6">
<?php
  $active = count(array_filter($users, fn($u)=>$u['status']==='active'));
  $banned = count($users) - $active;
  $totalXP = array_sum(array_column($users,'xp_points'));
  $cards = [
    ['Étudiants actifs', $active,  'user-check','#10B981'],
    ['Suspendus',        $banned,  'user-x',    '#EF4444'],
    ['XP Total',         number_format($totalXP).' XP', 'zap', '#F59E0B'],
  ];
  foreach($cards as [$label,$val,$icon,$color]):
?>
  <div class="stat-card">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background:<?= $color ?>18">
      <i data-lucide="<?= $icon ?>" class="w-5 h-5" style="color:<?= $color ?>"></i>
    </div>
    <div class="text-2xl font-black text-gray-900"><?= $val ?></div>
    <div class="text-gray-500 text-sm"><?= $label ?></div>
  </div>
<?php endforeach; ?>
</div>

<!-- ── Users Table ── -->
<div class="admin-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="dt-table w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-left">
          <th class="px-4 py-3 font-semibold">#</th>
          <th class="px-4 py-3 font-semibold">Utilisateur</th>
          <th class="px-4 py-3 font-semibold">Email</th>
          <th class="px-4 py-3 font-semibold">Rôle</th>
          <th class="px-4 py-3 font-semibold">Niveau</th>
          <th class="px-4 py-3 font-semibold">XP / Lvl</th>
          <th class="px-4 py-3 font-semibold">Cours</th>
          <th class="px-4 py-3 font-semibold">Statut</th>
          <th class="px-4 py-3 font-semibold">Inscrit le</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php foreach($users as $u): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-400 text-xs"><?= $u['id'] ?></td>

          <!-- User -->
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0
                <?= $u['role']==='super_admin'?'bg-purple-600':($u['role']==='admin'?'bg-blue-700':($u['role']==='staff'?'bg-cyan-600':'bg-blue-100 !text-blue-700')) ?>">
                <?= mb_strtoupper(mb_substr($u['full_name'],0,1)) ?>
              </div>
              <div>
                <div class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($u['full_name']) ?></div>
              </div>
            </div>
          </td>

          <!-- Email -->
          <td class="px-4 py-3 text-gray-500 text-xs max-w-[140px] truncate"><?= htmlspecialchars($u['email']) ?></td>

          <!-- Role -->
          <td class="px-4 py-3">
            <?php
              if ($u['role'] === 'super_admin')     { $rolePill = 'bg-purple-100 text-purple-700'; $roleLabel = '👑 Super Admin'; }
              elseif ($u['role'] === 'admin')        { $rolePill = 'bg-blue-100 text-blue-700';    $roleLabel = '🛡 Admin';       }
              elseif ($u['role'] === 'staff')        { $rolePill = 'bg-cyan-100 text-cyan-700';    $roleLabel = '🔧 Staff';       }
              else                                   { $rolePill = 'bg-gray-100 text-gray-600';    $roleLabel = '🎓 Étudiant';    }
            ?>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $rolePill ?>"><?= $roleLabel ?></span>
          </td>

          <!-- Grade -->
          <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($u['grade']) ?></td>

          <!-- XP -->
          <td class="px-4 py-3">
            <div class="font-bold text-yellow-600 text-sm"><?= number_format($u['xp_points']) ?> XP</div>
            <div class="text-gray-400 text-xs">Niv. <?= $u['level'] ?></div>
          </td>

          <!-- Completions -->
          <td class="px-4 py-3 text-gray-600 text-sm font-semibold"><?= $u['completions'] ?></td>

          <!-- Status -->
          <td class="px-4 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold
              <?= $u['status']==='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
              <?= $u['status']==='active' ? '✓ Actif' : '✗ Suspendu' ?>
            </span>
          </td>

          <!-- Joined -->
          <td class="px-4 py-3 text-gray-400 text-xs"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>

          <!-- Actions -->
          <td class="px-4 py-3">
            <?php if($u['id'] != $_SESSION[SESS_USER_ID]): ?>
            <div class="flex items-center gap-1.5">
              <!-- ✏ Edit button -->
              <button
                onclick='openEditUserModal(<?= json_encode([
                  "id"             => $u["id"],
                  "full_name"      => $u["full_name"],
                  "email"          => $u["email"],
                  "role"           => $u["role"],
                  "grade_level_id" => $u["grade_level_id"],
                  "status"         => $u["status"],
                ]) ?>)'
                class="p-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                title="Modifier">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
              </button>

              <!-- ⚡ XP -->
              <button
                onclick="openXpModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')"
                class="p-1.5 rounded-lg bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition"
                title="Ajouter XP">
                <i data-lucide="zap" class="w-4 h-4"></i>
              </button>

              <!-- 🔄 Reset XP -->
              <form method="POST" class="inline" onsubmit="return confirm('Réinitialiser le XP ?')">
                <?= csrfField() ?>
                <input type="hidden" name="action"  value="reset_xp">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit"
                  class="p-1.5 rounded-lg bg-gray-50 text-gray-500 hover:bg-gray-100 transition"
                  title="Réinitialiser XP">
                  <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
              </form>

              <!-- 🗑 Delete -->
              <form method="POST" class="inline" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')">
                <?= csrfField() ?>
                <input type="hidden" name="action"  value="delete_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit"
                  class="p-1.5 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition"
                  title="Supprimer">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
            </div>
            <?php else: ?>
            <span class="text-gray-300 text-xs px-2">— vous —</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($users)): ?>
        <tr><td colspan="10" class="px-4 py-12 text-center text-gray-400">Aucun utilisateur.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ EDIT USER MODAL ══════════════════════════════════════ -->
<div id="edit-user-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeEditUserModal()">
  <div class="modal-box">
    <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-black text-gray-900 text-lg">Modifier l'utilisateur</h2>
      <button onclick="closeEditUserModal()" class="text-gray-400 hover:text-gray-700 p-2 rounded-xl hover:bg-gray-100 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <form method="POST" class="px-7 py-6 space-y-5">
      <?= csrfField() ?>
      <input type="hidden" name="action"  value="edit_user">
      <input type="hidden" name="user_id" id="eu-id" value="">

      <!-- Name + Email -->
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label-sm">Nom complet *</label>
          <input type="text" name="full_name" id="eu-name" class="inp" required placeholder="Nom complet">
        </div>
        <div>
          <label class="label-sm">Email *</label>
          <input type="email" name="email" id="eu-email" class="inp" required placeholder="email@example.ma">
        </div>
      </div>

      <!-- Role + Grade -->
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label-sm">Rôle *</label>
          <select name="role" id="eu-role" class="inp">
            <option value="student">🎓 Étudiant</option>
            <option value="staff">🔧 Staff</option>
            <option value="admin">🛡 Admin</option>
            <option value="super_admin">👑 Super Admin</option>
          </select>
        </div>
        <div>
          <label class="label-sm">Niveau scolaire *</label>
          <select name="grade_level_id" id="eu-grade" class="inp" required>
            <option value="">— Sélectionner —</option>
            <?php foreach($levels as $lv): ?>
            <option value="<?= $lv['id'] ?>"><?= htmlspecialchars($lv['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Status -->
      <div>
        <label class="label-sm">Statut du compte</label>
        <select name="status" id="eu-status" class="inp">
          <option value="active">✓ Actif</option>
          <option value="banned">✗ Suspendu</option>
        </select>
      </div>

      <!-- New Password (optional) -->
      <div>
        <label class="label-sm">Nouveau mot de passe <span class="text-gray-400 font-normal">(laisser vide pour ne pas changer)</span></label>
        <input type="password" name="new_password" id="eu-pass" class="inp" placeholder="Min. 8 caractères">
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i> Enregistrer les modifications
        </button>
        <button type="button" onclick="closeEditUserModal()" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══ ADD XP MODAL ══════════════════════════════════════════ -->
<div id="xp-modal" class="modal-overlay hidden" onclick="if(event.target===this)closeXpModal()">
  <div class="modal-box-sm p-7">
    <h3 class="font-black text-gray-900 text-lg mb-1">⚡ Ajouter XP</h3>
    <p id="xp-user-name" class="text-gray-500 text-sm mb-5"></p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"  value="add_xp">
      <input type="hidden" name="user_id" id="xp-uid" value="">
      <label class="label-sm">Montant XP (1–9999)</label>
      <input type="number" name="xp_amount" min="1" max="9999" value="50"
             class="inp mb-4 mt-1">
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 rounded-xl transition">
          ⚡ Ajouter
        </button>
        <button type="button" onclick="closeXpModal()" class="px-5 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>

<style>
  .inp { width:100%; padding:.625rem 1rem; border:1px solid #e5e7eb; border-radius:.75rem; font-size:.875rem; outline:none; transition: border-color .15s, box-shadow .15s; background:#fff; color:#111827; }
  .inp:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  .label-sm { display:block; font-size:.8125rem; font-weight:600; color:#374151; margin-bottom:.375rem; }
</style>

<script>
function openEditUserModal(u) {
  document.getElementById('eu-id').value     = u.id;
  document.getElementById('eu-name').value   = u.full_name;
  document.getElementById('eu-email').value  = u.email;
  document.getElementById('eu-role').value   = u.role;
  document.getElementById('eu-grade').value  = u.grade_level_id;
  document.getElementById('eu-status').value = u.status;
  document.getElementById('eu-pass').value   = '';
  document.getElementById('edit-user-modal').classList.remove('hidden');
  lucide.createIcons();
}
function closeEditUserModal() { document.getElementById('edit-user-modal').classList.add('hidden'); }

function openXpModal(uid, name) {
  document.getElementById('xp-uid').value       = uid;
  document.getElementById('xp-user-name').textContent = 'Étudiant : ' + name;
  document.getElementById('xp-modal').classList.remove('hidden');
}
function closeXpModal() { document.getElementById('xp-modal').classList.add('hidden'); }
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>