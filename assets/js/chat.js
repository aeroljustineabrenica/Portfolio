/* =====================================================================
   Portfolio — chat.js
   Floating chat assistant: open/close, message rendering (mini
   markdown), localStorage persistence, suggestion chips and live
   Q&A via api/chatbot.php.
   ===================================================================== */
(function () {
    'use strict';

    const fab = document.getElementById('chat-fab');
    if (!fab) return;

    const panel        = document.getElementById('chat-panel');
    const messagesEl   = document.getElementById('chat-messages');
    const suggestions  = document.getElementById('chat-suggestions');
    const form         = document.getElementById('chat-form');
    const input        = document.getElementById('chat-input');
    const sendBtn      = document.getElementById('chat-send');
    const clearBtn     = document.getElementById('chat-clear');
    const closeBtn     = document.getElementById('chat-close');

    const STORE_KEY = 'portfolio-chat-v1';
    const SID_KEY   = 'portfolio-chat-sid';
    const MAX_SAVED = 80;

    let busy = false;
    let open = false;
    let messages = loadMessages();

    /* ------------------------------------------------------------ helpers */

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    function sessionId() {
        try {
            let id = localStorage.getItem(SID_KEY);
            if (!id) {
                id = uuid();
                localStorage.setItem(SID_KEY, id);
            }
            return id;
        } catch (_) {
            return uuid();
        }
    }

    function loadMessages() {
        try {
            const raw = localStorage.getItem(STORE_KEY);
            const data = raw ? JSON.parse(raw) : [];
            return Array.isArray(data) ? data : [];
        } catch (_) {
            return [];
        }
    }

    function saveMessages() {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify(messages.slice(-MAX_SAVED)));
        } catch (_) {}
    }

    function ownerName() {
        const el = document.getElementById('hero-name');
        const name = el ? el.textContent.trim() : '';
        return name && name !== 'Your Name' ? name : 'the owner';
    }

    /* ----------------------------------------------- tiny markdown renderer */

    function md(text) {
        let s = esc(text);

        // Links — only allow safe protocols
        s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (match, label, url) => {
            if (/^(https?:|mailto:)/i.test(url)) {
                return `<a href="${url}" target="_blank" rel="noopener">${label}</a>`;
            }
            return match;
        });

        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    /* ------------------------------------------------------ UI operations */

    function scrollDown() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function addMessage(role, text, source) {
        const div = document.createElement('div');
        div.className = 'chat-msg ' + role;
        div.innerHTML = md(text);

        if (role === 'bot' && source === 'ai') {
            const badge = document.createElement('span');
            badge.className = 'msg-src';
            badge.textContent = 'AI answer';
            div.appendChild(badge);
        }

        messagesEl.appendChild(div);
        scrollDown();
    }

    function renderAll() {
        messagesEl.innerHTML = '';
        messages.forEach((m) => {
            const div = document.createElement('div');
            div.className = 'chat-msg ' + (m.role === 'user' ? 'user' : 'bot');
            div.innerHTML = md(m.text || '');
            if (m.role === 'bot' && m.source === 'ai') {
                const badge = document.createElement('span');
                badge.className = 'msg-src';
                badge.textContent = 'AI answer';
                div.appendChild(badge);
            }
            messagesEl.appendChild(div);
        });
        scrollDown();
    }

    function showTyping() {
        const el = document.createElement('div');
        el.className = 'chat-typing';
        el.id = 'chat-typing';
        el.setAttribute('aria-label', 'Assistant is typing');
        el.innerHTML = '<i></i><i></i><i></i>';
        messagesEl.appendChild(el);
        scrollDown();
    }

    function hideTyping() {
        const el = document.getElementById('chat-typing');
        if (el) el.remove();
    }

    function renderSuggestions(list) {
        suggestions.innerHTML = '';
        if (!Array.isArray(list)) return;

        list.forEach((text) => {
            if (typeof text !== 'string' || !text.trim()) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = text;
            btn.addEventListener('click', () => sendMessage(text));
            suggestions.appendChild(btn);
        });
    }

    function setOpen(value) {
        open = value;
        panel.classList.toggle('open', value);
        panel.setAttribute('aria-hidden', String(!value));
        fab.setAttribute('aria-expanded', String(value));
        fab.setAttribute('data-open', String(value));
        fab.setAttribute('aria-label', value ? 'Close chat assistant' : 'Open chat assistant');

        if (value) {
            setTimeout(() => input.focus(), 60);
        }
    }

    /* ---------------------------------------------------------- messaging */

    async function sendMessage(text) {
        const message = String(text || '').trim();
        if (!message || busy) return;

        busy = true;
        sendBtn.disabled = true;
        renderSuggestions([]);

        messages.push({ role: 'user', text: message, source: null });
        saveMessages();
        addMessage('user', message);

        showTyping();

        try {
            const history = messages
                .slice(-8)
                .map((m) => ({
                    role: m.role === 'user' ? 'user' : 'assistant',
                    content: m.text,
                }));

            const res = await fetch('api/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ message, history, session_id: sessionId() }),
            });

            const json = await res.json();
            hideTyping();

            if (!json.ok || !json.data) {
                throw new Error(json.error || 'The assistant could not respond.');
            }

            const reply = String(json.data.reply || '').trim() || 'I did not catch that — could you rephrase?';
            messages.push({ role: 'bot', text: reply, source: json.data.source });
            saveMessages();
            addMessage('bot', reply, json.data.source);
            renderSuggestions(json.data.suggestions);
        } catch (err) {
            hideTyping();
            const fallback = 'Sorry — I could not reach the assistant just now. ' +
                             (err && err.message ? err.message : 'Please try again in a moment.');
            messages.push({ role: 'bot', text: fallback, source: 'local' });
            saveMessages();
            addMessage('bot', fallback, 'local');
            renderSuggestions(['What is your tech stack?', 'Show me projects', 'How can I contact you?']);
        } finally {
            busy = false;
            sendBtn.disabled = false;
        }
    }

    /* --------------------------------------------------------- interactions */

    fab.addEventListener('click', () => {
        if (!open && messages.length === 0) {
            const welcome =
                `Hi! I'm the assistant for **${ownerName()}**'s portfolio. ✨\n\n` +
                'Ask me about their skills, projects, education, availability — or how to get in touch.';
            messages.push({ role: 'bot', text: welcome, source: 'local' });
            saveMessages();
        }
        setOpen(!open);
        renderAll();
        if (open) renderSuggestions(['What are your skills?', 'Show me your projects', 'How can I contact you?']);
    });

    if (closeBtn) closeBtn.addEventListener('click', () => setOpen(false));

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            messages = [];
            saveMessages();
            renderSuggestions([]);
            setOpen(false);
        });
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = input.value;
        input.value = '';
        sendMessage(text);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && open) setOpen(false);
    });

    // Initial state: restore any saved conversation (rendered on open).
    renderAll();
})();