<?php
declare(strict_types=1);

/**
 * POST /api/contact.php
 * Accepts JSON { name, email, subject?, message } and stores it in MySQL.
 */

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_response(['ok' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$in = read_json_body();

$name    = trim((string) ($in['name'] ?? ''));
$email   = trim((string) ($in['email'] ?? ''));
$subject = trim((string) ($in['subject'] ?? ''));
$message = trim((string) ($in['message'] ?? ''));

$errors = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors['name'] = 'Please enter your name (2–100 characters).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (mb_strlen($subject) > 200) {
    $errors['subject'] = 'Subject is too long (max 200 characters).';
}

if (mb_strlen($message) < 5 || mb_strlen($message) > 5000) {
    $errors['message'] = 'Message must be between 5 and 5000 characters.';
}

if ($errors !== []) {
    api_response([
        'ok'     => false,
        'error'  => 'Please fix the highlighted fields.',
        'fields' => $errors,
    ], 422);
}

try {
    $pdo = Database::conn();

    // Lightweight anti-abuse check: max 3 messages per IP per hour.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM messages
         WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $stmt->execute([$ip]);

    if ((int) $stmt->fetchColumn() >= 3) {
        api_response([
            'ok'    => false,
            'error' => 'You have sent several messages recently — please wait a little before writing again.',
        ], 429);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO messages (name, email, subject, message, ip) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $subject, $message, $ip]);
} catch (Throwable $e) {
    api_response([
        'ok'    => false,
        'error' => 'Your message could not be saved right now. Please try again shortly.',
    ], 500);
}

api_response([
    'ok'      => true,
    'message' => "Thanks, {$name}! Your message landed safely in my inbox — I'll get back to you soon.",
]);
