<?php
declare(strict_types=1);

/**
 * Shared API bootstrap: config, autoloading, JSON headers,
 * error handling and automatic database migration.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$_config = require __DIR__ . '/../config.php';
$GLOBALS['APP_CONFIG'] = is_array($_config) ? $_config : [];

/**
 * Dot-notation config access: app_config('github.token', '')
 */
function app_config(?string $key = null, mixed $default = null): mixed
{
    $cfg = $GLOBALS['APP_CONFIG'] ?? [];
    if ($key === null || $key === '') {
        return $cfg;
    }

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

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

mb_internal_encoding('UTF-8');

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

try {
    Migrator::run();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Send a JSON response and terminate. */
function api_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read JSON body (with form-encoded fallback). */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');

    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return is_array($_POST) ? $_POST : [];
}
