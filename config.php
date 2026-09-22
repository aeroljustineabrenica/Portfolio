<?php
/**
 * ================================================================
 *  PORTFOLIO CONFIGURATION — edit this file to make it yours
 * ================================================================
 *  Every personal detail lives here. Change the placeholder text,
 *  add your real GitHub username, and the whole site updates.
 */

return [

    /* ------------------------------------------------------------------
     *  WHO YOU ARE (About section + hero)
     * ------------------------------------------------------------------ */
    'name'            => 'Aerol Justine M. Abrenica',
    'initials'        => 'AJ',
    'role'            => 'Software Developer',
    'tagline'         => "I design and build fast, accessible, full-stack web applications — from database schema to pixel-perfect UI.",
    'bio'             => "Hello! I'm Aerol Justine Abrenica, a passionate developer who loves turning ideas into real, working products. " .
                         "I enjoy the full cycle of web development: architecting databases, and making " .
                         "front-ends that feel effortless to use. This portfolio is powered by PHP + MySQL, pulls my projects " .
                         "straight from the GitHub API, and caches them in a database — a true full-stack experience.",

    'email'           => 'abrenicaaeroljustine@gmail.com',
    'location'        => 'Lipa City, Batangas, Philippines',
    'avatar_url'      => '',                       // optional: URL of your photo. Leave '' to show initials.
    'resume_url'      => '',                       // optional: link to your resume/CV (PDF or Google Drive)

    // Shown in the About card and answered by the chatbot.
    'education'       => '3rd Year, BSCS student — Lipa City Colleges',

    // Hobby / interest chips shown in About and used by the chatbot.
    'interests'       => ['Open Source', 'UI/UX Design', 'Problem Solving',],

    'status'          => [
        'available' => true,                       // shows the green "Open to work" pill in the hero
        'note'      => 'Available for internships & freelance work',
    ],

    // Words that cycle in the hero's typewriter effect.
    'roles'           => [
        'Software Developer',
    ],

    // Skills shown in the About section, grouped by category.
    'skills'          => [
        'Languages'      => ['PHP', 'JavaScript', 'HTML', 'CSS'],
        'Frameworks & Tools' => ['Bootstrap', 'jQuery'],
        'Databases'      => ['MySQL'],
        'Other Skills'   => ['Git & GitHub', 'UI/UX'],
    ],

    /* ------------------------------------------------------------------
     *  SOCIALS & LINKS
     * ------------------------------------------------------------------ */
    // Your real GitHub username — projects are pulled live from here.
    'github_username' => 'aeroljustineabrenica',

    'linkedin_url'    => 'https://www.linkedin.com/in/aerol-justine-abrenica-003523435/',
    'website_url'     => '',                       // optional personal website

    /* ------------------------------------------------------------------
     *  GITHUB PROJECT SYNC BEHAVIOUR
     * ------------------------------------------------------------------ */
    'github'          => [

        /**
         * Max number of repositories fetched from GitHub.
         * Set higher (up to 100 per page) to show all your repos.
         */
        'max_repos'    => 20,

        /**
         * Skip forked repositories so only your original work is shown.
         */
        'exclude_forks' => true,

        /**
         * Only show projects with at least this many stars (0 = show all).
         */
        'min_stars'    => 0,

        /**
         * Seconds before the site automatically re-syncs from GitHub.
         * 3600 = once every hour. The "Sync now ↻" button always forces a refresh.
         */
        'sync_ttl'     => 3600,

        /**
         * OPTIONAL — GitHub Personal Access Token (classic, no scopes needed).
         * Unauthenticated requests are limited to 60/hour per IP. Adding a
         * token raises that to 5,000/hour. Create one at:
         *   https://github.com/settings/tokens
         * Leave empty if you don't need it.
         */
        'token'        => '',
    ],

    /* ------------------------------------------------------------------
     *  CHATBOT — answers questions in the corner of your portfolio.
     *
     *  Out of the box the bot answers factually from YOUR data (skills,
     *  projects cached in MySQL, education, contact info…). When a
     *  question doesn't match an intent AND an OpenAI key is set below,
     *  it gets forwarded to the AI model instead.
     * ------------------------------------------------------------------ */
    'ai'              => [
        /**
         * OPTIONAL — OpenAI API key (https://platform.openai.com/api-keys).
         * Leave empty for 100% free local answers; add a key to let the bot
         * answer creative/open-ended questions with real AI.
         */
        'api_key'     => '',

        /** Model used for free-form questions. gpt-4o-mini is cheap & fast. */
        'model'       => 'gpt-4o-mini',

        /** Max tokens per AI answer (controls cost). */
        'max_tokens'  => 400,

        /**
         * Safety cap: max AI calls per IP per hour. Set 0 to disable the
         * limit (not recommended — AI calls cost money).
         */
        'max_per_hour' => 20,
    ],

    /* ------------------------------------------------------------------
     *  DATABASE (XAMPP defaults work out of the box)
     * ------------------------------------------------------------------ */
    'db'              => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'portfolio_db',
        'user'    => 'root',
        'pass'    => '',                            // XAMPP default root password is empty
        'charset' => 'utf8mb4',
    ],
];