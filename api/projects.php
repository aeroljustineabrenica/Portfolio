<?php
declare(strict_types=1);

/**
 * GET /api/projects.php            → serve projects from the MySQL cache
 * GET /api/projects.php?refresh=1  → force a fresh sync from GitHub first
 *
 * Response meta tells the UI exactly where the data came from
 * (live GitHub vs cache) and surfaces friendly warnings.
 */

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$store           = new ProjectStore(Database::conn());
$github          = new GithubClient();
$refreshRequested = isset($_GET['refresh']);

$meta = [
    'configured'        => $github->isConfigured(),
    'username'          => $github->username(),
    'source'            => 'cache',
    'total'             => 0,
    'synced_at'         => null,
    'refresh_requested' => $refreshRequested,
    'warning'           => null,
    'rate'              => ['remaining' => null, 'limit' => null],
];

// Not configured yet → return an empty list with setup instructions.
if (!$github->isConfigured()) {
    $meta['message'] = 'Set your GitHub username in config.php → github_username to display your projects.';
    api_response(['ok' => true, 'data' => [], 'meta' => $meta]);
}

$ttl    = (int) app_config('github.sync_ttl', 3600);
$needsSync = $refreshRequested || $store->count() === 0 || $store->isStale($ttl);

if ($needsSync) {
    try {
        $repos = $github->fetchRepos();
        $store->sync($repos);
        $meta['source']    = 'github';
        $meta['rate']      = $github->rate();
        $meta['warning']   = null;
    } catch (Throwable $e) {
        // Fall back to whatever is cached so the section still renders.
        $meta['warning'] = $e->getMessage();
        $meta['source']  = $store->count() > 0 ? 'cache' : 'none';
        $meta['rate']    = $github->rate();
    }
}

$projects = $store->all();

$meta['total']     = count($projects);
$meta['synced_at'] = $store->lastSyncedAt();

api_response([
    'ok'   => true,
    'data' => $projects,
    'meta' => $meta,
]);
