<?php
declare(strict_types=1);

/**
 * Reads/writes the cached `projects` table. Syncing pulls raw repo
 * objects from the GitHub API and upserts them; reads serve the UI.
 */
final class ProjectStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, full_name, description, homepage, html_url, language,
                    stargazers_count, forks_count, topics_json, is_fork, is_archived,
                    pushed_at, synced_at
             FROM projects
             ORDER BY stargazers_count DESC, pushed_at DESC'
        )->fetchAll();

        return array_map([$this, 'mapRow'], $rows);
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    }

    public function totalStars(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(SUM(stargazers_count), 0) FROM projects')->fetchColumn();
    }

    public function lastSyncedAt(): ?string
    {
        $val = $this->pdo->query('SELECT MAX(synced_at) FROM projects')->fetchColumn();
        return $val ? (string) $val : null;
    }

    public function isStale(int $ttlSeconds): bool
    {
        $last = $this->lastSyncedAt();
        if ($last === null) {
            return true;
        }
        return (time() - strtotime($last)) > $ttlSeconds;
    }

    /**
     * Replace the cached project set with fresh GitHub data.
     *
     * @param array<int, array<string, mixed>> $repos raw repo objects from GitHub
     */
    public function sync(array $repos): int
    {
        $sql = 'INSERT INTO projects
                    (id, name, full_name, description, homepage, html_url, language,
                     stargazers_count, forks_count, topics_json, is_fork, is_archived,
                     pushed_at, repo_created_at, synced_at)
                VALUES
                    (:id, :name, :full_name, :description, :homepage, :html_url, :language,
                     :stars, :forks, :topics, :is_fork, :is_archived,
                     :pushed_at, :repo_created_at, :synced_at)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    homepage = VALUES(homepage),
                    html_url = VALUES(html_url),
                    language = VALUES(language),
                    stargazers_count = VALUES(stargazers_count),
                    forks_count = VALUES(forks_count),
                    topics_json = VALUES(topics_json),
                    is_fork = VALUES(is_fork),
                    is_archived = VALUES(is_archived),
                    pushed_at = VALUES(pushed_at),
                    synced_at = VALUES(synced_at)';

        $stmt = $this->pdo->prepare($sql);
        $now  = date('Y-m-d H:i:s');
        $kept = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($repos as $repo) {
                $stmt->execute([
                    ':id'            => (int) $repo['id'],
                    ':name'          => mb_substr((string) $repo['name'], 0, 200),
                    ':full_name'     => mb_substr((string) $repo['full_name'], 0, 250),
                    ':description'   => $repo['description'] ?? null,
                    ':homepage'      => $repo['homepage'] ?: null,
                    ':html_url'      => (string) $repo['html_url'],
                    ':language'      => $repo['language'] ?? null,
                    ':stars'         => (int) ($repo['stargazers_count'] ?? 0),
                    ':forks'         => (int) ($repo['forks_count'] ?? 0),
                    ':topics'        => json_encode($repo['topics'] ?? [], JSON_UNESCAPED_SLASHES),
                    ':is_fork'       => !empty($repo['fork']) ? 1 : 0,
                    ':is_archived'   => !empty($repo['archived']) ? 1 : 0,
                    ':pushed_at'     => $this->dateOrNull($repo['pushed_at'] ?? null),
                    ':repo_created_at' => $this->dateOrNull($repo['created_at'] ?? null),
                    ':synced_at'     => $now,
                ]);
                $kept++;
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $kept;
    }

    private function mapRow(array $row): array
    {
        $topics = json_decode($row['topics_json'] ?? '[]', true);

        return [
            'id'          => (int) $row['id'],
            'name'        => $row['name'],
            'full_name'   => $row['full_name'],
            'description' => $row['description'] ?: 'No description provided.',
            'homepage'    => $row['homepage'],
            'html_url'    => $row['html_url'],
            'language'    => $row['language'],
            'stars'       => (int) $row['stargazers_count'],
            'forks'       => (int) $row['forks_count'],
            'topics'      => is_array($topics) ? $topics : [],
            'is_fork'     => (bool) $row['is_fork'],
            'is_archived' => (bool) $row['is_archived'],
            'pushed_at'   => $row['pushed_at'],
            'synced_at'   => $row['synced_at'],
        ];
    }

    private function dateOrNull(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        $ts = strtotime($iso);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
