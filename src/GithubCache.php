<?php
declare(strict_types=1);

/**
 * Small MySQL-backed cache with a TTL, used for the GitHub user
 * profile so we don't hammer the API on every page view.
 */
final class GithubCache
{
    public function remember(string $key, int $ttlSeconds, callable $loader): ?array
    {
        $pdo = Database::conn();

        $stmt = $pdo->prepare('SELECT payload, fetched_at FROM github_cache WHERE cache_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if ($row) {
            $age = time() - strtotime($row['fetched_at']);
            if ($age < $ttlSeconds) {
                $decoded = json_decode($row['payload'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $data = $loader();
        if (!is_array($data)) {
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO github_cache (cache_key, payload, fetched_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = VALUES(fetched_at)'
        );
        $stmt->execute([$key, json_encode($data, JSON_UNESCAPED_SLASHES), date('Y-m-d H:i:s')]);

        return $data;
    }

    public function lastSyncedAt(): ?string
    {
        $pdo = Database::conn();
        $stmt = $pdo->prepare('SELECT fetched_at FROM github_cache WHERE cache_key = ?');
        $stmt->execute(['github_user']);

        $row = $stmt->fetch();
        return $row ? $row['fetched_at'] : null;
    }
}
