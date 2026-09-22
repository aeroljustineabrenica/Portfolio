<?php
declare(strict_types=1);

/**
 * Thin GitHub REST API client (no SDK, no composer — just cURL).
 * Tracks rate-limit headers so we can show friendly warnings.
 */
final class GithubClient
{
    private const BASE = 'https://api.github.com';

    private string $username;
    /** @var string[] */
    private array $headers;
    /** @var array{remaining:?int, limit:?int, reset:?int} */
    private array $rate = ['remaining' => null, 'limit' => null, 'reset' => null];

    public function __construct()
    {
        $this->username = (string) app_config('github_username', '');
        $token          = (string) app_config('github.token', '');

        $this->headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: PHP-Portfolio/1.0',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        if ($token !== '') {
            $this->headers[] = 'Authorization: Bearer ' . $token;
        }
    }

    public function username(): string
    {
        return $this->username;
    }

    public function isConfigured(): bool
    {
        $u = strtolower(trim($this->username));
        return $u !== '' && $u !== 'your-github-username';
    }

    /** Fetch the public user profile (name, avatar, followers, …). */
    public function fetchUser(): ?array
    {
        $data = $this->get('/users/' . rawurlencode($this->username));
        return is_array($data) ? $data : null;
    }

    /** Fetch repositories, filtered by config (forks, min stars, archived). */
    public function fetchRepos(): array
    {
        $perPage      = min(max((int) app_config('github.max_repos', 20), 1), 100);
        $excludeForks = (bool) app_config('github.exclude_forks', true);
        $minStars     = (int) app_config('github.min_stars', 0);

        $repos = [];
        $page  = 1;
        $maxPages = 3;

        do {
            $query = http_build_query([
                'per_page'  => $perPage,
                'page'      => $page,
                'sort'      => 'pushed',
                'direction' => 'desc',
            ]);

            $batch = $this->get("/users/{$this->username}/repos?{$query}");

            if (!is_array($batch) || $batch === [] || isset($batch['message'])) {
                break;
            }

            foreach ($batch as $repo) {
                if (!is_array($repo) || !isset($repo['id'])) {
                    continue;
                }
                $isFork   = (bool) ($repo['fork'] ?? false);
                $stars    = (int) ($repo['stargazers_count'] ?? 0);
                $archived = (bool) ($repo['archived'] ?? false);

                if ($excludeForks && $isFork)   continue;
                if ($stars < $minStars)         continue;
                if ($archived)                  continue;

                $repos[] = $repo;
            }

            $page++;
        } while (count($batch) === $perPage && $page <= $maxPages);

        return $repos;
    }

    /** @return array{remaining:?int, limit:?int, reset:?int} */
    public function rate(): array
    {
        return $this->rate;
    }

    /** @return mixed decoded JSON or null */
    private function get(string $path): mixed
    {
        $ch = curl_init(self::BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $this->headers,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hdrSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Could not reach GitHub: ' . ($error ?: 'network error'));
        }

        $headerBlock = substr((string) $response, 0, $hdrSize);
        $body        = substr((string) $response, $hdrSize);

        $this->captureRate($headerBlock);

        if ($status === 404) {
            throw new RuntimeException(
                "GitHub user \"{$this->username}\" was not found — check github_username in config.php."
            );
        }

        if ($status === 403 || $status === 429) {
            $reset = $this->rate['reset'] ? date('H:i', $this->rate['reset']) : 'later';
            throw new RuntimeException(
                "GitHub API rate limit reached (resets at {$reset}). " .
                'Add a personal access token in config.php → github.token to raise the limit to 5,000/hour.'
            );
        }

        if ($status >= 400) {
            throw new RuntimeException("GitHub API error (HTTP {$status}).");
        }

        return json_decode($body, true);
    }

    private function captureRate(string $headers): void
    {
        foreach (explode("\r\n", $headers) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = strtolower(trim($parts[0]));
            $val = trim($parts[1]);

            if ($key === 'x-ratelimit-remaining') {
                $this->rate['remaining'] = (int) $val;
            } elseif ($key === 'x-ratelimit-limit') {
                $this->rate['limit'] = (int) $val;
            } elseif ($key === 'x-ratelimit-reset') {
                $this->rate['reset'] = (int) $val;
            }
        }
    }
}
