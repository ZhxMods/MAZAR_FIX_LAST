<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

// Strict Super Admin only access
requireSuperAdmin();

$lang = getCurrentLang();
$dir  = getDirection();
$pageTitle = 'Mazal AI Configuration';

$db = getDB();
$errors = [];
$success = '';

// Ensure AI config table exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_config (
            id INT PRIMARY KEY DEFAULT 1,
            provider VARCHAR(50) NOT NULL DEFAULT 'openai',
            model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o',
            api_key TEXT,
            system_prompt TEXT,
            enabled TINYINT(1) DEFAULT 1,
            temperature DECIMAL(3,2) DEFAULT 0.70,
            max_tokens INT DEFAULT 1000,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            CONSTRAINT chk_id CHECK (id = 1)
        )
    ");
    
    // Insert default config if not exists
    $db->exec("
        INSERT IGNORE INTO ai_config (id, provider, model, system_prompt) 
        VALUES (1, 'openai', 'gpt-4o', 'You are MAZAR AI, an educational assistant for Moroccan students. Provide helpful, accurate, and age-appropriate educational responses.')
    ");
} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_config') {
        $provider = cleanInput($_POST['provider'] ?? 'openai');
        $model = cleanInput($_POST['model'] ?? 'gpt-4o');
        $apiKey = $_POST['api_key'] ?? '';
        $systemPrompt = trim($_POST['system_prompt'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $temperature = (float)($_POST['temperature'] ?? 0.7);
        $maxTokens = (int)($_POST['max_tokens'] ?? 1000);
        
        // Validate inputs
        if (!in_array($provider, ['openai', 'groq', 'anthropic'], true)) {
            $errors[] = t('invalid_provider');
        }
        if ($temperature < 0 || $temperature > 2) {
            $errors[] = t('invalid_temperature');
        }
        if ($maxTokens < 100 || $maxTokens > 4000) {
            $errors[] = t('invalid_max_tokens');
        }
        
        if (empty($errors)) {
            try {
                // Only update API key if provided (don't overwrite with empty)
                if (!empty($apiKey)) {
                    $stmt = $db->prepare("
                        UPDATE ai_config 
                        SET provider = :provider, model = :model, api_key = :api_key, 
                            system_prompt = :prompt, enabled = :enabled, 
                            temperature = :temp, max_tokens = :max_tokens, updated_by = :uid
                        WHERE id = 1
                    ");
                    $stmt->execute([
                        ':provider' => $provider,
                        ':model' => $model,
                        ':api_key' => $apiKey,
                        ':prompt' => $systemPrompt,
                        ':enabled' => $enabled,
                        ':temp' => $temperature,
                        ':max_tokens' => $maxTokens,
                        ':uid' => (int)$_SESSION[SESS_USER_ID]
                    ]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE ai_config 
                        SET provider = :provider, model = :model, 
                            system_prompt = :prompt, enabled = :enabled, 
                            temperature = :temp, max_tokens = :max_tokens, updated_by = :uid
                        WHERE id = 1
                    ");
                    $stmt->execute([
                        ':provider' => $provider,
                        ':model' => $model,
                        ':prompt' => $systemPrompt,
                        ':enabled' => $enabled,
                        ':temp' => $temperature,
                        ':max_tokens' => $maxTokens,
                        ':uid' => (int)$_SESSION[SESS_USER_ID]
                    ]);
                }
                
                auditLog('ai_config_updated', "Provider: {$provider}, Model: {$model}, Enabled: {$enabled}", (int)$_SESSION[SESS_USER_ID]);
                $success = t('config_saved');
            } catch (Exception $e) {
                $errors[] = t('save_error') . ': ' . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_ai') {
        // Quick toggle (Kill Switch)
        $newState = isset($_POST['enable']) ? 1 : 0;
        try {
            $stmt = $db->prepare("UPDATE ai_config SET enabled = :enabled, updated_by = :uid WHERE id = 1");
            $stmt->execute([':enabled' => $newState, ':uid' => (int)$_SESSION[SESS_USER_ID]]);
            
            auditLog('ai_kill_switch', $newState ? 'AI Enabled' : 'AI Disabled', (int)$_SESSION[SESS_USER_ID]);
            $success = $newState ? t('ai_enabled_success') : t('ai_disabled_success');
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($action === 'test_ai') {
        // Test AI connection
        $testPrompt = 'Hello, this is a test. Please respond with "MAZAR AI is working correctly."';
        
        $configStmt = $db->query("SELECT * FROM ai_config WHERE id = 1");
        $config = $configStmt->fetch();
        
        if (!$config || empty($config['api_key'])) {
            $errors[] = t('api_key_missing');
        } else {
            // Simple test - in production, you'd make an actual API call
            $success = t('ai_test_initiated');
        }
    }
}

// Get current config
$configStmt = $db->query("SELECT * FROM ai_config WHERE id = 1");
$config = $configStmt->fetch() ?: [
    'provider' => 'openai',
    'model' => 'gpt-4o',
    'api_key' => '',
    'system_prompt' => '',
    'enabled' => 1,
    'temperature' => 0.7,
    'max_tokens' => 1000
];

// Mask API key for display
$maskedKey = '';
if (!empty($config['api_key'])) {
    $keyLen = strlen($config['api_key']);
    $maskedKey = substr($config['api_key'], 0, 8) . str_repeat('*', max(0, $keyLen - 16)) . substr($config['api_key'], -8);
}

require dirname(__DIR__) . '/admin/_layout.php';
?>

<!-- AI Configuration Page -->
<div class="max-w-4xl mx-auto">
    
    <!-- Kill Switch Card -->
    <div class="admin-card p-6 mb-6 <?= $config['enabled'] ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500' ?>">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center <?= $config['enabled'] ? 'bg-green-100' : 'bg-red-100' ?>">
                    <i data-lucide="power" class="w-7 h-7 <?= $config['enabled'] ? 'text-green-600' : 'text-red-600' ?>"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-gray-900"><?= t('ai_kill_switch') ?></h2>
                    <p class="text-gray-500 text-sm">
                        <?= $config['enabled'] ? t('ai_currently_enabled') : t('ai_currently_disabled') ?>
                    </p>
                </div>
            </div>
            <form method="POST" class="flex items-center gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_ai">
                <input type="hidden" name="enable" value="<?= $config['enabled'] ? '0' : '1' ?>">
                <button type="submit" 
                        class="px-6 py-3 rounded-xl font-bold text-white transition transform hover:scale-105 <?= $config['enabled'] ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' ?>">
                    <span class="flex items-center gap-2">
                        <i data-lucide="<?= $config['enabled'] ? 'pause' : 'play' ?>" class="w-5 h-5"></i>
                        <?= $config['enabled'] ? t('disable_ai') : t('enable_ai') ?>
                    </span>
                </button>
            </form>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="admin-card p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                <i data-lucide="settings" class="w-5 h-5 text-blue-600"></i>
            </div>
            <div>
                <h2 class="text-lg font-black text-gray-900"><?= t('ai_configuration') ?></h2>
                <p class="text-gray-500 text-sm"><?= t('ai_config_desc') ?></p>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3">
            <i data-lucide="check-circle" class="w-5 h-5 text-green-600 flex-shrink-0"></i>
            <span class="text-green-700"><?= clean($success) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <?php foreach ($errors as $error): ?>
            <p class="text-red-600 flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                <?= clean($error) ?>
            </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_config">

            <!-- Provider Selection -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <?= t('ai_provider') ?>
                    </label>
                    <select name="provider" required
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="openai" <?= $config['provider'] === 'openai' ? 'selected' : '' ?>>OpenAI (GPT)</option>
                        <option value="groq" <?= $config['provider'] === 'groq' ? 'selected' : '' ?>>Groq (Llama)</option>
                        <option value="anthropic" <?= $config['provider'] === 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <?= t('ai_model') ?>
                    </label>
                    <select name="model" required
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <!-- OpenAI Models -->
                        <optgroup label="OpenAI" class="provider-models" data-provider="openai">
                            <option value="gpt-4o" <?= $config['model'] === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                            <option value="gpt-4o-mini" <?= $config['model'] === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o Mini</option>
                            <option value="gpt-4-turbo" <?= $config['model'] === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo" <?= $config['model'] === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                        </optgroup>
                        <!-- Groq Models -->
                        <optgroup label="Groq" class="provider-models" data-provider="groq">
                            <option value="llama-3.1-70b" <?= $config['model'] === 'llama-3.1-70b' ? 'selected' : '' ?>>Llama 3.1 70B</option>
                            <option value="llama-3.1-8b" <?= $config['model'] === 'llama-3.1-8b' ? 'selected' : '' ?>>Llama 3.1 8B</option>
                            <option value="mixtral-8x7b" <?= $config['model'] === 'mixtral-8x7b' ? 'selected' : '' ?>>Mixtral 8x7B</option>
                        </optgroup>
                        <!-- Anthropic Models -->
                        <optgroup label="Anthropic" class="provider-models" data-provider="anthropic">
                            <option value="claude-3-5-sonnet" <?= $config['model'] === 'claude-3-5-sonnet' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                            <option value="claude-3-opus" <?= $config['model'] === 'claude-3-opus' ? 'selected' : '' ?>>Claude 3 Opus</option>
                            <option value="claude-3-sonnet" <?= $config['model'] === 'claude-3-sonnet' ? 'selected' : '' ?>>Claude 3 Sonnet</option>
                            <option value="claude-3-haiku" <?= $config['model'] === 'claude-3-haiku' ? 'selected' : '' ?>>Claude 3 Haiku</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- API Key -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <?= t('api_key') ?>
                </label>
                <div class="relative">
                    <input type="password" name="api_key" id="api_key"
                           placeholder="<?= !empty($maskedKey) ? $maskedKey : t('enter_api_key') ?>"
                           class="w-full px-4 py-3 pr-12 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm">
                    <button type="button" onclick="toggleApiKey()" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i data-lucide="eye" class="w-5 h-5" id="api-key-eye"></i>
                    </button>
                </div>
                <p class="text-gray-500 text-xs mt-1">
                    <?= !empty($maskedKey) ? t('api_key_leave_blank') : t('api_key_required') ?>
                </p>
            </div>

            <!-- System Prompt -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <?= t('system_prompt') ?>
                </label>
                <textarea name="system_prompt" rows="4" required
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"><?= clean($config['system_prompt'] ?? '') ?></textarea>
                <p class="text-gray-500 text-xs mt-1"><?= t('system_prompt_desc') ?></p>
            </div>

            <!-- Advanced Settings -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <?= t('temperature') ?> (0.0 - 2.0)
                    </label>
                    <input type="number" name="temperature" step="0.1" min="0" max="2" required
                           value="<?= $config['temperature'] ?>"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-gray-500 text-xs mt-1"><?= t('temperature_desc') ?></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <?= t('max_tokens') ?> (100 - 4000)
                    </label>
                    <input type="number" name="max_tokens" step="100" min="100" max="4000" required
                           value="<?= $config['max_tokens'] ?>"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-gray-500 text-xs mt-1"><?= t('max_tokens_desc') ?></p>
                </div>
            </div>

            <!-- Enable/Disable Checkbox -->
            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                <input type="checkbox" name="enabled" id="enabled" value="1" 
                       <?= $config['enabled'] ? 'checked' : '' ?>
                       class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="enabled" class="text-sm font-semibold text-gray-700 cursor-pointer">
                    <?= t('enable_ai_services') ?>
                </label>
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-wrap gap-3 pt-4 border-t">
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition flex items-center gap-2">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    <?= t('save_configuration') ?>
                </button>
                
                <button type="submit" formaction="?test=1" formmethod="post"
                        class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition flex items-center gap-2">
                    <i data-lucide="play-circle" class="w-5 h-5"></i>
                    <?= t('test_connection') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Usage Stats -->
    <div class="admin-card p-6 mt-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                <i data-lucide="bar-chart-2" class="w-5 h-5 text-purple-600"></i>
            </div>
            <div>
                <h2 class="text-lg font-black text-gray-900"><?= t('ai_usage_stats') ?></h2>
                <p class="text-gray-500 text-sm"><?= t('ai_stats_desc') ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-black text-blue-600">-</div>
                <div class="text-gray-500 text-xs"><?= t('total_requests') ?></div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-black text-green-600">-</div>
                <div class="text-gray-500 text-xs"><?= t('avg_response_time') ?></div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-black text-purple-600">-</div>
                <div class="text-gray-500 text-xs"><?= t('tokens_used') ?></div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-black text-orange-600"><?= $config['enabled'] ? t('active') : t('inactive') ?></div>
                <div class="text-gray-500 text-xs"><?= t('service_status') ?></div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    
    // Toggle API key visibility
    function toggleApiKey() {
        const input = document.getElementById('api_key');
        const eye = document.getElementById('api-key-eye');
        
        if (input.type === 'password') {
            input.type = 'text';
            eye.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            eye.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }
    
    // Filter models by provider
    document.querySelector('select[name="provider"]').addEventListener('change', function() {
        const provider = this.value;
        const modelSelect = document.querySelector('select[name="model"]');
        const optgroups = modelSelect.querySelectorAll('optgroup');
        
        optgroups.forEach(og => {
            og.style.display = og.dataset.provider === provider ? '' : 'none';
        });
        
        // Select first available model
        const visibleOption = modelSelect.querySelector(`optgroup[data-provider="${provider}"] option`);
        if (visibleOption) {
            visibleOption.selected = true;
        }
    });
    
    // Trigger change to set initial state
    document.querySelector('select[name="provider"]').dispatchEvent(new Event('change'));
</script>

<?php require dirname(__DIR__) . '/admin/_layout_end.php'; ?>
