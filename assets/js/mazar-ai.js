/* ============================================================
   MAZAR AI — assets/js/mazar-ai.js
   Chat Engine · Multi-Provider REST API · Dynamic Config
   Pure Vanilla JS — Zero dependencies
============================================================ */

'use strict';

/* ── Config (injected by PHP via window.MAZAR_AI_CONFIG) ── */
const CONFIG = window.MAZAR_AI_CONFIG || {};

// Debug: Log config (remove in production)
console.log('[MAZAR AI] Config loaded:', {
    enabled: CONFIG.enabled,
    provider: CONFIG.provider,
    model: CONFIG.model,
    hasApiKey: !!CONFIG.apiKey,
    apiEndpoint: CONFIG.apiEndpoint
});

// Validate config
if (!CONFIG.enabled) {
    console.warn('[MAZAR AI] Service is currently disabled.');
}

// Determine API URL
let API_URL = CONFIG.apiEndpoint;
if (!API_URL && CONFIG.provider === 'custom' && CONFIG.customUrl) {
    API_URL = CONFIG.customUrl;
}

// Default endpoints as fallback
const DEFAULT_ENDPOINTS = {
    'openai': 'https://api.openai.com/v1/chat/completions',
    'groq': 'https://api.groq.com/openai/v1/chat/completions',
    'anthropic': 'https://api.anthropic.com/v1/messages',
    'custom': CONFIG.customUrl || ''
};

if (!API_URL) {
    API_URL = DEFAULT_ENDPOINTS[CONFIG.provider] || DEFAULT_ENDPOINTS['groq'];
}

const API_KEY = CONFIG.apiKey || '';
const AI_MODEL = CONFIG.model || 'llama-3.3-70b-versatile';
const AI_PROVIDER = CONFIG.provider || 'groq';
const TEMPERATURE = parseFloat(CONFIG.temperature) || 0.7;
const MAX_TOKENS = parseInt(CONFIG.maxTokens) || 1000;

/* ── System Prompt from Database ───────────────────────── */
const SYSTEM_PROMPT = CONFIG.systemPrompt || `You are MAZAR AI, an educational assistant for Moroccan students.`;

/* ── DOM refs ──────────────────────────────────────────── */
const $msgs    = document.getElementById('chat-messages');
const $input   = document.getElementById('user-input');
const $sendBtn = document.getElementById('send-btn');
const $charCnt = document.getElementById('char-count');
const $welcome = document.getElementById('welcome-block');

/* ── State ─────────────────────────────────────────────── */
let history = [];
let busy    = false;

/* ── Check if AI is enabled ────────────────────────────── */
if (!CONFIG.enabled) {
    console.warn('[MAZAR AI] AI is disabled. Chat functionality is disabled.');
    if ($input) $input.disabled = true;
    if ($sendBtn) $sendBtn.disabled = true;
}

/* ── Textarea auto-resize ───────────────────────────────── */
if ($input) {
    $input.addEventListener('input', () => {
        $input.style.height = 'auto';
        $input.style.height = Math.min($input.scrollHeight, 130) + 'px';
        const len = $input.value.length;
        $charCnt.textContent = len + ' / 1200';
        $charCnt.className = len > 1100 ? 'over' : len > 950 ? 'warn' : '';
        $sendBtn.disabled = (!$input.value.trim() || busy || !CONFIG.enabled);
    });
}

/* ── Keyboard shortcuts ─────────────────────────────────── */
if ($input) {
    $input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!$sendBtn.disabled) sendMessage();
        }
    });
}

if ($sendBtn) {
    $sendBtn.addEventListener('click', sendMessage);
}

/* ── Suggestion pills ───────────────────────────────────── */
function sendSuggestion(el) {
    if (!CONFIG.enabled) return;
    
    // Get text content excluding the icon
    let text = '';
    el.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) text += node.textContent;
    });
    text = text.trim();
    if (!text) text = el.textContent.trim();
    
    if ($input) {
        $input.value = text;
        $input.dispatchEvent(new Event('input'));
        sendMessage();
    }
}

/* ── Build API request based on provider ────────────────── */
function buildApiRequestBody(messages) {
    // For Anthropic, format is different
    if (AI_PROVIDER === 'anthropic') {
        const systemMsg = messages.find(m => m.role === 'system');
        const otherMsgs = messages.filter(m => m.role !== 'system');
        
        return {
            model: AI_MODEL,
            max_tokens: MAX_TOKENS,
            messages: otherMsgs.map(m => ({
                role: m.role === 'assistant' ? 'assistant' : 'user',
                content: m.content
            })),
            system: systemMsg ? systemMsg.content : SYSTEM_PROMPT
        };
    }
    
    // OpenAI-compatible format (OpenAI, Groq, Custom)
    return {
        model: AI_MODEL,
        temperature: TEMPERATURE,
        max_tokens: MAX_TOKENS,
        top_p: 0.9,
        stream: false,
        messages: messages
    };
}

/* ── Parse API response based on provider ───────────────── */
function parseApiResponse(data) {
    if (!data) return '';
    
    try {
        if (AI_PROVIDER === 'anthropic') {
            // Anthropic format
            if (data.content && Array.isArray(data.content) && data.content.length > 0) {
                return data.content[0].text || '';
            }
        } else {
            // OpenAI-compatible format
            if (data.choices && Array.isArray(data.choices) && data.choices.length > 0) {
                return data.choices[0].message?.content || '';
            }
        }
    } catch (e) {
        console.error('[MAZAR AI] Error parsing response:', e);
    }
    
    return '';
}

/* ── Get API headers based on provider ──────────────────── */
function getApiHeaders() {
    const headers = {
        'Content-Type': 'application/json'
    };
    
    // Anthropic uses x-api-key header
    if (AI_PROVIDER === 'anthropic') {
        headers['x-api-key'] = API_KEY;
        headers['anthropic-version'] = '2023-06-01';
    } else {
        // OpenAI-compatible uses Bearer token
        headers['Authorization'] = 'Bearer ' + API_KEY;
    }
    
    return headers;
}

/* ── Main send function ─────────────────────────────────── */
async function sendMessage() {
    if (!CONFIG.enabled) {
        addBubble('ai', '⚠️ Le service IA est temporairement indisponible. Veuillez réessayer plus tard.');
        return;
    }
    
    if (!API_KEY) {
        addBubble('ai', '⚠️ Configuration error: API key is missing. Please contact administrator.');
        console.error('[MAZAR AI] No API key configured');
        return;
    }
    
    if (!API_URL) {
        addBubble('ai', '⚠️ Configuration error: API endpoint is not configured. Please contact administrator.');
        console.error('[MAZAR AI] No API URL configured');
        return;
    }
    
    const text = $input.value.trim();
    if (!text || busy) return;

    // Remove welcome screen
    if ($welcome && $welcome.parentNode) {
        $welcome.style.transition = 'opacity .3s, transform .3s';
        $welcome.style.opacity    = '0';
        $welcome.style.transform  = 'translateY(-8px)';
        setTimeout(() => { 
            if ($welcome && $welcome.parentNode) $welcome.remove(); 
        }, 300);
    }

    // Add user message
    addBubble('user', text);
    history.push({ role: 'user', content: text });

    // Clear input
    $input.value = '';
    $input.style.height = 'auto';
    $charCnt.textContent = '0 / 1200';
    $charCnt.className   = '';
    $sendBtn.disabled    = true;
    busy = true;

    const typingId = showTyping();

    try {
        const messages = [
            { role: 'system', content: SYSTEM_PROMPT },
            ...history
        ];
        
        const requestBody = buildApiRequestBody(messages);
        const headers = getApiHeaders();
        
        console.log('[MAZAR AI] Sending request to:', API_URL);
        console.log('[MAZAR AI] Model:', AI_MODEL);
        
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(requestBody)
        });

        // Handle HTTP errors
        if (!res.ok) {
            let errorMessage = 'Erreur HTTP ' + res.status;
            let responseData = null;
            
            try {
                responseData = await res.json();
                errorMessage = responseData?.error?.message || 
                              responseData?.error?.code || 
                              responseData?.message || 
                              errorMessage;
            } catch (e) {
                // Not JSON response
                const text = await res.text();
                if (text) errorMessage = text.substring(0, 200);
            }
            
            // User-friendly error messages
            if (res.status === 401) {
                errorMessage = 'Clé API invalide ou expirée. Veuillez vérifier votre configuration.';
            } else if (res.status === 403) {
                errorMessage = 'Accès refusé. Vérifiez les permissions de votre clé API.';
            } else if (res.status === 429) {
                errorMessage = 'Trop de requêtes. Veuillez patienter quelques secondes.';
            } else if (res.status === 500 || res.status === 502 || res.status === 503) {
                errorMessage = 'Le service IA est temporairement indisponible. Réessayez plus tard.';
            } else if (res.status === 404) {
                errorMessage = 'Modèle non trouvé. Vérifiez le nom du modèle dans la configuration.';
            }
            
            throw new Error(errorMessage);
        }

        const data = await res.json();
        console.log('[MAZAR AI] Response received:', data);
        
        const reply = parseApiResponse(data).trim();
        
        if (!reply) {
            throw new Error('Réponse vide reçue du serveur');
        }

        removeTyping(typingId);
        addBubble('ai', reply);
        history.push({ role: 'assistant', content: reply });

        // Keep context window manageable (last 10 exchanges)
        if (history.length > 20) {
            history.splice(0, 2); // Remove oldest exchange
        }

    } catch (err) {
        removeTyping(typingId);
        
        let errorMsg = '⚠️ Impossible de joindre MAZAR AI.\n\n';
        
        if (err.name === 'TypeError' && err.message.includes('fetch')) {
            errorMsg += 'Erreur de connexion. Vérifiez votre connexion internet.';
        } else if (err.message.includes('Failed to fetch') || err.message.includes('NetworkError')) {
            errorMsg += 'Impossible de se connecter au serveur IA. Vérifiez l\'URL de l\'API.';
        } else {
            errorMsg += err.message;
        }
        
        addBubble('ai', errorMsg);
        console.error('[MAZAR AI] Error:', err);
    }

    busy = false;
    $sendBtn.disabled = !$input.value.trim();
    if ($input) $input.focus();
}

/* ── Add message bubble ─────────────────────────────────── */
function addBubble(role, text) {
    if (!$msgs) return;
    
    const time = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    const html = role === 'ai' ? formatMarkdown(text) : escHtml(text).replace(/\n/g, '<br>');
    const isAI = role === 'ai';

    const row = document.createElement('div');
    row.className = 'msg-row ' + role;

    const avClass = isAI ? 'gradient-hero' : '';
    const avStyle = isAI ? '' : 'background:#dbeafe;color:#1e40af;';
    const avLabel = isAI ? 'M' : 'Toi';
    const avRole = isAI ? 'ai' : 'usr';

    row.innerHTML = `
        <div class="msg-av ${avRole} ${avClass}" style="${avStyle}" aria-hidden="true">${avLabel}</div>
        <div class="msg-content">
            <div class="msg-bubble">${html}</div>
            <span class="msg-meta">${time}</span>
        </div>
    `;

    $msgs.appendChild(row);
    scrollBottom();
    return row;
}

/* ── Markdown formatter ─────────────────────────────────── */
function formatMarkdown(raw) {
    if (!raw) return '';
    
    // 1. Escape HTML first to prevent XSS
    let text = escHtml(raw);

    // 2. Fenced code blocks (``` ... ```)
    text = text.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
        return `<pre><code class="language-${lang}">${code.trim()}</code></pre>`;
    });

    // 3. Bold: **text**
    text = text.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');

    // 4. Italic: *text* (but not inside **)
    text = text.replace(/(?<!\*)\*(?!\*)([\s\S]*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');

    // 5. Inline code `text`
    text = text.replace(/`([^`\n]+)`/g, '<code>$1</code>');

    // 6. Headers: ### text or ## text or # text
    text = text.replace(/^### (.+)$/gm, '<h3 class="section-title">$1</h3>');
    text = text.replace(/^## (.+)$/gm, '<h2 class="section-title">$1</h2>');
    text = text.replace(/^# (.+)$/gm, '<h1 class="section-title">$1</h1>');

    // 7. Bullet lists
    text = text.replace(/((?:^[ \t]*[-*] .+(?:\n|$))+)/gm, match => {
        const items = match
            .split('\n')
            .filter(l => l.trim())
            .map(l => `<li>${l.replace(/^[ \t]*[-*] /, '').trim()}</li>`)
            .join('');
        return `<ul>${items}</ul>`;
    });

    // 8. Numbered lists
    text = text.replace(/((?:^[ \t]*\d+\. .+(?:\n|$))+)/gm, match => {
        const items = match
            .split('\n')
            .filter(l => l.trim())
            .map(l => `<li>${l.replace(/^[ \t]*\d+\. /, '').trim()}</li>`)
            .join('');
        return `<ol>${items}</ol>`;
    });

    // 9. Line breaks
    text = text.replace(/\n\n/g, '<br><br>');
    text = text.replace(/\n/g, '<br>');

    return text;
}

/* ── Typing indicator ───────────────────────────────────── */
function showTyping() {
    if (!$msgs) return '';
    
    const id = 'typing_' + Date.now();
    const row = document.createElement('div');
    row.id = id;
    row.className = 'msg-row ai';
    row.setAttribute('aria-label', 'MAZAR AI est en train de répondre');
    row.innerHTML = `
        <div class="msg-av ai gradient-hero" aria-hidden="true">M</div>
        <div class="msg-content">
            <div class="msg-bubble" style="padding:.65rem .9rem;">
                <div class="typing-wrap">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        </div>
    `;
    $msgs.appendChild(row);
    scrollBottom();
    return id;
}

function removeTyping(id) {
    if (!id) return;
    const el = document.getElementById(id);
    if (el) el.remove();
}

/* ── Helpers ────────────────────────────────────────────── */
function scrollBottom() {
    if (!$msgs) return;
    $msgs.scrollTo({ top: $msgs.scrollHeight, behavior: 'smooth' });
}

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* ── Init ───────────────────────────────────────────────── */
window.addEventListener('load', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    if (CONFIG.enabled && $input) $input.focus();
    
    console.log('[MAZAR AI] Initialized. Enabled:', CONFIG.enabled);
});