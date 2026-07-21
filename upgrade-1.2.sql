-- ============================================================
-- Baladiyati upgrade 1.2 — for EXISTING databases only.
-- (Fresh installs import database.sql instead — it already
--  contains everything below.)
-- Adds: volunteer/closer/planter badges. The tree-campaign and
-- push tables create themselves automatically on first use, so
-- this file only needs to add the new badge rows.
-- Import via phpMyAdmin on your existing database. Safe to run twice.
-- ============================================================
SET NAMES utf8mb4;

INSERT IGNORE INTO badges (code, icon, name_ar, name_fr, desc_ar, desc_fr, role, sort) VALUES
('volunteer_first_fix', '🔨', 'مصلح متطوع', 'Réparateur bénévole', 'أول إصلاح تطوعي مصادق عليه', 'Première réparation bénévole validée', 'citizen', 7),
('volunteer_fix_5', '🦸', 'بطل الحي', 'Héros du quartier', '5 إصلاحات تطوعية', '5 réparations bénévoles', 'citizen', 8),
('confirmer_1', '✅', 'مؤكّد الإصلاح', 'Confirmateur', 'أكدت أول إصلاح لبلاغك', 'Première réparation confirmée', 'citizen', 9),
('confirmer_5', '🔏', 'غالق المشاكل', 'Clôtureur de problèmes', 'أكدت غلق 5 بلاغات', '5 signalements clôturés confirmés', 'citizen', 10),
('planter_1', '🌱', 'غارس', 'Planteur', 'أول شجرة مغروسة معتمدة', 'Premier arbre planté approuvé', 'citizen', 30),
('planter_25', '🌳', 'صديق البيئة', "Ami de l'environnement", '25 شجرة مغروسة', '25 arbres plantés', 'citizen', 31),
('planter_100', '🌲', 'حارس الغابة', 'Gardien de la forêt', '100 شجرة مغروسة', '100 arbres plantés', 'citizen', 32);
