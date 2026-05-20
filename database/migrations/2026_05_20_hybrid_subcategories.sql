-- =====================================================================
-- Migration: 2026-05-20
-- Move Hybrid leaves into the categories tree.
--
-- Hybrid previously had two empty parent rows ("Medical & Health Awareness
-- Days" / "National & Cultural Festivals") and the actual day/festival
-- names only existed in `occasions`. Customers want the days/festivals to
-- BE the subcategories of Hybrid, so this migration inserts each occasion
-- as a nested category under the matching Hybrid parent and adds a third
-- Hybrid parent ("Environment & Wellness") for completeness.
--
-- Safe to run multiple times: every row is INSERT IGNORE on the unique
-- (section_id, slug) key, so it simply skips anything that already exists.
-- =====================================================================
USE `greyshades_media`;

SET @sec_g = (SELECT id FROM sections WHERE code = 'graphics');
SET @cat_hybrid = (SELECT id FROM categories WHERE section_id = @sec_g AND slug = 'hybrid');

-- Make sure the third Hybrid parent exists (Environment & Wellness).
INSERT IGNORE INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g, @cat_hybrid, 'Environment & Wellness', 'hybrid-environment-wellness', 3);

SET @cat_hybrid_med = (SELECT id FROM categories WHERE section_id = @sec_g AND slug = 'hybrid-medical-days');
SET @cat_hybrid_nat = (SELECT id FROM categories WHERE section_id = @sec_g AND slug = 'hybrid-national-festivals');
SET @cat_hybrid_env = (SELECT id FROM categories WHERE section_id = @sec_g AND slug = 'hybrid-environment-wellness');

-- ---------- Hybrid > Medical & Health Awareness Days ----------
INSERT IGNORE INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_hybrid_med,'World No Tobacco Day','hyb-world-no-tobacco-day',1),
  (@sec_g,@cat_hybrid_med,'World Bronchiectasis Day','hyb-world-bronchiectasis-day',2),
  (@sec_g,@cat_hybrid_med,'World Lung Day','hyb-world-lung-day',3),
  (@sec_g,@cat_hybrid_med,'World Pneumonia Day','hyb-world-pneumonia-day',4),
  (@sec_g,@cat_hybrid_med,'World TB Day','hyb-world-tb-day',5),
  (@sec_g,@cat_hybrid_med,'World Asthma Day','hyb-world-asthma-day',6),
  (@sec_g,@cat_hybrid_med,'World COPD Day','hyb-world-copd-day',7),
  (@sec_g,@cat_hybrid_med,'World Hypertension Day','hyb-world-hypertension-day',8),
  (@sec_g,@cat_hybrid_med,'World Heart Day','hyb-world-heart-day',9),
  (@sec_g,@cat_hybrid_med,'World Hearing Day','hyb-world-hearing-day',10),
  (@sec_g,@cat_hybrid_med,'World Sight Day','hyb-world-sight-day',11),
  (@sec_g,@cat_hybrid_med,'World Liver Day','hyb-world-liver-day',12),
  (@sec_g,@cat_hybrid_med,'International NASH Day','hyb-international-nash-day',13),
  (@sec_g,@cat_hybrid_med,'World Hepatitis Day','hyb-world-hepatitis-day',14),
  (@sec_g,@cat_hybrid_med,'Liver Awareness Month','hyb-liver-awareness-month',15),
  (@sec_g,@cat_hybrid_med,'World Diabetes Day','hyb-world-diabetes-day',16),
  (@sec_g,@cat_hybrid_med,'World Alzheimer''s Day','hyb-world-alzheimers-day',17),
  (@sec_g,@cat_hybrid_med,'World Mental Health Day','hyb-world-mental-health-day',18),
  (@sec_g,@cat_hybrid_med,'World Glaucoma Week','hyb-world-glaucoma-week',19),
  (@sec_g,@cat_hybrid_med,'World Kidney Day','hyb-world-kidney-day',20),
  (@sec_g,@cat_hybrid_med,'World Leprosy Day','hyb-world-leprosy-day',21),
  (@sec_g,@cat_hybrid_med,'World Melanoma Day','hyb-world-melanoma-day',22),
  (@sec_g,@cat_hybrid_med,'World Vitiligo Day','hyb-world-vitiligo-day',23),
  (@sec_g,@cat_hybrid_med,'World Psoriasis Day','hyb-world-psoriasis-day',24),
  (@sec_g,@cat_hybrid_med,'Endometriosis Day','hyb-endometriosis-day',25),
  (@sec_g,@cat_hybrid_med,'World PCOS Day','hyb-world-pcos-day',26),
  (@sec_g,@cat_hybrid_med,'World Autism Awareness Day','hyb-world-autism-awareness-day',27),
  (@sec_g,@cat_hybrid_med,'Children''s Day','hyb-childrens-day',28),
  (@sec_g,@cat_hybrid_med,'World Spine Day','hyb-world-spine-day',29),
  (@sec_g,@cat_hybrid_med,'World Trauma Day','hyb-world-trauma-day',30),
  (@sec_g,@cat_hybrid_med,'World Osteoporosis Day','hyb-world-osteoporosis-day',31),
  (@sec_g,@cat_hybrid_med,'World Arthritis Day','hyb-world-arthritis-day',32),
  (@sec_g,@cat_hybrid_med,'World Cancer Day','hyb-world-cancer-day',33),
  (@sec_g,@cat_hybrid_med,'World Lung Cancer Day','hyb-world-lung-cancer-day',34),
  (@sec_g,@cat_hybrid_med,'World Hemophilia Day','hyb-world-hemophilia-day',35),
  (@sec_g,@cat_hybrid_med,'World Health Day','hyb-world-health-day',36),
  (@sec_g,@cat_hybrid_med,'World Thalassemia Day','hyb-world-thalassemia-day',37),
  (@sec_g,@cat_hybrid_med,'World Allergy Day','hyb-world-allergy-day',38),
  (@sec_g,@cat_hybrid_med,'Nutritional Awareness Week','hyb-nutritional-awareness-week',39),
  (@sec_g,@cat_hybrid_med,'Antimicrobial Awareness Week','hyb-antimicrobial-awareness-week',40),
  (@sec_g,@cat_hybrid_med,'World AIDS Day','hyb-world-aids-day',41);

-- ---------- Hybrid > Environment & Wellness ----------
INSERT IGNORE INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_hybrid_env,'World Environment Day','hyb-world-environment-day',1),
  (@sec_g,@cat_hybrid_env,'World Yoga Day','hyb-world-yoga-day',2),
  (@sec_g,@cat_hybrid_env,'Doctors'' Day','hyb-doctors-day',3);

-- ---------- Hybrid > National & Cultural Festivals ----------
INSERT IGNORE INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_hybrid_nat,'Republic Day','hyb-republic-day',1),
  (@sec_g,@cat_hybrid_nat,'Valentine''s Day','hyb-valentines-day',2),
  (@sec_g,@cat_hybrid_nat,'Women''s Day','hyb-womens-day',3),
  (@sec_g,@cat_hybrid_nat,'Ram Navami','hyb-ram-navami',4),
  (@sec_g,@cat_hybrid_nat,'Mother''s Day','hyb-mothers-day',5),
  (@sec_g,@cat_hybrid_nat,'Music Day','hyb-music-day',6),
  (@sec_g,@cat_hybrid_nat,'Independence Day','hyb-independence-day',7),
  (@sec_g,@cat_hybrid_nat,'Teachers'' Day','hyb-teachers-day',8),
  (@sec_g,@cat_hybrid_nat,'Gandhi Jayanti','hyb-gandhi-jayanti',9),
  (@sec_g,@cat_hybrid_nat,'Diwali','hyb-diwali',10),
  (@sec_g,@cat_hybrid_nat,'Christmas','hyb-christmas',11),
  (@sec_g,@cat_hybrid_nat,'New Year','hyb-new-year',12),
  (@sec_g,@cat_hybrid_nat,'Holi','hyb-holi',13),
  (@sec_g,@cat_hybrid_nat,'Ambedkar Jayanti','hyb-ambedkar-jayanti',14),
  (@sec_g,@cat_hybrid_nat,'Raksha Bandhan','hyb-raksha-bandhan',15),
  (@sec_g,@cat_hybrid_nat,'Daughters Day','hyb-daughters-day',16),
  (@sec_g,@cat_hybrid_nat,'Navratri','hyb-navratri',17),
  (@sec_g,@cat_hybrid_nat,'Bhai Dooj','hyb-bhai-dooj',18),
  (@sec_g,@cat_hybrid_nat,'Makar Sankranti','hyb-makar-sankranti',19),
  (@sec_g,@cat_hybrid_nat,'Gudi Padwa','hyb-gudi-padwa',20),
  (@sec_g,@cat_hybrid_nat,'Friendship Day','hyb-friendship-day',21),
  (@sec_g,@cat_hybrid_nat,'Ganesh Chaturthi','hyb-ganesh-chaturthi',22),
  (@sec_g,@cat_hybrid_nat,'Dussehra','hyb-dussehra',23);
