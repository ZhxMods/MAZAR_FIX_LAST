// ============================================================
//  MAZAR — assets/js/xp-system.js
//  XP Completion, Animations, Toast System
// ============================================================

'use strict';

// ── Toast System ─────────────────────────────────────────────
function showToast(message, type = 'info', duration = 4000) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icons = { success: '✅', error: '❌', info: 'ℹ️', xp: '⚡' };
  const toast = document.createElement('div');
  toast.className = `mazar-toast ${type}`;
  toast.innerHTML = `<span style="font-size:1.1rem">${icons[type] || '💬'}</span><span>${message}</span>`;

  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('removing');
    setTimeout(() => toast.remove(), 380);
  }, duration);
}

// ── Floating XP Animation ────────────────────────────────────
function floatXP(amount, nearElement) {
  const el = document.createElement('div');
  el.className = 'xp-float';
  el.textContent = `+${amount} XP ⚡`;

  // Position near button
  let x = window.innerWidth / 2;
  let y = window.innerHeight / 2;
  if (nearElement) {
    const rect = nearElement.getBoundingClientRect();
    x = rect.left + rect.width / 2;
    y = rect.top;
  }

  el.style.cssText = `left:${x}px; top:${y}px; transform:translateX(-50%);`;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 1900);
}

// ── Count-Up Animation ───────────────────────────────────────
function countUp(element, from, to, duration = 800) {
  if (!element) return;
  const steps = 40;
  const increment = (to - from) / steps;
  const stepTime = duration / steps;
  let current = from;
  let step = 0;
  const timer = setInterval(() => {       
    step++;
    current += increment;
    element.textContent = Math.round(step < steps ? current : to).toLocaleString();
    if (step >= steps) clearInterval(timer);
  }, stepTime);
}

// ── Confetti Burst ───────────────────────────────────────────
function burstConfetti() {
  if (typeof confetti === 'undefined') return;
  confetti({
    particleCount: 80,
    spread: 70,
    origin: { y: 0.6 },
    colors: ['#3B82F6', '#FBBF24', '#10B981', '#8B5CF6', '#EF4444']
  });
}

// ── Level-Up Modal ───────────────────────────────────────────
function showLevelUp(newLevel) {
  // Flash overlay
  const overlay = document.createElement('div');
  overlay.className = 'level-up-overlay';
  document.body.appendChild(overlay);
  setTimeout(() => overlay.remove(), 1000);

  // Modal
  const modal = document.createElement('div');
  modal.className = 'level-up-modal';
  modal.innerHTML = `
    <div class="level-up-card">
      <div style="font-size:4rem;margin-bottom:1rem">🎉</div>
      <div style="font-size:1rem;opacity:.8;margin-bottom:.5rem">FÉLICITATIONS !</div>
      <div style="font-size:2rem;font-weight:900;margin-bottom:.5rem">NIVEAU ${newLevel} !</div>
      <div style="font-size:.9rem;opacity:.75;margin-bottom:2rem">Tu as atteint un nouveau niveau. Continue comme ça !</div>
      <button onclick="this.closest('.level-up-modal').remove()"
              style="background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.4);color:#fff;padding:.75rem 2.5rem;border-radius:.75rem;font-weight:700;cursor:pointer;font-size:.9rem;">
        Continuer 🚀
      </button>
    </div>`;
  document.body.appendChild(modal);

  burstConfetti();
  setTimeout(() => burstConfetti(), 500);
}

// ── Update XP UI ─────────────────────────────────────────────
function updateXpUI(data) {
  const oldXP = window.MAZAR_XP || 0;

  // Header elements
  const headerXP   = document.getElementById('header-xp');
  const sidebarXP  = document.getElementById('sidebar-xp');
  const headerLvl  = document.getElementById('header-level');
  const sidebarLvl = document.getElementById('sidebar-level');
  const xpBar      = document.getElementById('sidebar-xp-bar');

  if (headerXP)   countUp(headerXP,  oldXP, data.new_xp);
  if (sidebarXP)  countUp(sidebarXP, oldXP, data.new_xp);
  if (headerLvl)  headerLvl.textContent  = data.new_level;
  if (sidebarLvl) sidebarLvl.textContent = data.new_level;
  if (xpBar) {
    setTimeout(() => { xpBar.style.width = data.percent + '%'; }, 200);
  }

  window.MAZAR_XP    = data.new_xp;
  window.MAZAR_LEVEL = data.new_level;
}

// ── Complete Lesson Handler ───────────────────────────────────
async function completeLesson(lessonId, buttonEl) {
  if (buttonEl.classList.contains('loading')) return;

  buttonEl.classList.add('loading');
  const originalHTML = buttonEl.innerHTML;
  buttonEl.innerHTML = '<span style="display:inline-block;animation:spin .7s linear infinite">⟳</span> Traitement...';

  try {
    const formData = new FormData();
    formData.append('lesson_id',   lessonId);
    formData.append('csrf_token',  window.MAZAR_CSRF || '');

    const res  = await fetch(window.MAZAR_AJAX || '../ajax/complete_lesson.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });

    const data = await res.json();

    if (data.success) {
      // ── SUCCESS ──
      floatXP(10, buttonEl);
      showToast(`+10 XP ! Cours terminé avec succès ! 🎓`, 'xp');
      updateXpUI(data);

      // Mark card visually
      const card = buttonEl.closest('[data-lesson-id]');
      if (card) {
        card.dataset.completed = '1';
        const img = card.querySelector('.relative');
        if (img) {
          const badge = document.createElement('div');
          badge.className = 'absolute inset-0 bg-green-900/40 flex items-center justify-center';
          badge.innerHTML = '<div class="bg-green-500 rounded-full p-2"><svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>';
          img.style.position = 'relative';
          img.appendChild(badge);
        }
      }

      // Replace button with "completed" badge
      buttonEl.outerHTML = `<span class="bg-green-100 text-green-700 text-xs font-semibold py-2 px-3 rounded-lg flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Terminé
      </span>`;

      // Level up?
      if (data.level_up) {
        setTimeout(() => showLevelUp(data.new_level), 600);
      }

    } else if (data.message === 'Already completed') {
      showToast('Ce cours est déjà marqué comme terminé.', 'info');
      buttonEl.innerHTML = originalHTML;
      buttonEl.classList.remove('loading');
    } else {
      showToast('Erreur: ' + (data.message || 'Réessayez.'), 'error');
      buttonEl.innerHTML = originalHTML;
      buttonEl.classList.remove('loading');
    }

  } catch (err) {
    console.error('[MAZAR]', err);
    showToast('Erreur de connexion. Vérifiez votre réseau.', 'error');
    buttonEl.innerHTML = originalHTML;
    buttonEl.classList.remove('loading');
  }
}

// ── Spin keyframe (inline) ────────────────────────────────────
if (!document.getElementById('mazar-spin-style')) {
  const s = document.createElement('style');
  s.id = 'mazar-spin-style';
  s.textContent = '@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
}
