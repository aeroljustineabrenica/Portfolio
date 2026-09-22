<?php
declare(strict_types=1);

/**
 * POST /api/chatbot.php
 * Body: { "message": string, "history"?: [{role, content}...], "session_id"?: string }
 * Returns: { ok, data: { reply, source: local|ai, suggestions: string[] } }
 *
 * Every exchange is logged to the `chat_logs` table (used both as an audit
 * trail and to enforce the per-IP AI usage cap).
 */

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_response(['ok' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$in = read_json_body();

$message = trim((string) ($in['message'] ?? ''));

if ($message === '') {
    api_response(['ok' => false, 'error' => 'Please type a question first.', 'fields' => ['message' => 'Required']], 422);
}

if (mb_strlen($message) > 3000) {
    api_response(['ok' => false, 'error' => 'Question is too long (max 3000 characters).'], 422);
}

$history = $in['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

$history = array_values(array_filter(
    array_slice($history, -10),
    static fn (mixed $turn): bool =>
        is_array($turn)
        && in_array((string) ($turn['role'] ?? ''), ['user', 'assistant'], true)
        && is_string($turn['content'] ?? '')
        && trim($turn['content']) !== ''
));

$bot = new ChatBot();
$result = $bot->answer($message, $history);

// Best-effort audit log; never let a logging failure break the chat.
try {
    $pdo = Database::conn();
    $sessionId = mb_substr(trim((string) ($in['session_id'] ?? '')), 0, 64);
    $stmt = $pdo->prepare(
        'INSERT INTO chat_logs (session_id, ip, message, reply, source) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $sessionId !== '' ? $sessionId : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr($message, 0, 4000),
        mb_substr((string) $result['reply'], 0, 8000),
        (string) $result['source'],
    ]);
} catch (Throwable) {
    // noop
}

api_response([
    'ok'   => true,
    'data' => $result,
]);