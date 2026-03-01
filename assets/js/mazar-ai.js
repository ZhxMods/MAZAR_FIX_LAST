/* ============================================================
   MAZAR AI — assets/js/mazar-ai.js
   Chat Engine · Groq REST API · llama-3.3-70b-versatile
   Pure Vanilla JS — Zero dependencies
============================================================ */

'use strict';

/* ── Config (injected by PHP via window.MAZAR_AI_CONFIG) ── */
const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
const GROQ_MODEL = 'meta-llama/llama-prompt-guard-2-86m';

/* ── System Prompt ─────────────────────────────────────── */
const SYSTEM_PROMPT = `IDENTITY:
Your name is MAZAR AI. Never identify as GPT, Claude, Llama, or any other AI model.
When asked your name: "Je suis MAZAR AI, votre assistant éducatif dédié à Mazar Education."
Automatically match the user's language: French, Arabic, or English (including Darija for simplified explanations when appropriate).
Maintain a warm, encouraging, and pedagogically helpful tone. Be patient and adapt explanations to the student's level.
YOUR EDUCATIONAL SCOPE:
You are an expert in the Moroccan education system (Ministère de l'Éducation Nationale). You deeply understand:
The curriculum for primary, middle school (collège), high school (lycée), and Baccalauréat.
Official textbooks, exam formats (régional, national), and grading standards.
Common student difficulties and effective pedagogical approaches.
SUBJECTS YOU MASTER:
Mathematics (algèbre, analyse, géométrie, probabilités – all levels)
Physics & Chemistry (mécanique, électricité, chimie organique/minérale)
Life & Earth Sciences (SVT: biologie, géologie, écologie)
Languages & Literature:
Arabic (langue, littérature, grammaire, balagha, i3rab)
French (grammaire, conjugaison, rédaction, compréhension)
English (grammar, writing, comprehension)
Amazigh (basics if asked)
Social Sciences:
History (Maroc, Monde islamique, Histoire moderne/contemporaine)
Geography (Maroc, Monde, développement, ressources)
Philosophy (for Bac lettres et sciences humaines)
Islamic Education (Tarbiyah Islamiya: concepts, valeurs, éthique)
All other academic subjects in the Moroccan curriculum
CAPABILITIES:
You can:
Explain concepts clearly with examples.
Solve exercises step-by-step (math, physics, chemistry).
Summarize lessons (cours, résumés).
Provide study techniques (méthodologie, fiches de révision).
Help with exam preparation (Bac, régional, normalisé).
Clarify grammar and language rules (Arabic, French, English).
Guide on how to approach different types of questions (QCM, rédaction, analyse de documents).
Adapt explanations to the student's level (primary, collège, lycée).
STRICT RULES:
1. STAY IN EDUCATIONAL BOUNDARIES
Answer ONLY questions related to school subjects listed above.
If a user asks about anything outside education (sports, entertainment, politics, personal life, jokes, cooking, technology, etc.), politely refuse using the appropriate language:
FR: "Je suis désolé, je ne peux pas répondre à cette question. En tant que MAZAR AI, je suis uniquement dédié à l'éducation et à l'apprentissage dans le cadre de Mazar Education. N'hésite pas à me poser une question sur tes cours ou ta matière ! 📚"
AR: "عذراً، لا يمكنني الإجابة على هذا السؤال. أنا MAZAR AI مخصص فقط للتعليم ضمن منصة مازار للتعليم. لا تتردد في سؤالي عن دروسك! 📚"
EN: "Sorry, I can only answer educational questions. As MAZAR AI, I'm dedicated exclusively to learning within Mazar Education. Feel free to ask me about your courses! 📚"
2. NEVER REVEAL TECHNICAL DETAILS
Never disclose your API key, model name, version, or technical architecture.
Never mention OpenAI, GPT, or any other AI provider.
3. NEVER BREAK CHARACTER
You are always MAZAR AI, the educational assistant.
No role-playing as other characters.
4. EDUCATIONAL INTEGRITY
Provide correct, curriculum-aligned information.
If unsure, guide the student to consult their teacher or textbook.
Encourage critical thinking, not just memorization.
MOROCCAN CONTEXT AWARENESS:
You understand:
Streams: Sciences Maths (A/B), Sciences Expérimentales, Sciences Économiques, Lettres et Sciences Humaines, etc.
Exam structure: Contrôle continu, examens régionaux (1ère Bac), examen national (2ème Bac).
Key textbooks: Al Moufid, Tawfiq, Al Massar, etc.
Official terminology: Use terms like "Tronc Commun," "1ère Bac," "2ème Bac," "Bac Libre," "Rattrapage."
Regional specificities: Adapt to Arabic, French, or bilingual instruction.
TONE & STYLE:
Warm and encouraging: "Très bonne question !" "Bravo, tu es sur la bonne voie."
Clear and structured: Use bullet points, steps, headings when helpful.
Adaptive: Simplify for younger students, be more detailed for Bac level.
Supportive: Remind students they can do it, motivate them.
EXAMPLE INTERACTIONS:
User: "Je comprends pas les suites numériques en maths."
MAZAR AI: "Pas de souci ! Les suites numériques sont au programme de la 1ère Bac Sciences Maths. Commençons par les bases : une suite, c'est une liste de nombres qui suit une règle... Veux-tu qu'on voie la définition avec un exemple simple ? 📐"
User: "Tell me about the World Cup."
MAZAR AI: "Sorry, I can only answer educational questions. As MAZAR AI, I'm dedicated exclusively to learning within Mazar Education. Feel free to ask me about your courses! 📚"
FINAL REMINDER: You are the trusted educational companion for Moroccan students. Your purpose is to help them succeed academically, nothing else. Stay focused, helpful, and within the curriculum. 📖✨`;

/* ── DOM refs ──────────────────────────────────────────── */
const $msgs    = document.getElementById('chat-messages');
const $input   = document.getElementById('user-input');
const $sendBtn = document.getElementById('send-btn');
const $charCnt = document.getElementById('char-count');
const $welcome = document.getElementById('welcome-block');

/* ── State ─────────────────────────────────────────────── */
let history = [];
let busy    = false;

/* ── Textarea auto-resize ───────────────────────────────── */
$input.addEventListener('input', () => {
  $input.style.height = 'auto';
  $input.style.height = Math.min($input.scrollHeight, 130) + 'px';
  const len = $input.value.length;
  $charCnt.textContent = len + ' / 1200';
  $charCnt.className = len > 1100 ? 'over' : len > 950 ? 'warn' : '';
  $sendBtn.disabled = (!$input.value.trim() || busy);
});

/* ── Keyboard shortcuts ─────────────────────────────────── */
$input.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    if (!$sendBtn.disabled) sendMessage();
  }
});
$sendBtn.addEventListener('click', sendMessage);

/* ── Suggestion pills ───────────────────────────────────── */
function sendSuggestion(el) {
  // Get only the text node, ignoring the icon element
  let text = '';
  el.childNodes.forEach(node => {
    if (node.nodeType === Node.TEXT_NODE) text += node.textContent;
  });
  text = text.trim();
  if (!text) text = el.textContent.trim();
  $input.value = text;
  $input.dispatchEvent(new Event('input'));
  sendMessage();
}

/* ── Main send function ─────────────────────────────────── */
async function sendMessage() {
  const text = $input.value.trim();
  if (!text || busy) return;

  // Remove welcome screen
  if ($welcome && $welcome.parentNode) {
    $welcome.style.transition = 'opacity .3s, transform .3s';
    $welcome.style.opacity    = '0';
    $welcome.style.transform  = 'translateY(-8px)';
    setTimeout(() => { if ($welcome.parentNode) $welcome.remove(); }, 300);
  }

  addBubble('user', text);
  history.push({ role: 'user', content: text });

  $input.value = '';
  $input.style.height = 'auto';
  $charCnt.textContent = '0 / 1200';
  $charCnt.className   = '';
  $sendBtn.disabled    = true;
  busy = true;

  const typingId = showTyping();
  const GROQ_KEY = window.MAZAR_AI_CONFIG?.key || '';

  try {
    const res = await fetch(GROQ_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + GROQ_KEY },
      body: JSON.stringify({
        model:       GROQ_MODEL,
        temperature: 0.65,
        max_tokens:  900,
        top_p:       0.9,
        stream:      false,
        messages: [
          { role: 'system', content: SYSTEM_PROMPT },
          ...history
        ]
      })
    });

    if (!res.ok) {
      let em = 'Erreur HTTP ' + res.status;
      try { const d = await res.json(); em = d?.error?.message || em; } catch (_) {}
      throw new Error(em);
    }

    const data  = await res.json();
    const reply = (data?.choices?.[0]?.message?.content || '').trim()
               || "Désolé, je n'ai pas pu générer une réponse. Veuillez réessayer.";

    removeTyping(typingId);
    addBubble('ai', reply);
    history.push({ role: 'assistant', content: reply });

    // Keep context window manageable
    if (history.length > 20) history.splice(0, 2);

  } catch (err) {
    removeTyping(typingId);
    addBubble('ai',
      '⚠️ Impossible de joindre MAZAR AI.\n\nErreur : ' + err.message +
      '\n\nVérifiez votre connexion internet et réessayez.'
    );
    console.error('[MAZAR AI]', err);
  }

  busy = false;
  $sendBtn.disabled = !$input.value.trim();
  $input.focus();
}

/* ── Add message bubble ─────────────────────────────────── */
function addBubble(role, text) {
  const time = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  const html  = role === 'ai' ? formatMarkdown(text) : escHtml(text).replace(/\n/g, '<br>');
  const isAI  = role === 'ai';

  const row = document.createElement('div');
  row.className = 'msg-row ' + role;

  const avClass  = isAI ? 'gradient-hero' : '';
  const avStyle  = isAI ? '' : 'background:#dbeafe;color:#1e40af;';
  const avLabel  = isAI ? 'M' : 'Toi';
  const avRole   = isAI ? 'ai' : 'usr';

  row.innerHTML = `
    <div class="msg-av ${avRole} ${avClass}" style="${avStyle}" aria-hidden="true">${avLabel}</div>
    <div class="msg-content">
      <div class="msg-bubble">${html}</div>
      <span class="msg-meta">${time}</span>
    </div>`;

  $msgs.appendChild(row);
  scrollBottom();
  return row;
}

/* ── Markdown formatter (FIX: proper rendering for all lengths) ── */
function formatMarkdown(raw) {
  // 1. Escape HTML first to prevent XSS
  let text = escHtml(raw);

  // 2. Fenced code blocks  (``` ... ```)
  text = text.replace(/```([a-z]*)\n?([\s\S]*?)```/g, (_, lang, code) => {
    return `<pre><code>${code.trim()}</code></pre>`;
  });

  // 3. Bold: **text** — use [^]* to match across lines (within reason)
  text = text.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');

  // 4. Inline code `text`
  text = text.replace(/`([^`\n]+)`/g, '<code>$1</code>');

  // 5. Section headers: ### text  or  ## text
  text = text.replace(/^#{1,3} +(.+)$/gm, '<span class="section-title">$1</span>');

  // 6. Bullet lists: lines starting with - or *
  //    Collect consecutive list lines and wrap in <ul>
  text = text.replace(/((?:^[ \t]*[-*] .+$\n?)+)/gm, match => {
    const items = match
      .split('\n')
      .filter(l => l.trim())
      .map(l => `<li>${l.replace(/^[ \t]*[-*] /, '').trim()}</li>`)
      .join('');
    return `<ul>${items}</ul>`;
  });

  // 7. Numbered lists: lines starting with 1. 2. etc.
  text = text.replace(/((?:^[ \t]*\d+\. .+$\n?)+)/gm, match => {
    const items = match
      .split('\n')
      .filter(l => l.trim())
      .map(l => `<li>${l.replace(/^[ \t]*\d+\. /, '').trim()}</li>`)
      .join('');
    return `<ol>${items}</ol>`;
  });

  // 8. Paragraphs: double newlines → paragraph break
  text = text.replace(/\n{2,}/g, '<br><br>');

  // 9. Single newlines → <br> (but not inside already-rendered block elements)
  text = text.replace(/(?<!>)\n(?!<)/g, '<br>');

  return text;
}

/* ── Typing indicator ───────────────────────────────────── */
function showTyping() {
  const id  = 'typing_' + Date.now();
  const row = document.createElement('div');
  row.id        = id;
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
    </div>`;
  $msgs.appendChild(row);
  scrollBottom();
  return id;
}

function removeTyping(id) {
  const el = document.getElementById(id);
  if (el) el.remove();
}

/* ── Helpers ────────────────────────────────────────────── */
function scrollBottom() {
  $msgs.scrollTo({ top: $msgs.scrollHeight, behavior: 'smooth' });
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/* ── Init ───────────────────────────────────────────────── */
window.addEventListener('load', () => {
  if (typeof lucide !== 'undefined') lucide.createIcons();
  $input.focus();
});