-- =====================================================================
--  Portfolio — MySQL schema (reference copy)
--  NOTE: You do NOT need to run this manually. The API auto-creates
--  the database and tables on first request (see src/Migrator.php).
--  Import this only if you prefer setting the DB up yourself:
--      C:\xampp\mysql\bin\mysql.exe -u root < sql\schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `portfolio_db`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `portfolio_db`;

-- Repositories cached from the GitHub API
CREATE TABLE IF NOT EXISTS `projects` (
    `id`               BIGINT UNSIGNED NOT NULL,           -- GitHub repo id
    `name`             VARCHAR(200)  NOT NULL,
    `full_name`        VARCHAR(250)  NOT NULL,
    `description`      TEXT          NULL,
    `homepage`         VARCHAR(500)  NULL,                 -- live demo URL
    `html_url`         VARCHAR(500)  NOT NULL,
    `language`         VARCHAR(60)   NULL,
    `stargazers_count` INT UNSIGNED  NOT NULL DEFAULT 0,
    `forks_count`      INT UNSIGNED  NOT NULL DEFAULT 0,
    `topics_json`      TEXT          NULL,
    `is_fork`          TINYINT(1)    NOT NULL DEFAULT 0,
    `is_archived`      TINYINT(1)    NOT NULL DEFAULT 0,
    `pushed_at`        DATETIME      NULL,
    `repo_created_at`  DATETIME      NULL,
    `synced_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_full_name` (`full_name`),
    KEY `idx_stars` (`stargazers_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached GitHub user profile (followers, public repos, avatar…)
CREATE TABLE IF NOT EXISTS `github_cache` (
    `cache_key`  VARCHAR(120) NOT NULL,
    `payload`    TEXT         NOT NULL,
    `fetched_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages submitted through the contact form
CREATE TABLE IF NOT EXISTS `messages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `subject`    VARCHAR(200) NOT NULL DEFAULT '',
    `message`    TEXT         NOT NULL,
    `ip`         VARCHAR(45)  NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chatbot conversation log (also powers the AI rate limiter)
CREATE TABLE IF NOT EXISTS `chat_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64)  NULL,
    `ip`         VARCHAR(45)  NULL,
    `message`    TEXT         NOT NULL,
    `reply`      TEXT         NOT NULL,
    `source`     VARCHAR(10)  NOT NULL DEFAULT 'local',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_created` (`ip`, `created_at`),
    KEY `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
