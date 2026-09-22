<?php
declare(strict_types=1);

/**
 * GET /api/profile.php
 *
 * Returns the About/hero content from config.php, plus live GitHub
 * stats (public repos, followers, following) cached in MySQL.
 */

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$github = new GithubClient();
$cache  = new GithubCache();
$ghUser = [];
$notice = null;

if ($github->isConfigured()) {
    try {
        $ghUser = $cache->remember('github_user', 3600, fn () => $github->fetchUser()) ?? [];
    } catch (Throwable $e) {
        $notice = $e->getMessage();
        $ghUser = [];
    }
}

// Live-ish stats: fall back to the cached project table when GitHub is unreachable.
$stats = [
    'public_repos' => isset($ghUser['public_repos']) ? (int) $ghUser['public_repos'] : null,
    'followers'    => isset($ghUser['followers']) ? (int) $ghUser['followers'] : null,
    'following'    => isset($ghUser['following']) ? (int) $ghUser['following'] : null,
    'stars'        => null,
];

try {
    $store = new ProjectStore(Database::conn());
    $stats['stars'] = $store->count() > 0 ? $store->totalStars() : null;
    if ($stats['public_repos'] === null && $store->count() > 0) {
        $stats['public_repos'] = $store->count();
    }
} catch (Throwable) {
    // stats stay null — the frontend handles that gracefully
}

$username = trim((string) app_config('github_username', ''));
$configured = $github->isConfigured();

$avatar = trim((string) app_config('avatar_url', ''));
if ($avatar === '' && !empty($ghUser['avatar_url'])) {
    $avatar = (string) $ghUser['avatar_url'];
}

$profile = [
    'name'            => (string) app_config('name', 'Your Name'),
    'initials'        => strtoupper((string) app_config('initials', 'YN')),
    'role'            => (string) app_config('role', 'Developer'),
    'tagline'         => (string) app_config('tagline', ''),
    'bio'             => (string) app_config('bio', ''),
    'email'           => (string) app_config('email', ''),
    'location'        => (string) app_config('location', ''),
    'avatar_url'      => $avatar,
    'resume_url'      => (string) app_config('resume_url', ''),
    'roles'           => array_values((array) app_config('roles', [])),
    'skills'          => (array) app_config('skills', []),
    'status'          => (array) app_config('status', ['available' => false, 'note' => '']),
    'github_username' => $username,
    'github_url'      => $configured ? 'https://github.com/' . rawurlencode($username) : '',
    'github_configured' => $configured,
    'linkedin_url'    => (string) app_config('linkedin_url', ''),
    'website_url'     => (string) app_config('website_url', ''),
    'stats'           => $stats,
    'github_name'     => (string) ($ghUser['name'] ?? ''),
    'github_bio'      => (string) ($ghUser['bio'] ?? ''),
    'notice'          => $notice,
];

api_response([
    'ok'   => true,
    'data' => $profile,
]);
