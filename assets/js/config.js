/* =====================================================================
   Portfolio — config.js
   Every personal detail lives here. Edit this file to make the site yours,
   then commit + push — GitHub Pages serves the change instantly.
   ===================================================================== */

window.PORTFOLIO_CONFIG = {

    /* ---------------------------------------------------------- WHO YOU ARE
       About section + hero. */
    name: 'Aerol Justine M. Abrenica',
    initials: 'AJ',
    role: 'Software Developer',
    tagline: 'I design and build fast, accessible, full-stack web applications — from database schema to pixel-perfect UI.',
    bio: "Hello! I'm Aerol Justine Abrenica, a passionate developer who loves turning ideas into real, working products. " +
         'I enjoy the full web-development cycle: architecting databases and making front-ends that feel effortless to use. ' +
         'This portfolio is a static site hosted on GitHub Pages — it pulls my projects straight from the GitHub API and ' +
         "caches them in your browser, so there's no server to maintain.",

    email: 'abrenicaaeroljustine@gmail.com',
    location: 'Lipa City, Batangas, Philippines',
    avatar_url: '',       // optional: URL of your photo. Leave '' to show initials.
    resume_url: '',       // optional: link to your resume/CV (PDF or Google Drive)

    // Shown in the About card and answered by the chatbot.
    education: '3rd Year, BSCS student — Lipa City Colleges',

    // Hobby / interest chips shown in About and used by the chatbot.
    interests: ['Open Source', 'UI/UX Design', 'Problem Solving'],

    // Open-to-work pill in the hero.
    status: {
        available: true,
        note: 'Available for internships & freelance work',
    },

    // Words cycled by the hero typewriter.
    roles: ['Software Developer'],

    // Skills shown in the About section, grouped by category.
    skills: {
        'Languages': ['PHP', 'JavaScript', 'HTML', 'CSS'],
        'Frameworks & Tools': ['Bootstrap', 'jQuery'],
        'Databases': ['MySQL'],
        'Other Skills': ['Git & GitHub', 'UI/UX'],
    },

    /* ---------------------------------------------------------- SOCIALS & LINKS */
    // Your real GitHub username — projects are pulled live from here.
    github_username: 'aeroljustineabrenica',

    linkedin_url: 'https://www.linkedin.com/in/aerol-justine-abrenica-003523435/',
    website_url: '',       // optional personal website

    /* ---------------------------------------------------------- GITHUB PROJECT SYNC
       Behaviour for the live project grid (runs in your browser). */
    github: {
        // Max number of repositories shown.
        max_repos: 20,
        // Skip forked repositories so only your original work is shown.
        exclude_forks: true,
        // Only show projects with at least this many stars (0 = show all).
        min_stars: 0,
        // Seconds before the site automatically re-fetches from GitHub.
        // 3600 = once every hour. "Sync now ↻" always forces a refresh.
        sync_ttl: 3600,
    },

    /* ---------------------------------------------------------- CONTACT FORM
       GitHub Pages has no backend, so the contact form opens the visitor's
       email app (mailto:). If you'd rather receive messages through a form
       service like Formspree, paste its endpoint below and set active to true. */
    contact: {
        active: false,
        endpoint: '',       // e.g. https://formspree.io/f/yourFormId
    },
};