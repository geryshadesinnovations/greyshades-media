-- =====================================================================
-- Greyshades Innovations - Media Platform Database Schema
-- MySQL 8.x / MariaDB 10.5+
-- Run this once via phpMyAdmin or mysql CLI to create the schema.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `greyshades_media`
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `greyshades_media`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- ROLES & PERMISSIONS (RBAC)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(64) NOT NULL,
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NOT NULL,
    `name` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`,`permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(120) NOT NULL,
    `email`           VARCHAR(190) NOT NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `role_id`         INT UNSIGNED NOT NULL,
    `can_graphics`    TINYINT(1) NOT NULL DEFAULT 0,
    `can_events`      TINYINT(1) NOT NULL DEFAULT 0,
    `can_upload`      TINYINT(1) NOT NULL DEFAULT 0,
    `can_edit`        TINYINT(1) NOT NULL DEFAULT 0,
    `can_delete`      TINYINT(1) NOT NULL DEFAULT 0,
    `can_download`    TINYINT(1) NOT NULL DEFAULT 0,
    `can_manage_users` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at`   DATETIME NULL,
    `last_login_ip`   VARCHAR(45) NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_active` (`is_active`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SECTIONS  (Greyshades Graphics / Greyshades Events)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
    `id`   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sections_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CATEGORIES  (nested via parent_id)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section_id` TINYINT UNSIGNED NOT NULL,
    `parent_id`  INT UNSIGNED NULL,
    `name`       VARCHAR(150) NOT NULL,
    `slug`       VARCHAR(180) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_section_slug` (`section_id`,`slug`),
    KEY `idx_categories_parent` (`parent_id`),
    KEY `idx_categories_section` (`section_id`),
    CONSTRAINT `fk_categories_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`),
    CONSTRAINT `fk_categories_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- OCCASIONS  (themes / metadata, NOT folders)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `occasion_groups`;
CREATE TABLE `occasion_groups` (
    `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_occasion_groups_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `occasions`;
CREATE TABLE `occasions` (
    `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` INT UNSIGNED NOT NULL,
    `name`     VARCHAR(190) NOT NULL,
    `slug`     VARCHAR(190) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_occasions_slug` (`slug`),
    KEY `idx_occasions_group` (`group_id`),
    CONSTRAINT `fk_occasions_group` FOREIGN KEY (`group_id`) REFERENCES `occasion_groups`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TAGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MEDIA  (single source of truth - never duplicated physically)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`          CHAR(36) NOT NULL,
    `section_id`    TINYINT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NULL,
    `keywords`      TEXT NULL,
    `media_type`    ENUM('video','image','pdf','ppt','other') NOT NULL,
    `mime_type`     VARCHAR(120) NOT NULL,
    `file_path`     VARCHAR(500) NOT NULL COMMENT 'Relative path inside private storage',
    `file_size`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `file_hash`     CHAR(64) NULL COMMENT 'SHA-256 to detect duplicates',
    `thumbnail_path` VARCHAR(500) NULL,
    `preview_path`   VARCHAR(500) NULL,
    `hls_master`     VARCHAR(500) NULL,
    `duration_sec`   INT UNSIGNED NULL,
    `width`          INT UNSIGNED NULL,
    `height`         INT UNSIGNED NULL,
    `is_downloadable` TINYINT(1) NOT NULL DEFAULT 0,
    `download_expiry` DATETIME NULL,
    `is_featured`    TINYINT(1) NOT NULL DEFAULT 0,
    `is_pinned`      TINYINT(1) NOT NULL DEFAULT 0,
    `view_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `processing_status` ENUM('pending','processing','ready','failed') NOT NULL DEFAULT 'ready',
    `uploaded_by`    INT UNSIGNED NOT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_media_uuid` (`uuid`),
    UNIQUE KEY `uq_media_hash` (`file_hash`),
    KEY `idx_media_section`   (`section_id`),
    KEY `idx_media_type`      (`media_type`),
    KEY `idx_media_uploader`  (`uploaded_by`),
    KEY `idx_media_created`   (`created_at`),
    KEY `idx_media_featured`  (`is_featured`),
    FULLTEXT KEY `ft_media_search` (`title`,`description`,`keywords`),
    CONSTRAINT `fk_media_section`   FOREIGN KEY (`section_id`)  REFERENCES `sections`(`id`),
    CONSTRAINT `fk_media_uploader`  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- JUNCTION TABLES  (one media -> many categories / occasions / tags)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `media_categories`;
CREATE TABLE `media_categories` (
    `media_id`    BIGINT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`media_id`,`category_id`),
    KEY `idx_mc_category` (`category_id`),
    CONSTRAINT `fk_mc_media`    FOREIGN KEY (`media_id`)    REFERENCES `media`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_mc_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `media_occasions`;
CREATE TABLE `media_occasions` (
    `media_id`    BIGINT UNSIGNED NOT NULL,
    `occasion_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`media_id`,`occasion_id`),
    KEY `idx_mo_occasion` (`occasion_id`),
    CONSTRAINT `fk_mo_media`    FOREIGN KEY (`media_id`)    REFERENCES `media`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_mo_occasion` FOREIGN KEY (`occasion_id`) REFERENCES `occasions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `media_tags`;
CREATE TABLE `media_tags` (
    `media_id` BIGINT UNSIGNED NOT NULL,
    `tag_id`   INT UNSIGNED NOT NULL,
    PRIMARY KEY (`media_id`,`tag_id`),
    KEY `idx_mt_tag` (`tag_id`),
    CONSTRAINT `fk_mt_media` FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mt_tag`   FOREIGN KEY (`tag_id`)   REFERENCES `tags`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-file download whitelist (overrides global is_downloadable)
DROP TABLE IF EXISTS `media_download_grants`;
CREATE TABLE `media_download_grants` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `media_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `expires_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mdg` (`media_id`,`user_id`),
    CONSTRAINT `fk_mdg_media` FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mdg_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ACTIVITY / AUDIT LOG
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(64) NOT NULL,
    `entity_type` VARCHAR(64) NULL,
    `entity_id`  BIGINT UNSIGNED NULL,
    `meta`       JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `session_id` VARCHAR(128) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_user`    (`user_id`),
    KEY `idx_al_action`  (`action`),
    KEY `idx_al_entity`  (`entity_type`,`entity_id`),
    KEY `idx_al_created` (`created_at`),
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `download_logs`;
CREATE TABLE `download_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `media_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `os`         VARCHAR(64) NULL,
    `browser`    VARCHAR(64) NULL,
    `session_id` VARCHAR(128) NULL,
    `bytes_sent` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dl_media` (`media_id`),
    KEY `idx_dl_user`  (`user_id`),
    KEY `idx_dl_created` (`created_at`),
    CONSTRAINT `fk_dl_media` FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dl_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `view_logs`;
CREATE TABLE `view_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `media_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `session_id` VARCHAR(128) NULL,
    `duration_sec` INT UNSIGNED NULL,
    `percent_completed` TINYINT UNSIGNED NULL,
    `quality`    VARCHAR(16) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vl_media` (`media_id`),
    KEY `idx_vl_user`  (`user_id`),
    KEY `idx_vl_created` (`created_at`),
    CONSTRAINT `fk_vl_media` FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vl_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SESSIONS  (live tracking of who is online and what they are viewing)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
    `id`              VARCHAR(128) NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `ip_address`      VARCHAR(45) NULL,
    `user_agent`      VARCHAR(500) NULL,
    `device_fingerprint` VARCHAR(128) NULL,
    `current_media_id` BIGINT UNSIGNED NULL,
    `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_us_user` (`user_id`),
    KEY `idx_us_active` (`is_active`),
    CONSTRAINT `fk_us_user`  FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_us_media` FOREIGN KEY (`current_media_id`) REFERENCES `media`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stream_tokens`;
CREATE TABLE `stream_tokens` (
    `token`      CHAR(64) NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `media_id`   BIGINT UNSIGNED NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token`),
    KEY `idx_st_user_media` (`user_id`,`media_id`),
    KEY `idx_st_expires` (`expires_at`),
    CONSTRAINT `fk_st_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_st_media` FOREIGN KEY (`media_id`) REFERENCES `media`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `failed_logins`;
CREATE TABLE `failed_logins` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(190) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fl_email` (`email`),
    KEY `idx_fl_ip` (`ip_address`),
    KEY `idx_fl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
