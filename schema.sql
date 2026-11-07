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

-- ---------------------------------------------------------------- customer support (portal/)

CREATE TABLE IF NOT EXISTS `changelog_customers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(160) NOT NULL,
  `code`       VARCHAR(10)  NOT NULL COMMENT 'ticket key prefix, e.g. ACME -> ACME-12',
  `created_at` DATETIME     NOT NULL,
  UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_customer_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id`   INT UNSIGNED NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `name`          VARCHAR(160) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `lang`          VARCHAR(5)   NOT NULL DEFAULT 'cs',
  `created_at`    DATETIME     NOT NULL,
  `last_login`    DATETIME     NULL,
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_customer` (`customer_id`),
  CONSTRAINT `fk_changelog_customer_users_customer` FOREIGN KEY (`customer_id`) REFERENCES `changelog_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_tickets` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_id`     INT UNSIGNED NOT NULL,
  `number`          INT UNSIGNED NOT NULL COMMENT 'per customer: ACME-1, ACME-2, ...',
  `user_id`         INT UNSIGNED NULL,
  `type`            VARCHAR(20)  NOT NULL COMMENT 'incident | question | feature',
  `product`         VARCHAR(80)  NOT NULL DEFAULT '',
  `product_version` VARCHAR(50)  NOT NULL DEFAULT '',
  `os`              VARCHAR(80)  NOT NULL DEFAULT '',
  `summary`         VARCHAR(255) NOT NULL,
  `description`     MEDIUMTEXT   NOT NULL,
  `severity`        VARCHAR(10)  NOT NULL DEFAULT 'medium' COMMENT 'crisis | high | medium | low',
  `status`          VARCHAR(20)  NOT NULL DEFAULT 'open' COMMENT 'open | in_progress | waiting | resolved | closed',
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  UNIQUE KEY `uq_customer_number` (`customer_id`, `number`),
  KEY `idx_status` (`status`),
  KEY `idx_updated` (`updated_at`),
  CONSTRAINT `fk_changelog_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `changelog_customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_ticket_comments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL COMMENT 'NULL = support staff',
  `staff_name` VARCHAR(160) NOT NULL DEFAULT '',
  `body`       MEDIUMTEXT   NOT NULL,
  `event`      VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'e.g. status:open>waiting (activity line)',
  `created_at` DATETIME     NOT NULL,
  KEY `idx_ticket` (`ticket_id`),
  CONSTRAINT `fk_changelog_ticket_comments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `changelog_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `changelog_ticket_files` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`  INT UNSIGNED NOT NULL,
  `comment_id` INT UNSIGNED NULL,
  `name`       VARCHAR(255) NOT NULL,
  `path`       VARCHAR(255) NOT NULL COMMENT 'file name in data/tickets/<ticket_id>/',
  `mime`       VARCHAR(100) NOT NULL DEFAULT '',
  `size`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL,
  KEY `idx_ticket` (`ticket_id`),
  CONSTRAINT `fk_changelog_ticket_files_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `changelog_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------- manuals (admin/help, portal/help)

CREATE TABLE IF NOT EXISTS `changelog_docs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug`       VARCHAR(40)  NOT NULL COMMENT 'admin | portal',
  `lang`       VARCHAR(5)   NOT NULL,
  `body`       MEDIUMTEXT   NOT NULL COMMENT 'Markdown; created from MANUAL.md / docs/*.md on first view',
  `updated_at` DATETIME     NOT NULL,
  `updated_by` VARCHAR(160) NOT NULL DEFAULT '',
  UNIQUE KEY `uq_slug_lang` (`slug`, `lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
