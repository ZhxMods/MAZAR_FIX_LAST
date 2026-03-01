<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $errorCode ?> — <?= $errorTitle ?> · MAZAR</title>
  <meta name="robots" content="noindex, nofollow">

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Poppins', sans-serif; min-height: 100vh; display: flex; flex-direction: column; background: #0b1120; }
    .hero-bg {
      background:
        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(29,78,216,.55) 0%, transparent 70%),
        linear-gradient(180deg, #0f1b35 0%, #0b1120 100%);
    }
    .grid-overlay {
      background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
      background-size: 48px 48px;
    }
    .blob { position: absolute; border-radius: 50%; filter: blur(90px); pointer-events: none; }
    .error-code {
      font-size: clamp(5.5rem, 16vw, 10rem); font-weight: 900; line-height: 1; letter-spacing: -0.05em;
      background: linear-gradient(135deg, #ffffff 20%, rgba(147,197,253,.55) 70%, rgba(96,165,250,.3) 100%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; user-select: none;
    }
    .glass-card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.09); backdrop-filter: blur(12px); border-radius: 1.25rem; }
    .status-badge { display: inline-flex; align-items: center; gap: .45rem; padding: .3rem .85rem; border-radius: 999px; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .badge-error  { background: rgba(239,68,68,.15);  color: #fca5a5; border: 1px solid rgba(239,68,68,.25); }
    .badge-warn   { background: rgba(251,191,36,.12); color: #fde68a; border: 1px solid rgba(251,191,36,.22); }
    .badge-server { background: rgba(168,85,247,.15); color: #d8b4fe; border: 1px solid rgba(168,85,247,.25); }
    .badge-maint  { background: rgba(251,191,36,.12); color: #fde68a; border: 1px solid rgba(251,191,36,.22); }
    .detail-pill { display: inline-flex; align-items: center; gap: .35rem; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); border-radius: .5rem; padding: .3rem .7rem; font-size: .7rem; color: rgba(255,255,255,.5); font-family: 'JetBrains Mono', monospace; }
    @keyframes floatEmoji { 0%,100%{transform:translateY(0) rotate(-3deg) scale(1)} 50%{transform:translateY(-12px) rotate(3deg) scale(1.05)} }
    @keyframes slideUp    { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
    @keyframes pulseRing  { 0%{transform:scale(1);opacity:.5} 70%,100%{transform:scale(1.65);opacity:0} }
    @keyframes progressSweep { 0%{transform:translateX(-100%)} 100%{transform:translateX(500%)} }
    @keyframes dotBounce  { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-7px)} }
    .float-emoji { animation: floatEmoji 3.5s ease-in-out infinite; display: inline-block; }
    .slide-up    { animation: slideUp .55s cubic-bezier(.22,1,.36,1) both; }
    .slide-up-d1 { animation-delay: .1s; }
    .slide-up-d2 { animation-delay: .22s; }
    .slide-up-d3 { animation-delay: .35s; }
    .slide-up-d4 { animation-delay: .48s; }
    .pulse-ring::before { content:''; position:absolute; inset:-10px; border-radius:50%; border:2px solid rgba(251,191,36,.45); animation:pulseRing 2.2s ease-out infinite; }
    .progress-sweep { animation: progressSweep 1.7s ease-in-out infinite; width: 30%; }
    .dot-1 { animation: dotBounce 1.2s ease-in-out 0s infinite; }
    .dot-2 { animation: dotBounce 1.2s ease-in-out .18s infinite; }
    .dot-3 { animation: dotBounce 1.2s ease-in-out .36s infinite; }
    .btn-primary { background:#fff; color:#1d4ed8; font-weight:700; padding:.78rem 1.8rem; border-radius:.875rem; display:inline-flex; align-items:center; gap:.5rem; transition:transform .2s,box-shadow .2s,background .15s; text-decoration:none; font-size:.875rem; white-space:nowrap; box-shadow:0 4px 20px rgba(0,0,0,.2); border:none; cursor:pointer; }
    .btn-primary:hover { transform:translateY(-2px); box-shadow:0 12px 32px rgba(0,0,0,.3); background:#eff6ff; }
    .btn-secondary { border:1.5px solid rgba(255,255,255,.2); color:rgba(255,255,255,.85); font-weight:600; padding:.75rem 1.6rem; border-radius:.875rem; display:inline-flex; align-items:center; gap:.5rem; transition:background .15s,border-color .15s,transform .2s; text-decoration:none; font-size:.875rem; white-space:nowrap; background:transparent; cursor:pointer; }
    .btn-secondary:hover { background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.4); transform:translateY(-2px); }
    .search-box { display:flex; align-items:center; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:.875rem; padding:.6rem 1rem; gap:.6rem; transition:border-color .2s,background .2s; }
    .search-box:focus-within { border-color:rgba(96,165,250,.5); background:rgba(255,255,255,.09); }
    .search-box input { background:transparent; border:none; outline:none; color:#fff; font-family:'Poppins',sans-serif; font-size:.875rem; flex:1; min-width:0; }
    .search-box input::placeholder { color:rgba(255,255,255,.35); }
    .search-box button { background:#2563eb; border:none; border-radius:.5rem; padding:.35rem .7rem; cursor:pointer; display:flex; align-items:center; transition:background .15s; }
    .search-box button:hover { background:#1d4ed8; }
    .quick-link { display:inline-flex; align-items:center; gap:.35rem; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); color:rgba(255,255,255,.7); padding:.38rem .85rem; border-radius:999px; font-size:.75rem; font-weight:600; text-decoration:none; transition:background .15s,color .15s,border-color .15s,transform .15s; }
    .quick-link:hover { background:rgba(255,255,255,.14); color:#fff; border-color:rgba(255,255,255,.22); transform:translateY(-1px); }
    .nav-link { display:flex; align-items:center; gap:.4rem; padding:.5rem .85rem; border-radius:.625rem; font-size:.8rem; font-weight:600; color:rgba(255,255,255,.6); text-decoration:none; transition:background .15s,color .15s; }
    .nav-link:hover { background:rgba(255,255,255,.08); color:#fff; }
    .divider { height:1px; background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent); }
    .meta-row { display:flex; align-items:center; gap:.4rem; font-size:.72rem; color:rgba(255,255,255,.35); font-family:'JetBrains Mono',monospace; }
    .meta-row span { color:rgba(255,255,255,.55); }
    footer { background:#080e1c; border-top:1px solid rgba(255,255,255,.06); }
  </style>
</head>
<body>

<main class="hero-bg grid-overlay flex-1 flex flex-col relative overflow-hidden">

  <!-- Blobs -->
  <div class="blob bg-blue-700 opacity-20" style="width:500px;height:500px;top:-120px;right:-80px"></div>
  <div class="blob bg-indigo-500 opacity-10" style="width:350px;height:350px;bottom:-80px;left:-60px"></div>

  <!-- TOP NAV -->
  <nav class="relative z-20 w-full px-5 py-3 slide-up flex items-center justify-between" style="border-bottom:1px solid rgba(255,255,255,.06)">

    <!-- Logo -->
    <a href="https://mazar.zya.me/" class="flex items-center">
      <img src="../assets/images/mazar.avif"
           alt="MAZAR"
           style="height:38px;width:auto;object-fit:contain;filter:drop-shadow(0 0 10px rgba(37,99,235,.5))"
           onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex'">
      <!-- Fallback if image fails to load -->
      <div style="display:none;align-items:center;gap:10px">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#1d4ed8);box-shadow:0 0 20px rgba(37,99,235,.4);display:flex;align-items:center;justify-content:center">
          <i data-lucide="graduation-cap" style="width:20px;height:20px;color:#fff"></i>
        </div>
        <div>
          <div style="color:#fff;font-weight:900;font-size:1rem;letter-spacing:-.02em;line-height:1">MAZAR</div>
          <div style="color:rgba(96,165,250,.7);font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;line-height:1;margin-top:2px">Éducation · Maroc</div>
        </div>
      </div>
    </a>

    <!-- Desktop nav links -->
    <div class="hidden md:flex items-center gap-1">
      <a href="https://mazar.zya.me/" class="nav-link">
        <i data-lucide="home" style="width:14px;height:14px"></i> Accueil
      </a>
      <a href="https://mazar.zya.me/student/dashboard.php" class="nav-link">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px"></i> Tableau de bord
      </a>
      <a href="https://mazar.zya.me/student/mazar-ai.php" class="nav-link">
        <i data-lucide="sparkles" style="width:14px;height:14px"></i> MAZAR AI
      </a>
    </div>

    <!-- Auth buttons -->
    <div class="flex items-center gap-2">
      <a href="https://mazar.zya.me/login.php" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold transition-colors" style="color:rgba(255,255,255,.6);text-decoration:none">
        <i data-lucide="log-in" style="width:14px;height:14px"></i> Connexion
      </a>
      <a href="https://mazar.zya.me/register.php" class="inline-flex items-center gap-1.5 font-bold" style="background:#fff;color:#1d4ed8;padding:.42rem 1rem;border-radius:.6rem;font-size:.8rem;text-decoration:none">
        S'inscrire
      </a>
    </div>
  </nav>

  <!-- CENTERED CONTENT -->
  <div class="relative z-10 flex-1 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-2xl">

      <!-- Badge row -->
      <div class="slide-up flex flex-wrap items-center justify-center gap-2 mb-6">
        <?php
          $badgeClass = 'badge-error';
          if (!empty($isMaintenance) || $errorCode==503) $badgeClass = 'badge-maint';
          elseif ($errorCode==500) $badgeClass = 'badge-server';
          elseif ($errorCode==401 || $errorCode==403) $badgeClass = 'badge-warn';
        ?>
        <div class="status-badge <?= $badgeClass ?>">
          <div style="width:6px;height:6px;border-radius:50%;background:currentColor;box-shadow:0 0 6px currentColor"></div>
          HTTP <?= $errorCode ?>
        </div>
        <div class="detail-pill">
          <i data-lucide="clock" style="width:10px;height:10px"></i>
          <?= date('d/m/Y · H:i:s') ?>
        </div>
        <div class="detail-pill">
          <i data-lucide="hash" style="width:10px;height:10px"></i>
          ERR-<?= strtoupper(substr(md5(microtime()), 0, 8)) ?>
        </div>
      </div>

      <!-- Emoji -->
      <div class="slide-up slide-up-d1 text-center mb-2">
        <?php if (!empty($isMaintenance)): ?>
          <div class="relative inline-block pulse-ring"><div class="text-6xl float-emoji"><?= $errorEmoji ?></div></div>
        <?php else: ?>
          <div class="text-6xl float-emoji"><?= $errorEmoji ?></div>
        <?php endif; ?>
      </div>

      <!-- Error code -->
      <div class="text-center slide-up slide-up-d1"><div class="error-code"><?= $errorCode ?></div></div>

      <!-- Divider -->
      <div class="divider my-5 slide-up slide-up-d2"></div>

      <!-- Title & message -->
      <div class="slide-up slide-up-d2 text-center mb-6">
        <h1 class="text-white font-black text-2xl sm:text-3xl mb-1 leading-tight"><?= htmlspecialchars($errorTitle) ?></h1>
        <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color:rgba(96,165,250,.6);letter-spacing:.18em"><?= htmlspecialchars($errorSubtitle) ?></p>
        <p class="text-base leading-relaxed max-w-md mx-auto" style="color:rgba(255,255,255,.65)"><?= htmlspecialchars($errorMsg) ?></p>
        <?php if (!empty($errorHint)): ?>
        <p class="text-sm mt-3 flex items-center justify-center gap-1.5" style="color:rgba(147,197,253,.7)">
          <i data-lucide="lightbulb" style="width:14px;height:14px;flex-shrink:0;color:rgba(251,191,36,.7)"></i>
          <em><?= htmlspecialchars($errorHint) ?></em>
        </p>
        <?php endif; ?>
      </div>

      <!-- Maintenance block -->
      <?php if (!empty($isMaintenance)): ?>
      <div class="slide-up slide-up-d2 glass-card p-4 mb-6 text-center">
        <div class="flex items-center justify-center gap-2 text-xs font-semibold mb-3" style="color:rgba(255,255,255,.6)">
          <div class="w-2 h-2 bg-yellow-400 rounded-full dot-1"></div>
          <div class="w-2 h-2 bg-yellow-400 rounded-full dot-2"></div>
          <div class="w-2 h-2 bg-yellow-400 rounded-full dot-3"></div>
          <span class="ml-1 tracking-wide">Mise à jour en cours — veuillez patienter</span>
        </div>
        <div class="w-full rounded-full h-1.5 overflow-hidden mb-3" style="background:rgba(255,255,255,.08)">
          <div class="h-full rounded-full progress-sweep" style="background:linear-gradient(90deg,transparent,#fbbf24,transparent)"></div>
        </div>
        <p class="text-xs" style="color:rgba(255,255,255,.35)">Nos équipes travaillent activement à la restauration du service.</p>
      </div>
      <?php endif; ?>

      <!-- Search box (404 only) -->
      <?php if ($errorCode == 404): ?>
      <div class="slide-up slide-up-d2 mb-6">
        <p class="text-xs font-semibold text-center uppercase tracking-widest mb-2" style="color:rgba(255,255,255,.35)">Rechercher une page</p>
        <form action="https://mazar.zya.me/search.php" method="GET" class="search-box">
          <i data-lucide="search" style="width:16px;height:16px;flex-shrink:0;color:rgba(255,255,255,.35)"></i>
          <input type="text" name="q" placeholder="Ex : tableau de bord, cours, IA…" autocomplete="off">
          <button type="submit"><i data-lucide="arrow-right" style="width:16px;height:16px;color:#fff"></i></button>
        </form>
      </div>
      <?php endif; ?>

      <!-- CTA Buttons -->
      <div class="slide-up slide-up-d3 flex flex-col sm:flex-row items-center justify-center gap-3 mb-8">
        <?php if (!empty($showLogin)): ?>
          <a href="https://mazar.zya.me/login.php" class="btn-primary">
            <i data-lucide="log-in" style="width:16px;height:16px"></i> Se connecter
          </a>
          <a href="https://mazar.zya.me/register.php" class="btn-secondary">
            <i data-lucide="user-plus" style="width:16px;height:16px"></i> Créer un compte
          </a>
          <a href="https://mazar.zya.me/" class="btn-secondary">
            <i data-lucide="home" style="width:16px;height:16px"></i> Accueil
          </a>
        <?php elseif (!empty($isMaintenance)): ?>
          <button onclick="window.location.reload()" class="btn-primary">
            <i data-lucide="refresh-cw" style="width:16px;height:16px"></i> Actualiser la page
          </button>
        <?php else: ?>
          <a href="https://mazar.zya.me/" class="btn-primary">
            <i data-lucide="home" style="width:16px;height:16px"></i> Retour à l'accueil
          </a>
          <a href="javascript:history.back()" class="btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px"></i> Page précédente
          </a>
        <?php endif; ?>
      </div>

      <?php if (empty($isMaintenance)): ?>
      <div class="divider mb-6 slide-up slide-up-d3"></div>

      <!-- Quick links -->
      <div class="slide-up slide-up-d4">
        <p class="text-xs text-center font-semibold uppercase tracking-widest mb-3" style="color:rgba(255,255,255,.3)">Liens rapides</p>
        <div class="flex flex-wrap items-center justify-center gap-2">
          <a href="https://mazar.zya.me/student/dashboard.php" class="quick-link">
            <i data-lucide="layout-dashboard" style="width:12px;height:12px"></i> Tableau de bord
          </a>
          <a href="https://mazar.zya.me/student/mazar-ai.php" class="quick-link">
            <i data-lucide="sparkles" style="width:12px;height:12px"></i> MAZAR AI
          </a>
          <a href="https://mazar.zya.me/student/courses.php" class="quick-link">
            <i data-lucide="book-open" style="width:12px;height:12px"></i> Mes cours
          </a>
          <a href="https://mazar.zya.me/login.php" class="quick-link">
            <i data-lucide="log-in" style="width:12px;height:12px"></i> Connexion
          </a>
          <a href="https://mazar.zya.me/register.php" class="quick-link">
            <i data-lucide="user-plus" style="width:12px;height:12px"></i> S'inscrire
          </a>
          <a href="mailto:support@mazar.ma" class="quick-link">
            <i data-lucide="mail" style="width:12px;height:12px"></i> Support
          </a>
        </div>
      </div>

      <!-- What to do next cards -->
      <div class="slide-up slide-up-d4 mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="glass-card p-4 text-center">
          <i data-lucide="refresh-cw" style="width:20px;height:20px;color:rgba(147,197,253,.7);display:block;margin:0 auto 8px"></i>
          <p class="text-white font-bold text-xs mb-1">Actualiser la page</p>
          <p class="text-xs" style="color:rgba(255,255,255,.38)">Rechargez — le problème peut être temporaire.</p>
        </div>
        <div class="glass-card p-4 text-center">
          <i data-lucide="wifi" style="width:20px;height:20px;color:rgba(147,197,253,.7);display:block;margin:0 auto 8px"></i>
          <p class="text-white font-bold text-xs mb-1">Vérifier la connexion</p>
          <p class="text-xs" style="color:rgba(255,255,255,.38)">Assurez-vous d'être connecté à Internet.</p>
        </div>
        <div class="glass-card p-4 text-center">
          <i data-lucide="headphones" style="width:20px;height:20px;color:rgba(147,197,253,.7);display:block;margin:0 auto 8px"></i>
          <p class="text-white font-bold text-xs mb-1">Contacter le support</p>
          <p class="text-xs" style="color:rgba(255,255,255,.38)">Notre équipe est disponible 7j/7.</p>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<!-- FOOTER -->
<footer class="relative z-10 py-5 px-6">
  <div class="max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-2">
        <img src="../assets/images/mazar.avif"
             alt="MAZAR"
             style="height:24px;width:auto;object-fit:contain;opacity:.6"
             onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='inline'">
        <span style="display:none;color:rgba(255,255,255,.6);font-size:.875rem;font-weight:700">MAZAR</span>
        <span class="text-xs" style="color:rgba(255,255,255,.2)">— La plateforme éducative numéro 1 au Maroc</span>
      </div>
      <div class="flex items-center gap-3">
        <a href="mailto:support@mazar.ma" class="text-xs font-medium transition-colors flex items-center gap-1" style="color:rgba(255,255,255,.3);text-decoration:none">
          <i data-lucide="mail" style="width:14px;height:14px"></i> support@mazar.ma
        </a>
        <span style="color:rgba(255,255,255,.1)">|</span>
        <a href="https://mazar.zya.me/privacy.php" class="text-xs transition-colors" style="color:rgba(255,255,255,.3);text-decoration:none">Confidentialité</a>
        <a href="https://mazar.zya.me/terms.php" class="text-xs transition-colors" style="color:rgba(255,255,255,.3);text-decoration:none">CGU</a>
      </div>
    </div>
    <div class="divider mb-3"></div>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
      <span class="text-xs" style="color:rgba(255,255,255,.2)">© <?= date('Y') ?> MAZAR. Tous droits réservés.</span>
      <div class="meta-row">
        <i data-lucide="alert-circle" style="width:12px;height:12px"></i>
        Code : <span><?= $errorCode ?></span>
        <span style="opacity:.3;margin:0 4px">·</span>
        <i data-lucide="clock" style="width:12px;height:12px"></i>
        <span><?= date('H:i:s') ?></span>
        <span style="opacity:.3;margin:0 4px">·</span>
        <i data-lucide="server" style="width:12px;height:12px"></i>
        <span>mazar.zya.me</span>
      </div>
    </div>
  </div>
</footer>

<script>lucide.createIcons();</script>
</body>
</html>