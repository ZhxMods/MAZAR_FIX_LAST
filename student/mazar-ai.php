<?php
// ============================================================
//  MAZAR — student/mazar-ai.php
//  MAZAR AI chat page — student-only, auth-guarded
//  NOW WITH DATABASE CONFIGURATION INTEGRATION
// ============================================================
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';  // guards students only

$lang     = getCurrentLang();
$dir      = getDirection();
$userName = $_SESSION[SESS_USERNAME] ?? 'Étudiant';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

// Fetch AI configuration from database
$db = getDB();
$aiConfig = [
    'enabled' => false,
    'provider' => 'groq',
    'model' => 'llama-3.3-70b-versatile',
    'api_key' => '',
    'custom_url' => '',
    'system_prompt' => 'You are MAZAR AI, an educational assistant for Moroccan students.',
    'temperature' => 0.7,
    'max_tokens' => 1000
];

try {
    // Ensure table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_config (
            id INT UNSIGNED PRIMARY KEY DEFAULT 1,
            provider VARCHAR(50) NOT NULL DEFAULT 'groq',
            model VARCHAR(100) NOT NULL DEFAULT 'llama-3.3-70b-versatile',
            api_key TEXT,
            custom_url VARCHAR(500) DEFAULT NULL,
            system_prompt TEXT,
            enabled TINYINT(1) DEFAULT 1,
            temperature DECIMAL(3,2) DEFAULT 0.70,
            max_tokens INT UNSIGNED DEFAULT 1000,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT UNSIGNED DEFAULT NULL
        )
    ");
    
    // Add custom_url if not exists
    try {
        $db->exec("ALTER TABLE ai_config ADD COLUMN custom_url VARCHAR(500) DEFAULT NULL");
    } catch (Exception $e) {
        // Already exists
    }
    
    $stmt = $db->query("SELECT * FROM ai_config WHERE id = 1");
    $dbConfig = $stmt->fetch();
    
    if ($dbConfig) {
        $aiConfig = array_merge($aiConfig, $dbConfig);
    } else {
        // Insert default config
        $db->exec("INSERT INTO ai_config (id, provider, model, enabled) VALUES (1, 'groq', 'llama-3.3-70b-versatile', 1)");
        $aiConfig['enabled'] = true;
    }
} catch (Exception $e) {
    error_log('MAZAR AI Config Error: ' . $e->getMessage());
}

// Check if AI is enabled
$aiDisabled = empty($aiConfig['enabled']);

// Determine API endpoint based on provider
$apiEndpoints = [
    'openai' => 'https://api.openai.com/v1/chat/completions',
    'groq' => 'https://api.groq.com/openai/v1/chat/completions',
    'anthropic' => 'https://api.anthropic.com/v1/messages',
    'custom' => $aiConfig['custom_url'] ?? ''
];

$apiEndpoint = $apiEndpoints[$aiConfig['provider']] ?? $apiEndpoints['groq'];

// If custom URL is set for custom provider, use it
if ($aiConfig['provider'] === 'custom' && !empty($aiConfig['custom_url'])) {
    $apiEndpoint = $aiConfig['custom_url'];
}

?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MAZAR AI — Assistant Éducatif</title>
<meta name="description" content="MAZAR AI — Votre assistant éducatif intelligent dédié à Mazar Education.">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

<!-- MAZAR AI styles -->
<link rel="stylesheet" href="../assets/css/mazar-ai.css">
</head>
<body>

<div id="bg-deco" aria-hidden="true"></div>

<div id="chat-app">

  <!-- ══ HEADER ══════════════════════════════════════════ -->
  <header id="chat-header">

    <!-- Back button -->
    <a href="dashboard.php" class="back-link">
      <i data-lucide="arrow-left" style="width:14px;height:14px;flex-shrink:0;"></i>
      <span>Tableau de bord</span>
    </a>

    <!-- AI Avatar -->
    <div class="ai-avatar-wrap">
      <div class="ai-avatar gradient-hero">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
          <path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
        </svg>
      </div>
      <span class="ai-online-dot <?= $aiDisabled ? 'disabled' : '' ?>"></span>
    </div>

    <!-- AI info -->
    <div class="ai-header-info">
      <div class="ai-header-name">MAZAR AI</div>
      <div class="ai-header-sub">
        <span class="ai-header-sub-dot <?= $aiDisabled ? 'disabled' : '' ?>"></span>
        <?= $aiDisabled ? 'Service temporairement indisponible' : 'Assistant éducatif · En ligne' ?>
      </div>
    </div>

    <!-- Badge -->
    <div class="ai-edu-badge">
      <i data-lucide="graduation-cap" style="width:13px;height:13px;flex-shrink:0;"></i>
      Éducation
    </div>

  </header>

  <!-- ══ MESSAGES ═══════════════════════════════════════ -->
  <div id="chat-messages" role="log" aria-live="polite" aria-label="Conversation avec MAZAR AI">

    <!-- Welcome block -->
    <div class="welcome-block" id="welcome-block">
      <div class="welcome-icon-wrap gradient-hero">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
          <path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
        </svg>
      </div>
      <div class="welcome-title">
        Bonjour<?= $userName !== 'Étudiant' ? ', ' . htmlspecialchars(explode(' ', $userName)[0]) : '' ?> ! Je suis
        <span class="gradient-text">MAZAR AI</span>
      </div>
      <p class="welcome-sub">
        <?php if ($aiDisabled): ?>
          <span style="color: #ef4444;">⚠️ Le service IA est temporairement désactivé pour maintenance. Veuillez réessayer plus tard.</span>
        <?php else: ?>
          Votre assistant éducatif intelligent, dédié à l'apprentissage sur la plateforme <strong>Mazar Education</strong>. Posez-moi vos questions sur vos cours, matières et révisions.
        <?php endif; ?>
      </p>

      <?php if (!$aiDisabled): ?>
      <!-- Suggestion pills -->
      <div class="suggestions">
        <button class="suggest-pill" onclick="sendSuggestion(this)">
          <i data-lucide="calculator" style="width:13px;height:13px;flex-shrink:0;"></i>
          Équations du 2ème degré
        </button>
        <button class="suggest-pill" onclick="sendSuggestion(this)">
          <i data-lucide="globe" style="width:13px;height:13px;flex-shrink:0;"></i>
          La Révolution française
        </button>
        <button class="suggest-pill" onclick="sendSuggestion(this)">
          <i data-lucide="zap" style="width:13px;height:13px;flex-shrink:0;"></i>
          C'est quoi la photosynthèse ?
        </button>
        <button class="suggest-pill" onclick="sendSuggestion(this)">
          <i data-lucide="brain" style="width:13px;height:13px;flex-shrink:0;"></i>
          Comment mieux mémoriser ?
        </button>
        <button class="suggest-pill" onclick="sendSuggestion(this)">
          <i data-lucide="star" style="width:13px;height:13px;flex-shrink:0;"></i>
          Quel est ton nom ?
        </button>
        <button class="suggest-pill" onclick="sendSuggestion(this)">
          <i data-lucide="book-open" style="width:13px;height:13px;flex-shrink:0;"></i>
          Théorème de Pythagore
        </button>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /#chat-messages -->

  <!-- ══ INPUT ══════════════════════════════════════════ -->
  <div id="input-area">
    <div class="input-card <?= $aiDisabled ? 'disabled' : '' ?>">
      <textarea
        id="user-input"
        rows="1"
        placeholder="<?= $aiDisabled ? 'Service temporairement indisponible...' : 'Posez votre question éducative…' ?>"
        aria-label="Votre message"
        maxlength="1200"
        <?= $aiDisabled ? 'disabled' : '' ?>
      ></textarea>
      <button id="send-btn" disabled aria-label="Envoyer le message" class="gradient-hero" <?= $aiDisabled ? 'disabled' : '' ?>>
        <i data-lucide="send" style="width:17px;height:17px;color:#fff;"></i>
      </button>
    </div>
    <div class="input-footer">
      <span class="input-hint">
        <kbd>Enter</kbd> envoyer &nbsp;·&nbsp; <kbd>Shift+Enter</kbd> nouvelle ligne
      </span>
      <span id="char-count">0 / 1200</span>
    </div>
  </div>

</div><!-- /#chat-app -->

<!-- Inject API config from database -->
<script>
  window.MAZAR_AI_CONFIG = {
    enabled: <?= json_encode(!$aiDisabled) ?>,
    provider: <?= json_encode($aiConfig['provider']) ?>,
    model: <?= json_encode($aiConfig['model']) ?>,
    apiKey: <?= json_encode($aiConfig['api_key']) ?>,
    customUrl: <?= json_encode($aiConfig['custom_url'] ?? '') ?>,
    systemPrompt: <?= json_encode($aiConfig['system_prompt']) ?>,
    temperature: <?= json_encode((float)$aiConfig['temperature']) ?>,
    maxTokens: <?= json_encode((int)$aiConfig['max_tokens']) ?>,
    apiEndpoint: <?= json_encode($apiEndpoint) ?>,
    userName: <?= json_encode($userName) ?>,
    initial: <?= json_encode($userInitial) ?>
  };
</script>

<!-- MAZAR AI engine -->
<script src="../assets/js/mazar-ai.js"></script>

</body>
</html>