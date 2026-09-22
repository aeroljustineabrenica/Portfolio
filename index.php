<?php
declare(strict_types=1);

/* =====================================================================
 *  Portfolio — server-rendered shell.
 *  Personal content comes from config.php; GitHub projects/stats are
 *  fetched live by assets/js/app.js from the JSON API.
 * ===================================================================== */

$cfg = require __DIR__ . '/config.php';

function c(string $key, mixed $default = ''): mixed
{
    global $cfg;
    $node = $cfg;
    foreach (explode('.', $key) as $part) {
        if (is_array($node) && array_key_exists($part, $node)) {
            $node = $node[$part];
        } else {
            return $default;
        }
    }
    return $node;
}

$e  = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$name        = (string) c('name', 'Your Name');
$initials    = strtoupper((string) c('initials', 'YN'));
$role        = (string) c('role', 'Developer');
$tagline     = (string) c('tagline', '');
$bio         = (string) c('bio', '');
$email       = (string) c('email', '');
$location    = (string) c('location', '');
$avatar      = trim((string) c('avatar_url', ''));
$resume      = trim((string) c('resume_url', ''));
$githubUser  = trim((string) c('github_username', ''));
$linkedin    = trim((string) c('linkedin_url', ''));
$website     = trim((string) c('website_url', ''));
$roles       = (array) c('roles', []);
$skills      = (array) c('skills', []);
$status      = (array) c('status', ['available' => false, 'note' => '']);

$githubUrl   = ($githubUser !== '' && $githubUser !== 'your-github-username')
    ? 'https://github.com/' . rawurlencode($githubUser)
    : 'https://github.com';

$faviconSvg = sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#7c6cf6"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs><rect width="64" height="64" rx="14" fill="url(#g)"/><text x="32" y="42" font-family="Arial,Helvetica,sans-serif" font-size="26" font-weight="700" fill="#fff" text-anchor="middle">%s</text></svg>',
    $e($initials)
);
$favicon = 'data:image/svg+xml,' . rawurlencode($faviconSvg);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $e($tagline) ?>">
    <meta name="author" content="<?= $e($name) ?>">
    <meta property="og:title" content="<?= $e($name) ?> — <?= $e($role) ?>">
    <meta property="og:description" content="<?= $e($tagline) ?>">
    <meta property="og:type" content="website">
    <title><?= $e($name) ?> — <?= $e($role) ?></title>
    <link rel="icon" href="<?= $e($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('portfolio-theme');
                if (t) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body>

<!-- ── Icon sprite ─────────────────────────────────────────────────── -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="i-github" viewBox="0 0 24 24">
        <path fill="currentColor" d="M12 .5A11.5 11.5 0 0 0 .5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56v-2.1c-3.2.7-3.87-1.36-3.87-1.36-.52-1.33-1.28-1.68-1.28-1.68-1.04-.72.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.76 2.7 1.25 3.35.96.1-.75.4-1.25.72-1.54-2.55-.29-5.24-1.28-5.24-5.68 0-1.26.45-2.29 1.19-3.09-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.78 0c2.2-1.49 3.16-1.18 3.16-1.18.63 1.59.24 2.76.12 3.05.74.8 1.18 1.83 1.18 3.09 0 4.41-2.69 5.38-5.26 5.66.41.36.78 1.06.78 2.15v3.19c0 .31.21.68.8.56A11.5 11.5 0 0 0 23.5 12 11.5 11.5 0 0 0 12 .5Z"/>
    </symbol>
    <symbol id="i-linkedin" viewBox="0 0 24 24">
        <path fill="currentColor" d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.47-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29ZM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12ZM7.12 20.45H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.55C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.72C24 .77 23.2 0 22.22 0Z"/>
    </symbol>
    <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
    </symbol>
    <symbol id="i-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
    </symbol>
    <symbol id="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
    </symbol>
    <symbol id="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
    </symbol>
    <symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M4 6h16M4 12h16M4 18h16"/>
    </symbol>
    <symbol id="i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M18 6 6 18M6 6l12 12"/>
    </symbol>
    <symbol id="i-arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 5v14m-7-7 7 7 7-7"/>
    </symbol>
    <symbol id="i-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>
    </symbol>
    <symbol id="i-star" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"/>
    </symbol>
    <symbol id="i-fork" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><path d="M18 9v2a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V9"/><path d="M12 12v3"/>
    </symbol>
    <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
    </symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/>
    </symbol>
    <symbol id="i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
    </symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
    </symbol>
    <symbol id="i-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 9v4m0 4h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
    </symbol>
    <symbol id="i-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>
    </symbol>
    <symbol id="i-folder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>
    </symbol>
    <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
    </symbol>
    <symbol id="i-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>
    </symbol>
</svg>

<!-- ── Ambient background ──────────────────────────────────────────── -->
<div class="bg-decor" aria-hidden="true">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="grid-overlay"></div>
</div>

<!-- ── Navigation ──────────────────────────────────────────────────── -->
<header class="nav" id="nav">
    <div class="container nav-inner">
        <a href="#home" class="nav-logo" aria-label="Home">
            <span class="logo-mark"><?= $e($initials) ?></span>
            <span class="logo-text"><?= $e($name) ?></span>
        </a>

        <nav class="nav-links" id="nav-links" aria-label="Primary">
            <a href="#home" class="nav-link active">Home</a>
            <a href="#about" class="nav-link">About</a>
            <a href="#projects" class="nav-link">Projects</a>
            <a href="#contact" class="nav-link">Contact</a>
        </nav>

        <div class="nav-actions">
            <button class="icon-btn" id="theme-toggle" type="button" aria-label="Toggle color theme">
                <svg class="icon icon-sun"><use href="#i-sun"/></svg>
                <svg class="icon icon-moon"><use href="#i-moon"/></svg>
            </button>
            <?php if ($resume !== ''): ?>
                <a class="btn btn-ghost btn-sm nav-resume" href="<?= $e($resume) ?>" target="_blank" rel="noopener">
                    <svg class="icon"><use href="#i-download"/></svg> Resume
                </a>
            <?php endif; ?>
            <a class="btn btn-primary btn-sm nav-hire" href="#contact">Message me</a>
            <button class="icon-btn nav-burger" id="nav-burger" type="button" aria-label="Open menu" aria-expanded="false">
                <svg class="icon icon-burger"><use href="#i-menu"/></svg>
                <svg class="icon icon-x"><use href="#i-close"/></svg>
            </button>
        </div>
    </div>
</header>

<main>

    <!-- ── Hero ────────────────────────────────────────────────────── -->
    <section class="hero" id="home">
        <div class="container hero-inner">
            <?php if (!empty($status['available'])): ?>
                <span class="pill status-pill reveal">
                    <span class="pulse-dot"></span>
                    <?= $e($status['note'] ?? 'Available for work') ?>
                </span>
            <?php endif; ?>

            <h1 class="hero-title reveal">
                Hi, I'm <span class="grad-text" id="hero-name"><?= $e($name) ?></span>
            </h1>

            <p class="hero-role reveal" aria-live="polite">
                <span class="hero-role-label">I am a&nbsp;</span><span id="typewriter" class="type-text"><?= $e($roles[0] ?? $role) ?></span><span class="caret" aria-hidden="true"></span>
            </p>

            <p class="hero-tagline reveal"><?= $e($tagline) ?></p>

            <div class="hero-cta reveal">
                <a href="#projects" class="btn btn-primary btn-lg">
                    <svg class="icon"><use href="#i-folder"/></svg> View my work
                </a>
                <a href="<?= $e($githubUrl) ?>" class="btn btn-ghost btn-lg" id="hero-github" target="_blank" rel="noopener">
                    <svg class="icon"><use href="#i-github"/></svg> GitHub
                </a>
                <?php if ($resume !== ''): ?>
                    <a href="<?= $e($resume) ?>" class="btn btn-ghost btn-lg" target="_blank" rel="noopener">
                        <svg class="icon"><use href="#i-download"/></svg> Résumé
                    </a>
                <?php endif; ?>
            </div>

            <div class="hero-stats reveal" id="hero-stats">
                <div class="stat-card">
                    <span class="stat-value" data-stat="repos">—</span>
                    <span class="stat-label">Repositories</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value" data-stat="stars">—</span>
                    <span class="stat-label">Total stars</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value" data-stat="followers">—</span>
                    <span class="stat-label">Followers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value" data-stat="following">—</span>
                    <span class="stat-label">Following</span>
                </div>
            </div>

            <a href="#about" class="scroll-hint reveal" aria-label="Scroll to About section">
                <svg class="icon"><use href="#i-arrow-down"/></svg>
            </a>
        </div>
    </section>

    <!-- ── About ───────────────────────────────────────────────────── -->
    <section class="section" id="about">
        <div class="container">
            <header class="section-head reveal">
                <span class="section-eyebrow">Get to know me</span>
                <h2 class="section-title">About <span class="grad-text">Me</span></h2>
            </header>

            <div class="about-grid">
                <aside class="about-visual card reveal">
                    <div class="avatar-ring">
                        <?php if ($avatar !== ''): ?>
                            <img class="avatar-img" src="<?= $e($avatar) ?>" alt="<?= $e($name) ?>" width="180" height="180">
                        <?php else: ?>
                            <div class="avatar-initials" aria-hidden="true"><?= $e($initials) ?></div>
                        <?php endif; ?>
                    </div>

                    <h3 class="about-visual-name"><?= $e($name) ?></h3>
                    <p class="about-visual-role"><?= $e($role) ?></p>

                    <ul class="fact-list">
                        <li>
                            <svg class="icon"><use href="#i-pin"/></svg>
                            <span><?= $e($location !== '' ? $location : 'Your City, Country') ?></span>
                        </li>
                        <li>
                            <svg class="icon"><use href="#i-mail"/></svg>
                            <a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a>
                        </li>
                        <?php
                        $education = (string) c('education', '');
                        if ($education !== '' && !str_starts_with($education, 'Your Degree,')):
                        ?>
                        <li>
                            <svg class="icon"><use href="#i-book"/></svg>
                            <span><?= $e($education) ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="social-row">
                        <a class="social-btn" href="<?= $e($githubUrl) ?>" target="_blank" rel="noopener" aria-label="GitHub">
                            <svg class="icon"><use href="#i-github"/></svg>
                        </a>
                        <?php if ($linkedin !== ''): ?>
                            <a class="social-btn" href="<?= $e($linkedin) ?>" target="_blank" rel="noopener" aria-label="LinkedIn">
                                <svg class="icon"><use href="#i-linkedin"/></svg>
                            </a>
                        <?php endif; ?>
                        <a class="social-btn" href="mailto:<?= $e($email) ?>" aria-label="Email">
                            <svg class="icon"><use href="#i-mail"/></svg>
                        </a>
                        <?php if ($website !== ''): ?>
                            <a class="social-btn" href="<?= $e($website) ?>" target="_blank" rel="noopener" aria-label="Website">
                                <svg class="icon"><use href="#i-external"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </aside>

                <div class="about-content reveal">
                    <p class="about-lead">
                        I'm a <strong><?= $e($role) ?></strong> who enjoys the entire stack — from
                        designing relational schemas and building resilient APIs, to crafting
                        interfaces that feel fast and intuitive.
                    </p>
                    <p class="about-bio"><?= $e($bio) ?></p>

                    <div class="skills" id="skills">
                        <?php foreach ($skills as $group => $items): if (empty($items)) continue; ?>
                            <div class="skill-group">
                                <h4 class="skill-group-title"><?= $e($group) ?></h4>
                                <div class="chip-row">
                                    <?php foreach ((array) $items as $skill): ?>
                                        <span class="chip"><?= $e($skill) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php
                        $interests = array_values(array_filter(array_map('strval', (array) c('interests', []))));
                        if ($interests !== []):
                        ?>
                        <div class="skill-group">
                            <h4 class="skill-group-title">Interests</h4>
                            <div class="chip-row">
                                <?php foreach ($interests as $interest): ?>
                                    <span class="chip"><?= $e($interest) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Projects (live from GitHub) ─────────────────────────────── -->
    <section class="section" id="projects">
        <div class="container">
            <header class="section-head reveal">
                <span class="section-eyebrow">Synced live from GitHub</span>
                <h2 class="section-title">Featured <span class="grad-text">Projects</span></h2>
                <p class="section-sub">
                    These repositories are fetched from the GitHub API by my PHP backend and
                    cached in MySQL — refresh to pull the latest.
                </p>
            </header>

            <div class="projects-toolbar reveal">
                <div class="search-box">
                    <svg class="icon"><use href="#i-search"/></svg>
                    <input type="search" id="project-search" placeholder="Search projects…" aria-label="Search projects" autocomplete="off">
                </div>
                <div class="chip-row lang-filters" id="lang-filters" role="group" aria-label="Filter by language"></div>
                <button class="btn btn-ghost btn-sm" id="sync-btn" type="button">
                    <svg class="icon"><use href="#i-refresh"/></svg>
                    <span id="sync-label">Sync now</span>
                </button>
            </div>

            <p class="projects-meta reveal" id="projects-meta">Loading projects from GitHub…</p>

            <div class="projects-grid" id="projects-grid" aria-live="polite" aria-busy="true">
                <!-- Skeleton loaders (replaced by JS) -->
                <div class="project-card skeleton"></div>
                <div class="project-card skeleton"></div>
                <div class="project-card skeleton"></div>
                <div class="project-card skeleton"></div>
                <div class="project-card skeleton"></div>
                <div class="project-card skeleton"></div>
            </div>

            <!-- <div class="empty-state" id="projects-empty" hidden>
                <svg class="icon empty-icon"><use href="#i-github"/></svg>
                <p id="projects-empty-text">No projects to show yet.</p>
            </div>
        </div> -->
    </section>

    <!-- ── Contact ─────────────────────────────────────────────────── -->
    <section class="section" id="contact">
        <div class="container">
            <header class="section-head reveal">
                <span class="section-eyebrow">Let's work together</span>
                <h2 class="section-title">Get in <span class="grad-text">Touch</span></h2>
                <p class="section-sub">
                    Have a project in mind, a question, or just want to say hi?
                    Drop a message — it's stored securely in my portfolio's MySQL database.
                </p>
            </header>

            <div class="contact-grid">
                <form class="contact-form card reveal" id="contact-form" novalidate>
                    <div class="form-row">
                        <div class="field">
                            <label for="cf-name">Name <span class="req">*</span></label>
                            <input type="text" id="cf-name" name="name" placeholder="Name" autocomplete="name" required>
                            <span class="field-error" data-error-for="name"></span>
                        </div>
                        <div class="field">
                            <label for="cf-email">Email <span class="req">*</span></label>
                            <input type="email" id="cf-email" name="email" placeholder="Email" autocomplete="email" required>
                            <span class="field-error" data-error-for="email"></span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="cf-subject">Subject</label>
                        <input type="text" id="cf-subject" name="subject" placeholder="Project inquiry">
                        <span class="field-error" data-error-for="subject"></span>
                    </div>

                    <div class="field">
                        <label for="cf-message">Message <span class="req">*</span></label>
                        <textarea id="cf-message" name="message" rows="6" placeholder="Tell me about your idea…" required></textarea>
                        <span class="field-error" data-error-for="message"></span>
                    </div>

                    <button class="btn btn-primary btn-lg contact-submit" id="contact-submit" type="submit">
                        <svg class="icon"><use href="#i-send"/></svg>
                        <span>Send message</span>
                    </button>
                </form>

                <aside class="contact-aside reveal">
                    <div class="card contact-info-card">
                        <h3>Contact details</h3>
                        <ul class="fact-list">
                            <li>
                                <svg class="icon"><use href="#i-mail"/></svg>
                                <a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a>
                            </li>
                            <li>
                                <svg class="icon"><use href="#i-pin"/></svg>
                                <span><?= $e($location !== '' ? $location : 'Your City, Country') ?></span>
                            </li>
                        </ul>

                        <div class="social-row">
                            <a class="social-btn" href="<?= $e($githubUrl) ?>" target="_blank" rel="noopener" aria-label="GitHub">
                                <svg class="icon"><use href="#i-github"/></svg>
                            </a>
                            <?php if ($linkedin !== ''): ?>
                                <a class="social-btn" href="<?= $e($linkedin) ?>" target="_blank" rel="noopener" aria-label="LinkedIn">
                                    <svg class="icon"><use href="#i-linkedin"/></svg>
                                </a>
                            <?php endif; ?>
                            <a class="social-btn" href="mailto:<?= $e($email) ?>" aria-label="Email">
                                <svg class="icon"><use href="#i-mail"/></svg>
                            </a>
                        </div>
                    </div>

                    <div class="card tech-badge">
                        <svg class="icon"><use href="#i-check"/></svg>
                        <div>
                            <strong>Powered by a real stack</strong>
                            <p>PHP 8 · MySQL · REST API · GitHub API · Vanilla JS</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<!-- ── Footer ──────────────────────────────────────────────────────── -->
<footer class="footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <span class="logo-mark sm"><?= $e($initials) ?></span>
            <span>© <span id="year"><?= date('Y') ?></span> <?= $e($name) ?>. All rights reserved.</span>
        </div>
        <p class="footer-note">Built with <strong>PHP</strong>, <strong>MySQL</strong> &amp; the <strong>GitHub API</strong></p>
        <a href="#home" class="footer-top">Back to top ↑</a>
    </div>
</footer>

<!-- ── Chat assistant ──────────────────────────────────────────── -->
<div class="chat-root">
    <section class="chat-panel" id="chat-panel" role="dialog" aria-label="Portfolio assistant" aria-hidden="true">
        <header class="chat-head">
            <div class="chat-head-avatar" aria-hidden="true">✦</div>
            <div class="chat-head-text">
                <strong>Portfolio Assistant</strong>
                <span class="chat-status"><span class="pulse-dot"></span> Online — ask me anything</span>
            </div>
            <div class="chat-head-actions">
                <button class="icon-btn" id="chat-clear" type="button" aria-label="Clear conversation" title="Clear conversation">
                    <svg class="icon"><use href="#i-refresh"/></svg>
                </button>
                <button class="icon-btn" id="chat-close" type="button" aria-label="Close chat" title="Close chat">
                    <svg class="icon"><use href="#i-close"/></svg>
                </button>
            </div>
        </header>

        <div class="chat-messages" id="chat-messages" aria-live="polite"></div>

        <div class="chat-suggestions" id="chat-suggestions"></div>

        <form class="chat-input-row" id="chat-form">
            <input type="text" id="chat-input" placeholder="Ask about skills, projects, contact…" maxlength="1000" autocomplete="off" aria-label="Message the assistant">
            <button class="chat-send" type="submit" id="chat-send" aria-label="Send message">
                <svg class="icon"><use href="#i-send"/></svg>
            </button>
        </form>
    </section>

    <button class="chat-fab" id="chat-fab" type="button" aria-label="Open chat assistant" aria-expanded="false">
        <svg class="icon"><use href="#i-chat"/></svg>
        <span class="chat-fab-dot"></span>
    </button>
</div>

<!-- Toasts -->
<div class="toast-wrap" id="toasts" role="status" aria-live="polite"></div>

<!-- Data for the JS layer -->
<script id="roles-data" type="application/json"><?= json_encode(array_values($roles), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script src="assets/js/app.js" defer></script>
<script src="assets/js/chat.js" defer></script>
</body>
</html>
