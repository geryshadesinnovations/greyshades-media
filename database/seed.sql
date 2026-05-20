-- =====================================================================
-- Greyshades Innovations - Seed Data
-- Run AFTER schema.sql.
-- Default super admin:  email: admin@greyshades.local  password: Admin@12345
-- (Hash is bcrypt of "Admin@12345" - change immediately after first login.)
-- =====================================================================
USE `greyshades_media`;

-- ---------- ROLES ----------
INSERT INTO `roles` (`code`,`name`) VALUES
  ('super_admin','Super Admin'),
  ('graphics_user','Graphics User'),
  ('events_user','Events User'),
  ('combined_user','Combined User');

-- ---------- PERMISSIONS ----------
INSERT INTO `permissions` (`code`,`description`) VALUES
  ('media.view','View media'),
  ('media.upload','Upload new media'),
  ('media.edit','Edit media metadata'),
  ('media.delete','Delete media'),
  ('media.download','Download media'),
  ('media.feature','Feature/pin content'),
  ('user.manage','Manage users'),
  ('category.manage','Manage categories & occasions'),
  ('analytics.view','View analytics dashboards'),
  ('section.graphics','Access Greyshades Graphics'),
  ('section.events','Access Greyshades Events');

-- Super Admin gets everything
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p WHERE r.code='super_admin';

-- Graphics user
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
ON p.code IN ('media.view','section.graphics')
WHERE r.code='graphics_user';

-- Events user
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
ON p.code IN ('media.view','section.events')
WHERE r.code='events_user';

-- Combined user
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
ON p.code IN ('media.view','section.graphics','section.events')
WHERE r.code='combined_user';

-- ---------- DEFAULT SUPER ADMIN ----------
-- Bcrypt hash for "Admin@12345"
INSERT INTO `users`
  (`name`,`email`,`password_hash`,`role_id`,`can_graphics`,`can_events`,
   `can_upload`,`can_edit`,`can_delete`,`can_download`,`can_manage_users`,`is_active`)
VALUES
  ('Super Admin','admin@greyshades.local',
   '$2y$10$HIYrGJM3Rsyu.6ezAUPgQu5zFaNFLWJxwJLxI9rRwg8viKq96IWIS',
   (SELECT id FROM `roles` WHERE code='super_admin'),
   1,1,1,1,1,1,1,1);

-- ---------- SECTIONS ----------
INSERT INTO `sections` (`code`,`name`) VALUES
  ('graphics','Greyshades Graphics'),
  ('events','Greyshades Events');

-- ---------- CATEGORIES (Greyshades Graphics) ----------
SET @sec_g = (SELECT id FROM sections WHERE code='graphics');
SET @sec_e = (SELECT id FROM sections WHERE code='events');

INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g, NULL, 'Gimmick','gimmick',1),
  (@sec_g, NULL, 'Art','art',2),
  (@sec_g, NULL, 'Hybrid','hybrid',3);

SET @cat_gimmick = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='gimmick');
SET @cat_art     = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='art');
SET @cat_hybrid  = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='hybrid');

-- Gimmick subcategories
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_gimmick,'Pop-up','pop-up',1),
  (@sec_g,@cat_gimmick,'Double pop-up','double-pop-up',2),
  (@sec_g,@cat_gimmick,'Pull-up gimmick','pull-up-gimmick',3),
  (@sec_g,@cat_gimmick,'Detailer','detailer',4),
  (@sec_g,@cat_gimmick,'Launcher','launcher',5),
  (@sec_g,@cat_gimmick,'Sample catch cover','sample-catch-cover',6),
  (@sec_g,@cat_gimmick,'LBLS','lbls-gimmick',7),
  (@sec_g,@cat_gimmick,'Music gimmick','music-gimmick',8),
  (@sec_g,@cat_gimmick,'Greeting gimmick','greeting-gimmick',9),
  (@sec_g,@cat_gimmick,'Photo frame','photo-frame',10),
  (@sec_g,@cat_gimmick,'Before & after gimmick','before-after-gimmick',11),
  (@sec_g,@cat_gimmick,'Box gimmick','box-gimmick',12),
  (@sec_g,@cat_gimmick,'Circle rotating gimmick','circle-rotating-gimmick',13),
  (@sec_g,@cat_gimmick,'Dangler','dangler',14),
  (@sec_g,@cat_gimmick,'Sample dispenser','sample-dispenser',15),
  (@sec_g,@cat_gimmick,'Magical gimmick','magical-gimmick',16),
  (@sec_g,@cat_gimmick,'Table tops','table-tops',17);

-- Art subcategories
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_art,'LBLs','lbls',1),
  (@sec_g,@cat_art,'Visual aids (VA)','visual-aids',2),
  (@sec_g,@cat_art,'Videos','videos',3),
  (@sec_g,@cat_art,'Digital games','digital-games',4),
  (@sec_g,@cat_art,'Digital activities','digital-activities',5),
  (@sec_g,@cat_art,'Photoshoots','photoshoots',6),
  (@sec_g,@cat_art,'Logo design','logo-design',7),
  (@sec_g,@cat_art,'Brand campaigns','brand-campaigns',8);

SET @cat_lbls   = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='lbls');
SET @cat_videos = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='videos');

-- LBLs sub-types
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_lbls,'Scientific','lbls-scientific',1),
  (@sec_g,@cat_lbls,'Poster','lbls-poster',2),
  (@sec_g,@cat_lbls,'Banner','lbls-banner',3),
  (@sec_g,@cat_lbls,'Stickers','lbls-stickers',4);

-- Videos nested children
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_videos,'Invitation Videos','invitation-videos',1),
  (@sec_g,@cat_videos,'Chroma shoot','chroma-shoot',2),
  (@sec_g,@cat_videos,'Augmented Reality Videos','ar-videos',3),
  (@sec_g,@cat_videos,'3D Videos','3d-videos',4),
  (@sec_g,@cat_videos,'Motivational Videos','motivational-videos',5),
  (@sec_g,@cat_videos,'Marketing Presentation','marketing-presentation',6),
  (@sec_g,@cat_videos,'Language Translations','language-translations',7),
  (@sec_g,@cat_videos,'Jinggle Videos','jinggle-videos',8),
  (@sec_g,@cat_videos,'Web Link Videos','web-link-videos',9),
  (@sec_g,@cat_videos,'Scientific Concepts','scientific-concepts',10),
  (@sec_g,@cat_videos,'Product Shoot','product-shoot',11),
  (@sec_g,@cat_videos,'Photoshoot','video-photoshoot',12),
  (@sec_g,@cat_videos,'Days Videos','days-videos',13),
  (@sec_g,@cat_videos,'Cancer Awareness','cancer-awareness',14),
  (@sec_g,@cat_videos,'Conference Videos','conference-videos',15),
  (@sec_g,@cat_videos,'Whiteboard Videos','whiteboard-videos',16),
  (@sec_g,@cat_videos,'Teaser','teaser',17),
  (@sec_g,@cat_videos,'Greyshades Videos','greyshades-videos',18);

-- Hybrid subcategories (top-level groups)
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_hybrid,'Medical & Health Awareness Days','hybrid-medical-days',1),
  (@sec_g,@cat_hybrid,'National & Cultural Festivals','hybrid-national-festivals',2),
  (@sec_g,@cat_hybrid,'Environment & Wellness','hybrid-environment-wellness',3);

SET @cat_hybrid_med = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='hybrid-medical-days');
SET @cat_hybrid_nat = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='hybrid-national-festivals');
SET @cat_hybrid_env = (SELECT id FROM categories WHERE section_id=@sec_g AND slug='hybrid-environment-wellness');

-- Hybrid > Medical & Health Awareness Days (leaves)
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
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

-- Hybrid > Environment & Wellness (leaves)
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_g,@cat_hybrid_env,'World Environment Day','hyb-world-environment-day',1),
  (@sec_g,@cat_hybrid_env,'World Yoga Day','hyb-world-yoga-day',2),
  (@sec_g,@cat_hybrid_env,'Doctors'' Day','hyb-doctors-day',3);

-- Hybrid > National & Cultural Festivals (leaves)
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
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

-- ---------- CATEGORIES (Greyshades Events) ----------
INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_e,NULL,'Events','events',1);

SET @cat_events = (SELECT id FROM categories WHERE section_id=@sec_e AND slug='events');

INSERT INTO `categories` (`section_id`,`parent_id`,`name`,`slug`,`sort_order`) VALUES
  (@sec_e,@cat_events,'Conference activities','conference-activities',1),
  (@sec_e,@cat_events,'Stall fabrication','stall-fabrication',2),
  (@sec_e,@cat_events,'Product launches','product-launches',3),
  (@sec_e,@cat_events,'Personalized inputs','personalized-inputs',4),
  (@sec_e,@cat_events,'Diaries','diaries',5);

-- ---------- OCCASION GROUPS ----------
INSERT INTO `occasion_groups` (`code`,`name`) VALUES
  ('medical','Medical & Health Awareness Days'),
  ('national','National & Cultural Festivals'),
  ('environment','Environment & Wellness');

SET @og_med = (SELECT id FROM occasion_groups WHERE code='medical');
SET @og_nat = (SELECT id FROM occasion_groups WHERE code='national');
SET @og_env = (SELECT id FROM occasion_groups WHERE code='environment');

-- ---------- OCCASIONS: Medical & Health ----------
INSERT INTO `occasions` (`group_id`,`name`,`slug`) VALUES
  (@og_med,'World No Tobacco Day','world-no-tobacco-day'),
  (@og_med,'World Bronchiectasis Day','world-bronchiectasis-day'),
  (@og_med,'World Lung Day','world-lung-day'),
  (@og_med,'World Pneumonia Day','world-pneumonia-day'),
  (@og_med,'World TB Day','world-tb-day'),
  (@og_med,'World Asthma Day','world-asthma-day'),
  (@og_med,'World COPD Day','world-copd-day'),
  (@og_med,'World Hypertension Day','world-hypertension-day'),
  (@og_med,'World Heart Day','world-heart-day'),
  (@og_med,'World Hearing Day','world-hearing-day'),
  (@og_med,'World Sight Day','world-sight-day'),
  (@og_med,'World Liver Day','world-liver-day'),
  (@og_med,'International NASH Day','international-nash-day'),
  (@og_med,'World Hepatitis Day','world-hepatitis-day'),
  (@og_med,'Liver Awareness Month','liver-awareness-month'),
  (@og_med,'World Diabetes Day','world-diabetes-day'),
  (@og_med,'World Alzheimer''s Day','world-alzheimers-day'),
  (@og_med,'World Mental Health Day','world-mental-health-day'),
  (@og_med,'World Glaucoma Week','world-glaucoma-week'),
  (@og_med,'World Kidney Day','world-kidney-day'),
  (@og_med,'World Leprosy Day','world-leprosy-day'),
  (@og_med,'World Melanoma Day','world-melanoma-day'),
  (@og_med,'World Vitiligo Day','world-vitiligo-day'),
  (@og_med,'World Psoriasis Day','world-psoriasis-day'),
  (@og_med,'Endometriosis Day','endometriosis-day'),
  (@og_med,'World PCOS Day','world-pcos-day'),
  (@og_med,'World Autism Awareness Day','world-autism-awareness-day'),
  (@og_med,'Children''s Day','childrens-day'),
  (@og_med,'World Spine Day','world-spine-day'),
  (@og_med,'World Trauma Day','world-trauma-day'),
  (@og_med,'World Osteoporosis Day','world-osteoporosis-day'),
  (@og_med,'World Arthritis Day','world-arthritis-day'),
  (@og_med,'World Cancer Day','world-cancer-day'),
  (@og_med,'World Lung Cancer Day','world-lung-cancer-day'),
  (@og_med,'World Hemophilia Day','world-hemophilia-day'),
  (@og_med,'World Health Day','world-health-day'),
  (@og_med,'World Thalassemia Day','world-thalassemia-day'),
  (@og_med,'World Allergy Day','world-allergy-day'),
  (@og_med,'Nutritional Awareness Week','nutritional-awareness-week'),
  (@og_med,'Antimicrobial Awareness Week','antimicrobial-awareness-week'),
  (@og_med,'World AIDS Day','world-aids-day');

-- ---------- OCCASIONS: Environment & Wellness ----------
INSERT INTO `occasions` (`group_id`,`name`,`slug`) VALUES
  (@og_env,'World Environment Day','world-environment-day'),
  (@og_env,'World Yoga Day','world-yoga-day'),
  (@og_env,'Doctors'' Day','doctors-day');

-- ---------- OCCASIONS: National & Cultural ----------
INSERT INTO `occasions` (`group_id`,`name`,`slug`) VALUES
  (@og_nat,'Republic Day','republic-day'),
  (@og_nat,'Valentine''s Day','valentines-day'),
  (@og_nat,'Women''s Day','womens-day'),
  (@og_nat,'Ram Navami','ram-navami'),
  (@og_nat,'Mother''s Day','mothers-day'),
  (@og_nat,'Music Day','music-day'),
  (@og_nat,'Independence Day','independence-day'),
  (@og_nat,'Teachers'' Day','teachers-day'),
  (@og_nat,'Gandhi Jayanti','gandhi-jayanti'),
  (@og_nat,'Diwali','diwali'),
  (@og_nat,'Christmas','christmas'),
  (@og_nat,'New Year','new-year'),
  (@og_nat,'Holi','holi'),
  (@og_nat,'Ambedkar Jayanti','ambedkar-jayanti'),
  (@og_nat,'Raksha Bandhan','raksha-bandhan'),
  (@og_nat,'Daughters Day','daughters-day'),
  (@og_nat,'Navratri','navratri'),
  (@og_nat,'Bhai Dooj','bhai-dooj'),
  (@og_nat,'Makar Sankranti','makar-sankranti'),
  (@og_nat,'Gudi Padwa','gudi-padwa'),
  (@og_nat,'Friendship Day','friendship-day'),
  (@og_nat,'Ganesh Chaturthi','ganesh-chaturthi'),
  (@og_nat,'Dussehra','dussehra');
