-- =====================================================================
-- Migration: 2026-05-20  (run AFTER 2026_05_20_hybrid_subcategories.sql)
--
-- Two backfills that make existing media show up correctly under the
-- new sidebar category filters in the Media Dashboard:
--
--   1. Map every (media_id, occasion_id) pair to the matching Hybrid
--      leaf category (categories.slug = 'hyb-' + occasion slug). This
--      means old uploads that only attached an Occasion (e.g. "World TB
--      Day") now also live under the matching Hybrid > Medical &
--      Health > World TB Day node — exactly where users now expect to
--      find them.
--
--   2. Walk up the category tree and copy each media's parent / root
--      category into media_categories, so filtering by a top-level
--      ("Gimmick", "Art", "Hybrid", "Events") returns every descendant
--      file even when only a leaf was originally ticked.
--
-- Both blocks use INSERT IGNORE so they are idempotent and safe to
-- replay.
-- =====================================================================
USE `greyshades_media`;

-- ---------- 1. Bridge Occasions into Hybrid categories ----------
INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT mo.media_id, c.id
FROM `media_occasions` mo
JOIN `occasions` o   ON o.id = mo.occasion_id
JOIN `categories` c  ON c.slug = CONCAT('hyb-', o.slug)
JOIN `sections`   s  ON s.id = c.section_id AND s.code = 'graphics';

-- ---------- 2. Walk up the category tree and attach ancestors ----------
-- We do this iteratively so it works on MySQL 5.7 (no recursive CTE
-- required). Six rounds covers any realistic tree depth.
INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT DISTINCT mc.media_id, c.parent_id
FROM `media_categories` mc
JOIN `categories` c ON c.id = mc.category_id
WHERE c.parent_id IS NOT NULL;

INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT DISTINCT mc.media_id, c.parent_id
FROM `media_categories` mc
JOIN `categories` c ON c.id = mc.category_id
WHERE c.parent_id IS NOT NULL;

INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT DISTINCT mc.media_id, c.parent_id
FROM `media_categories` mc
JOIN `categories` c ON c.id = mc.category_id
WHERE c.parent_id IS NOT NULL;

INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT DISTINCT mc.media_id, c.parent_id
FROM `media_categories` mc
JOIN `categories` c ON c.id = mc.category_id
WHERE c.parent_id IS NOT NULL;

INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT DISTINCT mc.media_id, c.parent_id
FROM `media_categories` mc
JOIN `categories` c ON c.id = mc.category_id
WHERE c.parent_id IS NOT NULL;

INSERT IGNORE INTO `media_categories` (`media_id`, `category_id`)
SELECT DISTINCT mc.media_id, c.parent_id
FROM `media_categories` mc
JOIN `categories` c ON c.id = mc.category_id
WHERE c.parent_id IS NOT NULL;
