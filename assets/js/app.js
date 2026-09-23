/* =====================================================================
   Portfolio — app.js
   Progressive enhancement layer: theme, motion, live GitHub projects,
   stat counters, search/filters and the contact form.

   This static build runs on GitHub Pages: it talks to the public GitHub
   REST API directly from the browser, and caches the responses in
   localStorage so visitors rarely touch the API rate limit.
   ===================================================================== */
(function () {
    'use strict';

    const CFG = window.PORTFOLIO_CONFIG || {};
    const GITHUB_USER = String(CFG.github_username || '').trim();
    const GH_BASE = 'https://api.github.com';
    const GH_TTL = Number(CFG.github && CFG.github.sync_ttl) || 3600;

    /* ------------------------------------------------------------ Utils */
    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const esc = (value) =>
        String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const LANG_COLORS = {
        PHP: '#777BB4', JavaScript: '#f1e05a', TypeScript: '#3178c6', HTML: '#e34c26',
        HTML5: '#e34c26', CSS: '#563d7c', SCSS: '#c6538c', Python: '#3572A5',
        Java: '#b07219', 'C#': '#178600', 'C++': '#f34b7d', C: '#555555',
        Ruby: '#701516', Go: '#00ADD8', Rust: '#dea584', Swift: '#F05138',
        Kotlin: '#A97BFF', Dart: '#00B4AB', Shell: '#89e051', PowerShell: '#012456',
        Vue: '#41b883', Svelte: '#ff3e00', Astro: '#bc52ee', Dockerfile: '#384d54',
        Lua: '#000080', R: '#198CE7', Scala: '#c22d40', Perl: '#0298c3',
        Blade: '#f05352', Smarty: '#f0ac4e', 'Jupyter Notebook': '#DA5B0B',
        Makefile: '#427819', Vim: '#199f4b', 'Emacs Lisp': '#9999ff',
    };

    const defaultLangColor = '#8b949e';

    function formatDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short' });
    }

    function timeAgo(iso) {
        if (!iso) return '';
        const then = new Date(iso).getTime();
        const secs = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (secs < 60) return 'just now';
        const mins = Math.floor(secs / 60);
        if (mins < 60) return `${mins} min ago`;
        const hours = Math.floor(mins / 60);
        if (hours < 24) return `${hours} hr ago`;
        const days = Math.floor(hours / 24);
        if (days < 30) return `${days} day${days === 1 ? '' : 's'} ago`;
        return formatDate(iso);
    }

    /* ------------------------------------------------ localStorage cache */
    function readCache(key, ttlSeconds) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const entry = JSON.parse(raw);
            if (!entry || !Array.isArray(entry.data)) return null;
            const age = (Date.now() - new Date(entry.syncedAt).getTime()) / 1000;
            const stale = Number.isFinite(ttlSeconds) && ttlSeconds >= 0 && age > ttlSeconds;
            return { data: entry.data, syncedAt: entry.syncedAt, stale };
        } catch (_) {
            return null;
        }
    }

    function writeCache(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify({
                syncedAt: new Date().toISOString(),
                data,
            }));
        } catch (_) {}
    }

    function isConfigured() {
        return GITHUB_USER !== '' && GITHUB_USER.toLowerCase() !== 'your-github-username';
    }

    async function ghFetch(path) {
        const res = await fetch(GH_BASE + path, {
            headers: {
                Accept: 'application/vnd.github+json',
                'X-GitHub-Api-Version': '2022-11-28',
            },
        });
        const json = await res.json().catch(() => ({}));

        if (res.status === 404) {
            throw new Error(`GitHub user "${GITHUB_USER}" was not found — check github_username in assets/js/config.js.`);
        }
        if (res.status === 403 || res.status === 429) {
            throw new Error('GitHub API rate limit reached — projects are shown from cache. Try again in a little while.');
        }
        if (!res.ok) {
            throw new Error(`GitHub API error (HTTP ${res.status}).`);
        }

        let rate = null;
        for (const h of ['x-ratelimit-remaining', 'x-ratelimit-limit']) {
            try {
                const v = res.headers.get(h);
                if (v !== null) {
                    rate = rate || {};
                    rate[h === 'x-ratelimit-remaining' ? 'remaining' : 'limit'] = Number(v);
                }
            } catch (_) {}
        }

        return { json, rate };
    }

    /* ------------------------------------------------------------ Toasts */
    const toastWrap = $('#toasts');

    function toast(message, type = 'info', duration = 4500) {
        if (!toastWrap) return;

        const icon = type === 'success' ? '#i-check' : '#i-alert';
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.innerHTML =
            `<svg class="icon" aria-hidden="true"><use href="${icon}"/></svg><span>${esc(message)}</span>`;
        toastWrap.appendChild(el);

        setTimeout(() => {
            el.classList.add('out');
            el.addEventListener('animationend', () => el.remove(), { once: true });
        }, duration);
    }

    /* ------------------------------------------------------------ Theme */
    const themeToggle = $('#theme-toggle');

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', current);
            try { localStorage.setItem('portfolio-theme', current); } catch (_) {}
        });
    }

    /* ---------------------------------------------------------------- Nav */
    const nav = $('#nav');
    const navLinks = $('#nav-links');
    const burger = $('#nav-burger');

    function onScrollNav() {
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 24);
    }

    window.addEventListener('scroll', onScrollNav, { passive: true });
    onScrollNav();

    if (burger && navLinks) {
        burger.addEventListener('click', () => {
            const open = navLinks.classList.toggle('open');
            burger.classList.toggle('open', open);
            burger.setAttribute('aria-expanded', String(open));
        });

        navLinks.addEventListener('click', (e) => {
            if (e.target.closest('a')) {
                navLinks.classList.remove('open');
                burger.classList.remove('open');
                burger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Active section highlighting
    const sectionIds = ['home', 'about', 'projects', 'contact'];
    const navAnchors = $$('.nav-link');

    function updateActiveLink() {
        let current = 'home';
        const offset = window.innerHeight * 0.32;

        for (const id of sectionIds) {
            const section = document.getElementById(id);
            if (section && section.getBoundingClientRect().top <= offset) {
                current = id;
            }
        }

        navAnchors.forEach((a) => {
            const href = a.getAttribute('href') || '';
            a.classList.toggle('active', href === `#${current}`);
        });
    }

    window.addEventListener('scroll', updateActiveLink, { passive: true });
    updateActiveLink();

    /* ------------------------------------------------------ Reveal on scroll */
    const revealEls = $$('.reveal');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealEls.forEach((el) => el.classList.add('visible'));
    } else {
        const revealObserver = new IntersectionObserver(
            (entries, obs) => {
                entries.forEach((entry, i) => {
                    if (!entry.isIntersecting) return;
                    const el = entry.target;
                    const delay = Number(el.dataset.delay || i * 70);
                    setTimeout(() => el.classList.add('visible'), Math.min(delay, 420));
                    obs.unobserve(el);
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );

        revealEls.forEach((el, i) => {
            el.dataset.delay = String((i % 4) * 80);
            revealObserver.observe(el);
        });
    }

    /* --------------------------------------------------------- Typewriter */
    function startTypewriter() {
        const target = $('#typewriter');
        if (!target) return;

        let roles = [];
        try {
            roles = JSON.parse($('#roles-data')?.textContent || '[]');
        } catch (_) {}

        if (!Array.isArray(roles) || roles.length === 0) return;

        if (reduceMotion) {
            target.textContent = roles[0];
            return;
        }

        let roleIndex = 0;
        let charIndex = 0;
        let deleting = false;

        function tick() {
            const word = roles[roleIndex];

            if (!deleting) {
                charIndex++;
                target.textContent = word.slice(0, charIndex);
                if (charIndex === word.length) {
                    deleting = true;
                    setTimeout(tick, 1900);
                    return;
                }
                setTimeout(tick, 68 + Math.random() * 45);
            } else {
                charIndex--;
                target.textContent = word.slice(0, charIndex);
                if (charIndex === 0) {
                    deleting = false;
                    roleIndex = (roleIndex + 1) % roles.length;
                    setTimeout(tick, 320);
                    return;
                }
                setTimeout(tick, 34);
            }
        }

        charIndex = roles[0].length;
        target.textContent = roles[0];
        deleting = true;
        setTimeout(tick, 1600);
    }

    startTypewriter();

    /* ------------------------------------------------------ Stat counters */
    function animateCounter(el, target) {
        if (target === null || target === undefined || Number.isNaN(target)) {
            el.textContent = '—';
            return;
        }

        if (reduceMotion) {
            el.textContent = target.toLocaleString();
            return;
        }

        const duration = 1200;
        const start = performance.now();

        function frame(now) {
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) requestAnimationFrame(frame);
        }

        requestAnimationFrame(frame);
    }

    // Only animates the stats that are actually provided.
    function renderStats(stats) {
        if (!stats) return;
        const map = {
            repos: stats.public_repos,
            stars: stats.stars,
            followers: stats.followers,
            following: stats.following,
        };
        Object.entries(map).forEach(([key, value]) => {
            if (value === undefined) return;
            const el = document.querySelector(`[data-stat="${key}"]`);
            if (el) animateCounter(el, value);
        });
    }

    /* ------------------------------------------------------ Profile load */
    async function loadProfile() {
        if (!isConfigured()) {
            toast('Set your GitHub username in assets/js/config.js to load your stats.', 'warning', 7000);
            return null;
        }

        // Prefer a recent cache — this is what keeps us well under the
        // unauthenticated GitHub API limit (60 requests/hour per IP).
        const cached = readCache('pf-profile', GH_TTL);
        if (cached && !cached.stale) {
            renderStats(cached.data);
            return cached.data;
        }

        try {
            const { json } = await ghFetch('/users/' + encodeURIComponent(GITHUB_USER));
            const stats = {
                public_repos: json.public_repos ?? null,
                followers: json.followers ?? null,
                following: json.following ?? null,
            };
            writeCache('pf-profile', stats);
            renderStats(stats);

            const avatar = $('#avatar-img');
            if (avatar && json.avatar_url) {
                avatar.src = json.avatar_url;
            }
            return stats;
        } catch (err) {
            const fallback = readCache('pf-profile', -1);
            if (fallback) {
                renderStats(fallback.data);
                toast('Showing cached stats — ' + err.message, 'warning', 6000);
            } else {
                toast(err.message, 'error');
            }
            return null;
        }
    }

    /* ----------------------------------------------------- Projects data */
    const grid = $('#projects-grid');
    const emptyBox = $('#projects-empty');
    const emptyText = $('#projects-empty-text');
    const metaLine = $('#projects-meta');
    const searchInput = $('#project-search');
    const langFilters = $('#lang-filters');
    const syncBtn = $('#sync-btn');
    const syncLabel = $('#sync-label');

    let allProjects = [];
    let activeLang = 'All';
    let searchTerm = '';
    let busy = false;
    let lastRate = null;

    function projectCard(p, index) {
        const color = LANG_COLORS[p.language] || defaultLangColor;
        const topics = (p.topics || []).slice(0, 3)
            .map((t) => `<span class="pc-topic">${esc(t)}</span>`)
            .join('');
        const lang = p.language
            ? `<span class="pc-lang"><span class="lang-dot" style="--lang-color:${color}"></span>${esc(p.language)}</span>`
            : '<span class="pc-lang"><span class="lang-dot"></span>Other</span>';
        const home = p.homepage
            ? `<a href="${esc(p.homepage)}" target="_blank" rel="noopener" aria-label="Live demo of ${esc(p.name)}"><svg class="icon"><use href="#i-external"/></svg></a>`
            : '';
        const delay = Math.min(index * 60, 420);

        return `
            <article class="project-card" style="animation-delay:${delay}ms">
                <div class="pc-top">
                    <div class="pc-repo-icon"><svg class="icon"><use href="#i-folder"/></svg></div>
                    ${lang}
                </div>
                <h3 class="pc-title"><a href="${esc(p.html_url)}" target="_blank" rel="noopener">${esc(p.name)}</a></h3>
                <p class="pc-desc">${esc(p.description || 'No description provided.')}</p>
                ${topics ? `<div class="pc-topics">${topics}</div>` : ''}
                <div class="pc-footer">
                    <div class="pc-stats">
                        <span title="Stars"><svg class="icon"><use href="#i-star"/></svg>${p.stars}</span>
                        <span title="Forks"><svg class="icon" style="color:var(--accent-2)"><use href="#i-fork"/></svg>${p.forks}</span>
                        ${p.pushed_at ? `<span class="pc-updated" title="Last push">${formatDate(p.pushed_at)}</span>` : ''}
                    </div>
                    <div class="pc-links">
                        <a href="${esc(p.html_url)}" target="_blank" rel="noopener" aria-label="Repository for ${esc(p.name)}"><svg class="icon"><use href="#i-github"/></svg></a>
                        ${home}
                    </div>
                </div>
            </article>`;
    }

    function matches(p) {
        const langOk = activeLang === 'All' || p.language === activeLang;
        if (!langOk) return false;
        if (!searchTerm) return true;
        const hay = `${p.name} ${p.description || ''} ${(p.topics || []).join(' ')} ${p.language || ''}`.toLowerCase();
        return hay.includes(searchTerm);
    }

    function renderProjects() {
        if (!grid) return;

        const list = allProjects.filter(matches);

        if (list.length === 0) {
            grid.innerHTML = '';
            grid.setAttribute('aria-busy', 'false');
            if (emptyBox) {
                emptyBox.hidden = false;
                if (allProjects.length === 0) {
                    emptyText.innerHTML = 'No projects yet.';
                } else {
                    emptyText.innerHTML = 'No projects match your search.';
                }
            }
            return;
        }

        if (emptyBox) emptyBox.hidden = true;
        grid.innerHTML = list.map(projectCard).join('');
        grid.setAttribute('aria-busy', 'false');
        attachCardGlow();
    }

    function renderLangFilters() {
        if (!langFilters) return;

        const counts = {};
        allProjects.forEach((p) => {
            const lang = p.language || 'Other';
            counts[lang] = (counts[lang] || 0) + 1;
        });

        const langs = Object.keys(counts).sort((a, b) => counts[b] - counts[a] || a.localeCompare(b));
        const chips = ['All', ...langs];

        if (!chips.includes(activeLang)) activeLang = 'All';

        langFilters.innerHTML = chips
            .map(
                (lang) =>
                    `<button type="button" class="chip${lang === activeLang ? ' active' : ''}" data-lang="${esc(lang)}">${esc(lang)}${lang !== 'All' ? ` <span style="opacity:.65;margin-left:4px">${counts[lang]}</span>` : ''}</button>`
            )
            .join('');
    }

    if (langFilters) {
        langFilters.addEventListener('click', (e) => {
            const chip = e.target.closest('.chip');
            if (!chip) return;
            activeLang = chip.dataset.lang || 'All';
            $$('.chip', langFilters).forEach((c) => c.classList.toggle('active', c === chip));
            renderProjects();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            searchTerm = searchInput.value.trim().toLowerCase();
            renderProjects();
        });
    }

    function setMeta(meta) {
        if (!metaLine) return;

        if (meta.configured === false) {
            metaLine.innerHTML = `<span class="dot warn"></span> ${esc(meta.message || 'GitHub is not configured yet.')}`;
            return;
        }

        const parts = [];
        parts.push(
            meta.source === 'github'
                ? '<span class="dot"></span> Live sync from GitHub'
                : '<span class="dot warn"></span> Serving cached data'
        );
        parts.push(`${meta.total} project${meta.total === 1 ? '' : 's'}`);
        if (meta.synced_at) parts.push(`synced ${timeAgo(meta.synced_at)}`);
        if (meta.rate && meta.rate.remaining !== null && meta.rate.limit !== null) {
            parts.push(`API ${meta.rate.remaining}/${meta.rate.limit}`);
        }

        metaLine.innerHTML = parts.map((x) => `<span>${x}</span>`).join('<span style="opacity:.4">·</span>');

        if (meta.warning) {
            toast(meta.warning, 'warning', 8000);
        }
    }

    /* GitHub repo shape → the shape the UI expects (mirrors ProjectStore).
       Raw fields come straight from the GitHub REST API. */
    function mapRepo(repo) {
        return {
            id: repo.id ?? 0,
            name: repo.name,
            full_name: repo.full_name,
            description: repo.description || 'No description provided.',
            homepage: repo.homepage || null,
            html_url: repo.html_url,
            language: repo.language,
            stars: repo.stargazers_count ?? 0,
            forks: repo.forks_count ?? 0,
            topics: Array.isArray(repo.topics) ? repo.topics : [],
            is_fork: Boolean(repo.fork),
            is_archived: Boolean(repo.archived),
            pushed_at: repo.pushed_at,
            synced_at: new Date().toISOString(),
        };
    }

    async function ghReposAll() {
        const perPage = 100;
        const maxPages = 3;
        const repos = [];

        for (let page = 1; page <= maxPages; page++) {
            const path = `/users/${encodeURIComponent(GITHUB_USER)}/repos` +
                `?per_page=${perPage}&page=${page}&sort=pushed&direction=desc`;
            const { json, rate } = await ghFetch(path);
            if (rate) lastRate = rate;
            if (!Array.isArray(json)) break;
            repos.push(...json);
            if (json.length < perPage) break;
        }

        return repos;
    }

    function filterAndSortRepos(raw) {
        const g = CFG.github || {};
        const excludeForks = g.exclude_forks !== false;
        const minStars = Number(g.min_stars) || 0;
        const maxRepos = Math.max(1, Number(g.max_repos) || 20);

        const projects = raw
            .filter((r) => !(excludeForks && r.is_fork))
            .filter((r) => r.stars >= minStars)
            .filter((r) => !r.is_archived)
            .sort((a, b) => b.stars - a.stars || String(b.pushed_at || '').localeCompare(String(a.pushed_at || '')))
            .slice(0, maxRepos);

        return projects;
    }

    async function loadProjects(forceRefresh = false) {
        if (busy) return;
        busy = true;

        if (syncBtn) {
            syncBtn.disabled = true;
            syncBtn.classList.add('syncing');
        }
        if (syncLabel) syncLabel.textContent = 'Syncing…';
        if (grid) grid.setAttribute('aria-busy', 'true');
        if (metaLine && forceRefresh) metaLine.textContent = 'Pulling the latest repositories from GitHub…';

        try {
            if (!isConfigured()) {
                setMeta({ configured: false, message: 'Set your GitHub username in assets/js/config.js to display your projects.' });
                return;
            }

            const cached = readCache('pf-projects', GH_TTL);
            if (cached && !cached.stale && !forceRefresh) {
                allProjects = cached.data;
                setMeta({
                    configured: true,
                    source: 'cache',
                    total: allProjects.length,
                    synced_at: cached.syncedAt,
                    rate: null,
                });
                renderLangFilters();
                renderProjects();
            } else {
                const raw = await ghReposAll();
                allProjects = raw.map(mapRepo);
                allProjects = filterAndSortRepos(allProjects);

                writeCache('pf-projects', allProjects);
                setMeta({
                    configured: true,
                    source: 'github',
                    total: allProjects.length,
                    synced_at: new Date().toISOString(),
                    rate: lastRate,
                });
                renderLangFilters();
                renderProjects();
            }

            // Refresh hero stats from the loaded projects.
            const stars = allProjects.reduce((sum, p) => sum + (p.stars || 0), 0);
            renderStats({ stars, public_repos: allProjects.length });

            if (forceRefresh) {
                toast('Projects synced with GitHub.', 'success');
            }
        } catch (err) {
            // Fall back to whatever is cached so the section still renders.
            const fallback = readCache('pf-projects', -1);
            if (fallback && Array.isArray(fallback.data) && fallback.data.length > 0) {
                allProjects = fallback.data;
                setMeta({
                    configured: true,
                    source: 'cache',
                    total: allProjects.length,
                    synced_at: fallback.syncedAt,
                    warning: err.message,
                    rate: null,
                });
                renderLangFilters();
                renderProjects();
            } else {
                if (metaLine) metaLine.innerHTML = `<span class="dot warn"></span> ${esc(err.message)}`;
                toast(err.message || 'Could not load projects.', 'error');
                if (grid) grid.setAttribute('aria-busy', 'false');
            }
        } finally {
            busy = false;
            if (syncBtn) {
                syncBtn.disabled = false;
                syncBtn.classList.remove('syncing');
            }
            if (syncLabel) syncLabel.textContent = 'Sync now';
        }
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', () => loadProjects(true));
    }

    /* Card spotlight glow following the cursor */
    function attachCardGlow() {
        if (reduceMotion) return;
        $$('.project-card', grid).forEach((card) => {
            card.addEventListener('pointermove', (e) => {
                const rect = card.getBoundingClientRect();
                card.style.setProperty('--mx', `${e.clientX - rect.left}px`);
                card.style.setProperty('--my', `${e.clientY - rect.top}px`);
            });
        });
    }

    /* -------------------------------------------------------- Contact form */
    const form = $('#contact-form');

    function setFieldError(name, message) {
        const input = form.querySelector(`[name="${name}"]`);
        const errEl = form.querySelector(`[data-error-for="${name}"]`);
        if (errEl) errEl.textContent = message || '';
        const field = input?.closest('.field');
        if (field) field.classList.toggle('invalid', Boolean(message));
    }

    function validateClient(data) {
        const errors = {};
        if (!data.name || data.name.trim().length < 2) errors.name = 'Please enter your name (at least 2 characters).';
        if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) errors.email = 'Please enter a valid email address.';
        if (!data.message || data.message.trim().length < 5) errors.message = 'Message must be at least 5 characters.';
        return errors;
    }

    if (form) {
        form.addEventListener('input', (e) => {
            const name = e.target.getAttribute('name');
            if (name) setFieldError(name, '');
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const payload = {
                name: form.name.value,
                email: form.email.value,
                subject: form.subject.value,
                message: form.message.value,
            };

            ['name', 'email', 'subject', 'message'].forEach((f) => setFieldError(f, ''));

            const clientErrors = validateClient(payload);
            if (Object.keys(clientErrors).length > 0) {
                Object.entries(clientErrors).forEach(([f, msg]) => setFieldError(f, msg));
                toast('Please fix the highlighted fields.', 'error');
                return;
            }

            const btn = $('#contact-submit');
            const label = btn.querySelector('span');
            btn.disabled = true;
            if (label) label.textContent = 'Sending…';

            const contactCfg = CFG.contact || {};

            try {
                // Option A: a form service endpoint (e.g. Formspree).
                if (contactCfg.active && contactCfg.endpoint) {
                    const fd = new FormData(form);
                    const res = await fetch(contactCfg.endpoint, {
                        method: 'POST',
                        body: fd,
                        headers: { Accept: 'application/json' },
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok && !json.ok) {
                        throw new Error('The message could not be sent. Try email instead.');
                    }
                    toast('Message sent!', 'success', 6000);
                    form.reset();
                } else {
                    // Option B: fall back to the visitor's email app.
                    const subject = payload.subject
                        ? encodeURIComponent(payload.subject)
                        : encodeURIComponent('Project inquiry');
                    const body = encodeURIComponent(
                        `Hi,\n\n${payload.message}\n\n— ${payload.name}\n${payload.email}`
                    );
                    window.location.href = `mailto:${CFG.email}?subject=${subject}&body=${body}`;
                    toast('Opening your email app…', 'success', 6000);
                    form.reset();
                }
            } catch (err) {
                toast(err.message || 'Could not send your message.', 'error');
            } finally {
                btn.disabled = false;
                if (label) label.textContent = 'Send message';
            }
        });
    }

    /* ---------------------------------------------------------------- Init */
    const yearEl = $('#year');
    if (yearEl) yearEl.textContent = String(new Date().getFullYear());

    loadProfile().then(() => {
        loadProjects(false);
    });
})();