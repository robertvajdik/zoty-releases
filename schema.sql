-- Zoty Release Notes – MySQL / MariaDB schema
-- (the app also creates these tables itself when 'auto_migrate' => true)

CREATE TABLE IF NOT EXISTS `changelog_versions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `version`      VARCHAR(50)  NOT NULL,
  `release_date` DATE         NOT NULL,
  `title`        VARCHAR(255) NOT NULL DEFAULT '',
  `summary`      TEXT         NOT NULL,
  `published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL,
  `updated_at`   DATETIME     NOT NULL,
  UNIQUE KEY `uq_version` (`version`),
  KEY `idx_release_date` (`release_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_entries` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `version_id` INT UNSIGNED NOT NULL,
  `type`       VARCHAR(20)  NOT NULL COMMENT 'new | improved | fixed | security',
  `module`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'one or more modules, "Audit, Reporty"',
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT         NOT NULL,
  `published`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL,
  `updated_at` DATETIME     NOT NULL,
  KEY `idx_version_id` (`version_id`),
  KEY `idx_published` (`published`),
  CONSTRAINT `fk_changelog_entries_version` FOREIGN KEY (`version_id`) REFERENCES `changelog_versions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(80)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_subscribers` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`        VARCHAR(190) NOT NULL,
  `token`        CHAR(32)     NOT NULL,
  `confirmed`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL,
  `confirmed_at` DATETIME     NULL,
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_mailings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `version_id` INT UNSIGNED NOT NULL,
  `recipients` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL,
  KEY `idx_version_id` (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_modules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(80)  NOT NULL,
  `created_at` DATETIME     NOT NULL,
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT IGNORE INTO `changelog_modules` (`name`, `created_at`) VALUES
  ('DataLink', NOW()), ('Audit', NOW()), ('Reporty', NOW());
