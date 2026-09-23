/* =====================================================================
   Portfolio — chat.js
   Floating chat assistant: open/close, message rendering (mini
   markdown), localStorage persistence, suggestion chips and a fully
   in-browser Q&A engine that answers from this site's own config and
   the GitHub projects cache. No server involved — perfect for GitHub
   Pages.
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
    const MAX_SAVED = 80;

    let busy = false;
    let open = false;
    let messages = loadMessages();

    /* ========================================================== bot engine */
    /* A direct port of the PHP intent engine from the original full-stack
       build, rewritten in plain JS and fed from config.js + the
       localStorage project cache. */

    const BOT_CFG = window.PORTFOLIO_CONFIG || {};

    const INTENTS = {
        greeting:   ['hi', 'hello', 'hey', 'yo', 'howdy', 'hi there', 'hello there', 'good morning', 'good afternoon', 'good evening', 'salam', 'assalam', 'greetings', 'how are you', 'how r u', 'howdy do', 'whats up', 'what s up', 'sup', 'wassup'],
        thanks:     ['thank you', 'thanks', 'thankyou', 'thx', 'cheers', 'jazak', 'appreciate it', 'nice work', 'great work', 'love it'],
        bye:        ['bye', 'goodbye', 'see you', 'see ya', 'later', 'take care', 'good night', 'goodnight', 'seeya'],
        help:       ['help', 'what can you do', 'what do you know', 'commands', 'options', 'capabilities', 'how does this work', 'how do i use', 'guide me', 'what questions', 'menu'],
        github:     ['github', 'git hub', 'your repos', 'repositories page', 'code hosted'],
        linkedin:   ['linkedin', 'linked in', 'professional profile', 'professional network'],
        resume:     ['resume', 'cv', 'curriculum vitae', 'download cv', 'download resume'],
        education:  ['education', 'educational', 'study', 'studies', 'studied', 'study at', 'school', 'university', 'college', 'degree', 'degree in', 'qualifica', 'graduat', 'alma mater', 'what did you study'],
        location:   ['location', 'located', 'where are you', 'where do you live', 'where are you from', 'which city', 'which country', 'live in', 'based in', 'your base', 'timezone'],
        hire:       ['hire', 'hiring', 'available', 'availability', 'freelance', 'freelancing', 'freelancer', 'job', 'jobs', 'internship', 'intern', 'open to work', 'opportunity', 'opportunities', 'work with you', 'collaborate', 'contract', 'do you take', 'for hire'],
        contact:    ['contact', 'email', 'e mail', 'reach you', 'reach out', 'get in touch', 'message you', 'write to you', 'phone', 'telephone', 'whatsapp', 'send you', 'call you', 'your contact'],
        skills:     ['skill', 'skills', 'technology', 'technologies', 'tech stack', 'stack', 'languages', 'language', 'framework', 'frameworks', 'libraries', 'tools', 'proficient', 'proficiency', 'expertise', 'good at', 'know how', 'what can you build'],
        projects:   ['project', 'projects', 'portfolio', 'repositories', 'repository', 'repos', 'repo', 'my work', 'your work', 'built', 'built projects', 'build', 'side project', 'show me', 'show your'],
        interests:  ['interest', 'interests', 'hobbies', 'hobby', 'like to do', 'free time', 'fun stuff', 'passion', 'what do you enjoy'],
        experience: ['experience', 'experiance', 'how long', 'years of', 'how many years', 'career history'],
        price:      ['price', 'pricing', 'cost', 'how much', 'charge', 'rates', 'rate', 'budget', 'expensive', 'afford', 'quote', 'payment'],
        role:       ['what do you do', 'your job', 'your role', 'occupation', 'profession', 'day job', 'what is your work', 'what do u do', 'do for work'],
        bot:        ['are you a bot', 'are you real', 'are you human', 'who made you', 'who created you', 'who built you', 'what are you', 'are you an ai', 'how do you work', 'chatbot what'],
        about:      ['about you', 'about yourself', 'about him', 'about her', 'about this site', 'who are you', 'your name', 'tell me about', 'your bio', 'introduce', 'tell me who', 'background story', 'a bit about'],
    };

    const TRIVIAL = ['greeting', 'thanks', 'bye'];

    const FALLBACK_SUGGESTIONS = [
        'What are your skills?',
        'Show me your projects',
        'How can I contact you?',
        'Are you available for hire?',
    ];

    /* ---- data helpers (mirror config.php accessors) */

    function botName()        { return BOT_CFG.name || 'the owner'; }
    function botRole()        { return BOT_CFG.role || 'Developer'; }
    function botEmail()       { return BOT_CFG.email || ''; }
    function botLinkedin()    { return BOT_CFG.linkedin_url || ''; }
    function botResume()      { return BOT_CFG.resume_url || ''; }
    function botEducation()   { return (BOT_CFG.education || '').trim(); }
    function botStatus()      { return BOT_CFG.status || {}; }
    function botSkills()      { return (BOT_CFG.skills && typeof BOT_CFG.skills === 'object') ? BOT_CFG.skills : {}; }
    function botInterests()   { return Array.isArray(BOT_CFG.interests) ? BOT_CFG.interests.filter(Boolean) : []; }

    function botGithubUsername() {
        return String(BOT_CFG.github_username || '').trim();
    }

    function botGithubUrl() {
        const u = botGithubUsername();
        return (u !== '' && u.toLowerCase() !== 'your-github-username')
            ? 'https://github.com/' + encodeURIComponent(u)
            : '';
    }

    function botLocationOr(fallback) {
        const loc = (BOT_CFG.location || '').trim();
        return (loc !== '' && loc.toLowerCase().indexOf('your city') === -1) ? loc : fallback;
    }

    function botProjects() {
        try {
            const raw = localStorage.getItem('pf-projects');
            if (!raw) return [];
            const entry = JSON.parse(raw);
            return Array.isArray(entry.data) ? entry.data : [];
        } catch (_) {
            return [];
        }
    }

    function placeholderLinkedin() {
        return botLinkedin().toLowerCase().indexOf('your-handle') !== -1;
    }

    function excerpt(text, limit) {
        text = String(text || '').replace(/\s+/g, ' ').trim();
        if (text === '') return 'More details live in the **About** section of this site.';
        if (text.length <= limit) return text;
        return text.slice(0, limit).replace(/[ \t.,;:]+$/, '') + '…';
    }

    /* ---- intent detection (direct port of ChatBot.php) */

    function normalize(text) {
        return ' ' + String(text).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim() + ' ';
    }

    function detectIntent(message) {
        const normalized = normalize(message);
        if (normalized.trim() === '') return null;

        const hits = [];
        for (const [key, keywords] of Object.entries(INTENTS)) {
            for (const keyword of keywords) {
                const k = normalize(keyword).trim();
                if (k !== '' && normalized.indexOf(' ' + k + ' ') !== -1) {
                    hits.push(key);
                    break;
                }
            }
        }

        if (hits.length === 0) return null;

        const substantive = hits.filter((h) => TRIVIAL.indexOf(h) === -1);
        return substantive[0] || hits[0];
    }

    /* ---- answers (strings use the same mini-markdown the renderer supports) */

    function skillsReply() {
        const skills = botSkills();
        const lines = [];

        for (const [group, items] of Object.entries(skills)) {
            const clean = (Array.isArray(items) ? items : []).filter(Boolean).map(String);
            if (clean.length === 0) continue;
            lines.push('• **' + group + ':** ' + clean.join(', '));
        }

        if (lines.length === 0) {
            return "The owner hasn't listed skills in `config.js` yet — check back soon!";
        }

        return "Here's the tech toolkit:\n\n" + lines.join('\n') + '\n\nWant to see it in action? Ask me about the **projects**!';
    }

    function projectsReply() {
        const projects = botProjects();

        if (projects.length === 0) {
            return 'No projects are cached yet. Open the **Projects** section and hit **Sync now ↻** to pull repositories from GitHub — then ask me again!';
        }

        const count = projects.length;
        const lines = [];

        projects.slice(0, 4).forEach((p) => {
            const lang  = p.language ? ' — ' + p.language : '';
            const stars = p.stars > 0 ? ' ★' + p.stars : '';
            const desc  = excerpt(p.description || '', 90);
            lines.push('• **[' + p.name + '](' + p.html_url + ')**' + lang + stars + '\n  ' + desc);
        });

        return 'There are **' + count + ' project' + (count === 1 ? '' : 's') + '** loaded from GitHub, sorted by stars:\n\n' +
               lines.join('\n\n') + '\n\nSee the full grid in the **Projects** section!';
    }

    function educationReply() {
        const education = botEducation();
        const gh        = botGithubUrl();

        if (education === '') {
            return 'The owner hasn\'t published their education yet — but you can often see where they studied on ' +
                   (placeholderLinkedin() ? 'LinkedIn' : '[LinkedIn](' + botLinkedin() + ')') + '.';
        }

        return '**' + botName() + '** studied at: **' + education + '**.\n\n' +
               'Curious how it\'s put into practice? Check the **projects**!';
    }

    function hireReply() {
        const status   = botStatus();
        const available = Boolean(status.available);
        const note      = String(status.note || '').trim();
        const email     = botEmail();

        let lead = available
            ? '**Yes! ' + botName() + ' is currently open to work.**'
            : '**' + botName() + ' isn\'t actively looking at the moment**, but great opportunities are always worth hearing about.';

        if (note !== '') lead += ' ' + note + '.';

        return lead + '\n\nStart a conversation through the **Contact** form on this page' +
               (email !== '' ? ' or email [' + email + '](mailto:' + email + ')' : '') + '.';
    }

    function contactReply() {
        const email  = botEmail();
        const gh     = botGithubUrl();
        const li     = botLinkedin();
        const resume = botResume();

        const lines = [];
        if (email !== '')  lines.push('• Email: [' + email + '](mailto:' + email + ')');
        if (li !== '')     lines.push('• LinkedIn: [Open profile](' + li + ')');
        if (gh !== '')     lines.push('• GitHub: [' + gh + '](' + gh + ')');
        if (resume !== '') lines.push('• Résumé: [Download](' + resume + ')');

        let body = lines.length > 0
            ? "Here's how to reach **" + botName() + "**:\n\n" + lines.join('\n')
            : "The owner hasn't filled in contact details yet.";
        body += '\n\nYou can also use the **Contact** form on this page — it opens your email app!';

        return body;
    }

    function interestsReply() {
        const interests = botInterests();

        if (interests.length === 0) {
            return 'Hmm, no interests listed yet — but building a portfolio like this one is clearly one of them! 😄';
        }

        return 'Outside of writing code, **' + botName() + '** enjoys:\n\n• ' +
               interests.map((i) => '**' + i + '**').join('\n• ');
    }

    function resumeReply() {
        const resume = botResume();
        const email  = botEmail();

        if (resume !== '') return 'Yes! Here\'s the link: [Download résumé](' + resume + ') 📄';

        return 'A résumé link hasn\'t been added yet. Ask for one via [email](mailto:' + email +
               ') or the **Contact** form instead!';
    }

    function fallbackReply() {
        const email = botEmail();

        return 'Hmm, I don\'t have a ready-made answer for that one yet. 🤔 I\'m best with questions about **skills**, ' +
               '**projects**, **education**, **contact details** and **availability** — try one of those!' +
               (email !== '' ? '\n\nFor anything else, you can write to **' + botName() + '** directly at [' + email + '](mailto:' + email + ').' : '');
    }

    function localAnswer(intent) {
        const name  = botName();
        const role  = botRole();
        const email = botEmail();
        const gh    = botGithubUrl();
        const li    = botLinkedin();

        switch (intent) {
            case 'greeting':
                return {
                    reply: 'Hey there! 👋 I\'m the portfolio assistant for **' + name + '** — ' + role + '. ' +
                           'Ask me anything about skills, projects, education, availability, or how to get in touch!',
                    suggestions: ['What are your skills?', 'Show me your projects', 'How can I contact you?'],
                };

            case 'help':
                return {
                    reply: 'I can answer questions about:\n\n' +
                           '• **Skills & tech stack**\n' +
                           '• **Projects** (synced live from the GitHub API)\n' +
                           '• **Education & interests**\n' +
                           '• **Contact details** — email, LinkedIn, GitHub\n' +
                           '• **Availability** for work and internships\n\n' +
                           'Just ask me naturally — like you\'d ask a human!',
                    suggestions: ['Tell me about yourself', 'What projects have you built?', 'Are you available for hire?'],
                };

            case 'about':
                return {
                    reply: '**' + name + '** — ' + role + '.\n\n' +
                           excerpt(BOT_CFG.bio || '', 340) +
                           '\n\nScroll to the **About** section on this page for the full story!',
                    suggestions: ['What are your skills?', 'Where are you based?', 'Where did you study?'],
                };

            case 'role':
                return {
                    reply: '**' + name + '** works as a **' + role + '**. ' +
                           excerpt(BOT_CFG.tagline || '', 220),
                    suggestions: ['What are your skills?', 'Show me your projects', 'How can I contact you?'],
                };

            case 'skills':
                return {
                    reply: skillsReply(),
                    suggestions: ['Show me your projects', 'Are you available for hire?', 'Where did you study?'],
                };

            case 'projects':
                return {
                    reply: projectsReply(),
                    suggestions: ['What are your skills?', 'How can I contact you?', 'Are you available for hire?'],
                };

            case 'education':
                return {
                    reply: educationReply(),
                    suggestions: ['What are your skills?', 'Show me your projects', 'How can I contact you?'],
                };

            case 'location':
                return {
                    reply: '**' + name + '** is based in **' + botLocationOr('an undisclosed location') + '**.' +
                           (gh !== '' ? ' — and their code lives all over the world on [GitHub](' + gh + ')! 🌍' : ''),
                    suggestions: ['What are your skills?', 'Show me your projects', 'Are you available for hire?'],
                };

            case 'hire':
                return {
                    reply: hireReply(),
                    suggestions: ['Show me your projects', 'How can I contact you?', 'What are your skills?'],
                };

            case 'github':
                return {
                    reply: gh !== ''
                        ? 'Everything public lives at [github.com/' + botGithubUsername() + '](' + gh + '). ' +
                          'The **Projects** section of this page pulls straight from the GitHub API in your browser — click **Sync now ↻** to refresh!'
                        : "The GitHub link hasn't been configured yet — the owner needs to add `github_username` in `assets/js/config.js`.",
                    suggestions: ['Show me your projects', 'What are your skills?', 'How can I contact you?'],
                };

            case 'linkedin':
                return {
                    reply: li !== ''
                        ? "Sure — here's the LinkedIn profile: [" + li + '](' + li + '). ' +
                          'You can also find GitHub and email under **Contact** on this page.'
                        : "The LinkedIn URL hasn't been added yet — let the owner know to update `linkedin_url` in `assets/js/config.js`.",
                    suggestions: ['Show me your projects', 'How can I contact you?', 'Are you available for hire?'],
                };

            case 'resume':
                return {
                    reply: resumeReply(),
                    suggestions: ['Show me your projects', 'How can I contact you?'],
                };

            case 'contact':
                return {
                    reply: contactReply(),
                    suggestions: ['Show me your projects', 'Are you available for hire?', 'Tell me about yourself'],
                };

            case 'interests':
                return {
                    reply: interestsReply(),
                    suggestions: ['What are your skills?', 'Show me your projects', 'Where did you study?'],
                };

            case 'experience':
                return {
                    reply: 'As a **' + role + '**, the best evidence of hands-on experience is the **Projects** section — ' +
                           'real repositories synced live from GitHub.' +
                           (li !== '' ? ' For a full career history and recommendations, check [LinkedIn](' + li + ').' : ''),
                    suggestions: ['Show me your projects', 'What are your skills?', 'How can I contact you?'],
                };

            case 'price':
                return {
                    reply: 'Pricing depends on scope and timeline. The fastest way to get a real quote is to send the ' +
                           'details through the **Contact** form on this page' +
                           (email !== '' ? ' or email [' + email + '](mailto:' + email + ')' : '') +
                           ' — you\'ll get a reply soon!',
                    suggestions: ['Are you available for hire?', 'How can I contact you?'],
                };

            case 'bot':
                return {
                    reply: "I'm a small assistant that runs entirely in your browser — a keyword engine that answers " +
                           "from this site's own config and the GitHub projects cache. No servers, no wizardry — just JavaScript!",
                    suggestions: ['What can you do?', 'Show me your projects', 'Tell me about yourself'],
                };

            case 'thanks':
                return {
                    reply: "You're very welcome! 😊 Anything else I can help you with?",
                    suggestions: ['Show me your projects', 'What are your skills?', 'How can I contact you?'],
                };

            case 'bye':
                return {
                    reply: 'Bye! 👋 It was great chatting with you — feel free to come back anytime. Good luck with your own projects too!',
                    suggestions: ['Show me your projects', 'Tell me about yourself'],
                };

            default:
                return null;
        }
    }

    function botAnswer(message) {
        const intent = detectIntent(message);

        if (intent !== null) {
            const local = localAnswer(intent);
            if (local !== null) {
                return { reply: local.reply, source: 'local', suggestions: local.suggestions };
            }
        }

        return {
            reply: fallbackReply(),
            source: 'local',
            suggestions: FALLBACK_SUGGESTIONS,
        };
    }

    /* ------------------------------------------------------------ helpers */

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
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

        messagesEl.appendChild(div);
        scrollDown();
    }

    function renderAll() {
        messagesEl.innerHTML = '';
        messages.forEach((m) => {
            const div = document.createElement('div');
            div.className = 'chat-msg ' + (m.role === 'user' ? 'user' : 'bot');
            div.innerHTML = md(m.text || '');
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

        // Answer locally (this build has no backend) with a tiny pause
        // so the typing indicator is actually visible.
        const answer = botAnswer(message);

        await new Promise((resolve) => setTimeout(resolve, 500 + Math.random() * 400));

        hideTyping();

        const reply = String(answer.reply || '').trim() || 'I did not catch that — could you rephrase?';
        messages.push({ role: 'bot', text: reply, source: answer.source });
        saveMessages();
        addMessage('bot', reply, answer.source);
        renderSuggestions(answer.suggestions);

        busy = false;
        sendBtn.disabled = false;
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