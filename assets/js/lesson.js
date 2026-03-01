/* ============================================================
   MAZAR — assets/js/lesson.js
   Lesson page engine: progress tracking, PDF reader, XP
   Requires: xp-system.js, PDF.js (cdn), lucide
============================================================ */
'use strict';

/* ══════════════════════════════════════════════════════
   PROGRESS TRACKING
══════════════════════════════════════════════════════ */

var elapsedSecs   = 0;
var progressReady = false;
var trackInterval = null;
var ytPlayer      = null;
var ytTrackHandle = null;

function qs(id) { return document.getElementById(id); }

function fmtTime(s) {
  s = Math.max(0, Math.floor(s));
  var m   = Math.floor(s / 60);
  var sec = s % 60;
  return m + ':' + (sec < 10 ? '0' : '') + sec;
}

function updateProgressUI() {
  if (window.LESSON_ALREADY_DONE) return;

  var pct        = Math.min(100, (elapsedSecs / window.LESSON_REQUIRED_SECS) * 100);
  var bar        = qs('lesson-progress-bar');
  var pill       = qs('time-pill');
  var pillTxt    = qs('time-pill-text');
  var elapsedEl  = qs('elapsed-display');
  var btn        = qs('complete-btn-main');
  var btnIcon    = qs('btn-icon');
  var btnText    = qs('btn-text');
  var btnSub     = qs('btn-subtext');

  if (bar)      bar.style.width = pct + '%';
  if (elapsedEl) elapsedEl.textContent = fmtTime(elapsedSecs);

  if (pct >= 100 && !progressReady) {
    progressReady = true;
    if (trackInterval)  { clearInterval(trackInterval);  trackInterval  = null; }
    if (ytTrackHandle)  { clearInterval(ytTrackHandle);  ytTrackHandle  = null; }

    if (bar)   { bar.classList.remove('active'); bar.classList.add('done'); }
    if (pill)  { pill.className = 'time-pill ready'; }
    if (pillTxt) pillTxt.textContent = 'Prêt !';

    var section = qs('progress-section');
    if (section) {
      section.style.background   = 'linear-gradient(135deg, #f0fdf4, #dcfce7)';
      section.style.borderColor  = '#86efac';
    }

    var hintEl = qs('progress-hint-text');
    if (hintEl) {
      hintEl.innerHTML = '🎉 Excellent ! Vous pouvez maintenant gagner <strong style="color:#16a34a">+' + window.LESSON_XP_REWARD + ' XP</strong>';
    }

    if (btn) {
      btn.classList.remove('locked');
      btn.classList.add('pending');
      btn.disabled = false;
    }
    if (btnIcon) {
      btnIcon.setAttribute('data-lucide', 'check-circle');
      btnIcon.classList.remove('lock-pulse');
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    if (btnText) btnText.textContent = 'Marquer comme terminé';
    if (btnSub)  btnSub.textContent  = 'Cliquez pour gagner +' + window.LESSON_XP_REWARD + ' XP !';

    showToast('🎓 Leçon complète ! Cliquez pour gagner vos XP.', 'info', 3000);

  } else if (!progressReady) {
    if (bar && !bar.classList.contains('active')) bar.classList.add('active');
    if (pill)    { pill.className = 'time-pill running'; }
    if (pillTxt) pillTxt.textContent = fmtTime(window.LESSON_REQUIRED_SECS - elapsedSecs) + ' restant';
  }
}

/* ── Page-timer tracking (PDF / Book / non-YouTube) ── */
function startPageTimer() {
  if (window.LESSON_ALREADY_DONE || progressReady) return;
  if (trackInterval) return;
  trackInterval = setInterval(function() {
    elapsedSecs++;
    updateProgressUI();
    if (progressReady) { clearInterval(trackInterval); trackInterval = null; }
  }, 1000);
}

function stopPageTimer() {
  if (trackInterval) { clearInterval(trackInterval); trackInterval = null; }
}

/* ── YouTube tracking (called by YouTube IFrame API) ── */
function startYTTracking() {
  if (window.LESSON_ALREADY_DONE || progressReady) return;
  if (ytTrackHandle) return;
  ytTrackHandle = setInterval(function() {
    elapsedSecs++;
    updateProgressUI();
    if (progressReady) { clearInterval(ytTrackHandle); ytTrackHandle = null; }
  }, 1000);
}

function stopYTTracking() {
  if (ytTrackHandle) { clearInterval(ytTrackHandle); ytTrackHandle = null; }
}

/* Called by YouTube IFrame API */
function onYouTubeIframeAPIReady() {
  if (!window.LESSON_YT_ID) return;
  ytPlayer = new YT.Player('yt-player', {
    videoId: window.LESSON_YT_ID,
    playerVars: { rel: 0, modestbranding: 1, color: 'white', enablejsapi: 1 },
    events: {
      onReady: function(e) {
        var dur = e.target.getDuration() || 0;
        if (window.LESSON_DURATION_MINS === 0 && dur > 0) {
          window.LESSON_REQUIRED_SECS = Math.max(45, Math.floor(dur * 0.80));
          var rd = qs('required-display');
          var rh = qs('remaining-hint');
          if (rd) rd.textContent = fmtTime(window.LESSON_REQUIRED_SECS);
          if (rh) rh.textContent = 'Requis : ' + Math.ceil(window.LESSON_REQUIRED_SECS / 60) + ' min';
        }
      },
      onStateChange: function(e) {
        if (e.data === YT.PlayerState.PLAYING) startYTTracking();
        else stopYTTracking();
      }
    }
  });
}

/* ══════════════════════════════════════════════════════
   COMPLETE LESSON (with server-side anti-cheat)
══════════════════════════════════════════════════════ */
async function completeLessonPage(lessonId) {
  if (!progressReady) {
    showToast('⏳ Regardez/lisez la leçon entièrement d\'abord !', 'error', 3000);
    return;
  }

  var btn = qs('complete-btn-main');
  if (!btn || btn.disabled || btn.classList.contains('done')) return;
  if (btn.classList.contains('loading')) return;

  btn.classList.add('loading');
  var orig = btn.innerHTML;
  btn.innerHTML = '<span style="display:inline-block;animation:spin .7s linear infinite">⟳</span> Traitement...';
  btn.disabled = true;

  try {
    var fd = new FormData();
    fd.append('lesson_id',    lessonId);
    fd.append('csrf_token',   window.MAZAR_CSRF);
    fd.append('elapsed_secs', Math.floor(elapsedSecs));

    var res  = await fetch(window.MAZAR_AJAX, { method: 'POST', body: fd, credentials: 'same-origin' });
    var data = await res.json();

    if (data.success) {
      floatXP(window.LESSON_XP_REWARD, btn);
      showToast('+' + window.LESSON_XP_REWARD + ' XP ! Leçon terminée avec succès ! 🎓', 'xp');

      var hXP   = qs('header-xp');
      var xpDisp = qs('xp-display');
      if (hXP)    countUp(hXP,    window.MAZAR_XP, data.new_xp);
      if (xpDisp) countUp(xpDisp, window.MAZAR_XP, data.new_xp);
      setTimeout(function() {
        var bar = qs('xp-bar-fill');
        if (bar) bar.style.width = data.percent + '%';
      }, 300);

      window.MAZAR_XP    = data.new_xp;
      window.MAZAR_LEVEL = data.new_level;

      btn.className = 'complete-btn-main done';
      btn.innerHTML = '<i data-lucide="check-circle-2" style="width:1.25rem;height:1.25rem;"></i> Leçon terminée !';
      btn.disabled  = true;
      if (typeof lucide !== 'undefined') lucide.createIcons();

      var sub = qs('btn-subtext');
      if (sub) sub.textContent = '✅ +' + window.LESSON_XP_REWARD + ' XP gagnés';

      window.LESSON_ALREADY_DONE = true;
      if (data.level_up) setTimeout(function() { showLevelUp(data.new_level); }, 800);

    } else if (data.message === 'Already completed') {
      showToast('Cette leçon est déjà marquée comme terminée.', 'info');
      btn.className = 'complete-btn-main done';
      btn.innerHTML = '<i data-lucide="check-circle-2" style="width:1.25rem;height:1.25rem;"></i> Leçon terminée !';
      btn.disabled  = true;
      if (typeof lucide !== 'undefined') lucide.createIcons();

    } else if (data.message === 'too_early') {
      showToast('⏳ Passez plus de temps sur la leçon ! (' + data.hint + ')', 'error', 4000);
      btn.innerHTML = orig;
      btn.disabled  = false;
      btn.classList.remove('loading');

    } else {
      showToast('Erreur : ' + (data.message || 'Réessayez.'), 'error');
      btn.innerHTML = orig;
      btn.disabled  = false;
      btn.classList.remove('loading');
    }

  } catch (err) {
    showToast('Erreur de connexion. Vérifiez votre réseau.', 'error');
    btn.innerHTML = orig;
    btn.disabled  = false;
    btn.classList.remove('loading');
  }
}

/* ══════════════════════════════════════════════════════
   PDF READER (PDF.js)
   Handles: MediaFire direct links, direct .pdf URLs,
            Google Viewer fallback
══════════════════════════════════════════════════════ */
var pdfDoc        = null;
var pdfCurrentPage = 1;
var pdfTotalPages  = 0;
var pdfScale       = 1.2;
var pdfRendering   = false;
var pdfPendingPage = null;

function initPdfReader(pdfUrl) {
  var container = qs('pdf-reader-wrapper');
  if (!container) return;

  // Show loading state
  var loadingEl = qs('pdf-loading-state');
  if (loadingEl) loadingEl.style.display = 'flex';

  // Set PDF.js worker
  if (typeof pdfjsLib === 'undefined') {
    showPdfError('PDF.js non chargé. Vérifiez votre connexion.');
    return;
  }
  pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  var loadingTask = pdfjsLib.getDocument({
    url: pdfUrl,
    cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
    cMapPacked: true,
    withCredentials: false
  });

  loadingTask.promise.then(function(pdf) {
    pdfDoc       = pdf;
    pdfTotalPages = pdf.numPages;

    if (loadingEl) loadingEl.style.display = 'none';

    var toolbar = qs('pdf-toolbar');
    var canvas  = qs('pdf-canvas-container');
    var status  = qs('pdf-status-bar');
    if (toolbar) toolbar.style.display = 'flex';
    if (canvas)  canvas.style.display  = 'flex';
    if (status)  status.style.display  = 'flex';

    updatePdfPageInfo();
    renderPdfPage(1);

  }).catch(function(err) {
    console.warn('[MAZAR PDF]', err);
    showPdfError('Impossible de charger le PDF. Vérifiez le lien ou essayez plus tard.');
  });
}

function renderPdfPage(num) {
  if (!pdfDoc) return;
  if (pdfRendering) { pdfPendingPage = num; return; }
  pdfRendering = true;
  pdfCurrentPage = num;
  updatePdfPageInfo();

  pdfDoc.getPage(num).then(function(page) {
    var container = qs('pdf-canvas-container');
    if (!container) return;

    // Calculate scale to fit container width
    var containerWidth = container.clientWidth - 32;
    var viewport0 = page.getViewport({ scale: 1 });
    var autoScale = containerWidth / viewport0.width;
    var finalScale = pdfScale === 'auto' ? autoScale : (autoScale * pdfScale);

    var viewport = page.getViewport({ scale: finalScale });

    // Clear old canvases
    container.innerHTML = '';

    var canvas  = document.createElement('canvas');
    var context = canvas.getContext('2d');
    canvas.width  = viewport.width;
    canvas.height = viewport.height;
    container.appendChild(canvas);

    page.render({ canvasContext: context, viewport: viewport }).promise.then(function() {
      pdfRendering = false;
      if (pdfPendingPage !== null) {
        renderPdfPage(pdfPendingPage);
        pdfPendingPage = null;
      }
      // Scroll to top of container
      container.scrollTop = 0;
    });
  });
}

function updatePdfPageInfo() {
  var inp   = qs('pdf-page-input');
  var total = qs('pdf-page-total');
  var prev  = qs('pdf-prev-btn');
  var next  = qs('pdf-next-btn');
  var stat  = qs('pdf-status-text');

  if (inp)   inp.value = pdfCurrentPage;
  if (total) total.textContent = '/ ' + pdfTotalPages;
  if (prev)  prev.disabled = pdfCurrentPage <= 1;
  if (next)  next.disabled = pdfCurrentPage >= pdfTotalPages;
  if (stat)  stat.textContent = 'Page ' + pdfCurrentPage + ' sur ' + pdfTotalPages + ' · PDF sécurisé';
}

function pdfPrevPage() {
  if (pdfCurrentPage <= 1) return;
  renderPdfPage(pdfCurrentPage - 1);
}

function pdfNextPage() {
  if (pdfCurrentPage >= pdfTotalPages) return;
  renderPdfPage(pdfCurrentPage + 1);
}

function pdfGoToPage(val) {
  var n = parseInt(val, 10);
  if (isNaN(n) || n < 1 || n > pdfTotalPages) return;
  renderPdfPage(n);
}

function pdfSetZoom(val) {
  var z = parseFloat(val);
  if (!isNaN(z)) {
    pdfScale = z;
    renderPdfPage(pdfCurrentPage);
  }
}

function showPdfError(msg) {
  var loadingEl = qs('pdf-loading-state');
  if (loadingEl) loadingEl.style.display = 'none';
  var errEl = qs('pdf-error-state');
  if (errEl) {
    errEl.style.display = 'flex';
    var msgEl = errEl.querySelector('.pdf-err-msg');
    if (msgEl) msgEl.textContent = msg;
  }
}

/* ── Keyboard navigation for PDF ── */
document.addEventListener('keydown', function(e) {
  if (!pdfDoc) return;
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); pdfNextPage(); }
  if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); pdfPrevPage(); }
});

/* ══════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
  if (typeof lucide !== 'undefined') lucide.createIcons();

  // If lesson already completed, nothing to init — content hidden behind overlay
  if (window.LESSON_ALREADY_DONE) return;

  // Start page timer for non-YouTube content
  if (!window.LESSON_IS_YOUTUBE) {
    startPageTimer();
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) stopPageTimer();
      else if (!window.LESSON_ALREADY_DONE && !progressReady) startPageTimer();
    });
  }

  // Load YouTube IFrame API if needed
  if (window.LESSON_IS_YOUTUBE) {
    var tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
  }

  // Init PDF reader if needed
  if (window.LESSON_PDF_URL) {
    setTimeout(function() {
      initPdfReader(window.LESSON_PDF_URL);
    }, 200);
  }
});