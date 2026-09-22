<?php
declare(strict_types=1);

/**
 * Auto-migrator: creates the database and every table it needs on
 * first run. Idempotent — safe to run on every request, so the
 * portfolio works the moment you drop it into htdocs.
 */
final class Migrator
{
    public static function run(): void
    {
        $db = app_config('db');

        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);

        try {
            $pdo = new PDO($serverDsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'MySQL is not reachable. Start MySQL from the XAMPP Control Panel, then reload. (' .
                $e->getMessage() . ')'
            );
        }

        $name = str_replace('`', '', $db['name']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS projects (
                id                BIGINT UNSIGNED NOT NULL,
                name              VARCHAR(200)  NOT NULL,
                full_name         VARCHAR(250)  NOT NULL,
                description       TEXT          NULL,
                homepage          VARCHAR(500)  NULL,
                html_url          VARCHAR(500)  NOT NULL,
                language          VARCHAR(60)   NULL,
                stargazers_count  INT UNSIGNED  NOT NULL DEFAULT 0,
                forks_count       INT UNSIGNED  NOT NULL DEFAULT 0,
                topics_json       TEXT          NULL,
                is_fork           TINYINT(1)    NOT NULL DEFAULT 0,
                is_archived       TINYINT(1)    NOT NULL DEFAULT 0,
                pushed_at         DATETIME      NULL,
                repo_created_at   DATETIME      NULL,
                synced_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_full_name (full_name),
                KEY idx_stars (stargazers_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS github_cache (
                cache_key  VARCHAR(120) NOT NULL,
                payload    TEXT         NOT NULL,
                fetched_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (cache_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS messages (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name       VARCHAR(100) NOT NULL,
                email      VARCHAR(150) NOT NULL,
                subject    VARCHAR(200) NOT NULL DEFAULT '',
                message    TEXT         NOT NULL,
                ip         VARCHAR(45)  NULL,
                is_read    TINYINT(1)   NOT NULL DEFAULT 0,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_logs (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                session_id VARCHAR(64)  NULL,
                ip         VARCHAR(45)  NULL,
                message    TEXT         NOT NULL,
                reply      TEXT         NOT NULL,
                source     VARCHAR(10)  NOT NULL DEFAULT 'local',
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ip_created (ip, created_at),
                KEY idx_session (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
