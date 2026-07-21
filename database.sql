-- ============================================================
-- Baladiyati — بلديتي — Algerian citizen complaint platform
-- Import this file into your MySQL database (phpMyAdmin > Import),
-- then open /install.php in the browser to create the super admin.
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS user_assoc_categories, assoc_profiles, assoc_categories,
    tree_confirms, tree_plantings, tree_campaigns,
    push_subscriptions, settings, user_badges, badges, points_log,
    notifications, upvotes, complaint_events, complaints, categories, users, communes, wilayas;
SET FOREIGN_KEY_CHECKS = 1;

-- Key/value settings (VAPID push keys are stored here automatically)
CREATE TABLE settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web Push subscriptions (one row per device that enabled notifications)
CREATE TABLE push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL UNIQUE,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tree-planting campaigns (goals set by admins/associations, trees logged by
-- citizens, approved by community confirmation)
CREATE TABLE tree_campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  creator_id INT NOT NULL,
  wilaya_id INT NOT NULL,
  commune_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  goal INT NOT NULL,
  ends_at DATE NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  KEY idx_status (status),
  KEY idx_commune (commune_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tree_plantings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT NOT NULL,
  user_id INT NOT NULL,
  trees INT NOT NULL DEFAULT 1,
  photo VARCHAR(120) NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  note VARCHAR(300) NULL,
  confirms INT NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  KEY idx_campaign (campaign_id),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tree_confirms (
  planting_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (planting_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Association directory: activity domains, extended profiles, and the
-- many-to-many link between associations and domains. (These also
-- auto-create on first use, so existing databases need no migration.)
CREATE TABLE assoc_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  icon VARCHAR(16) NOT NULL DEFAULT '🏷️',
  name_ar VARCHAR(80) NOT NULL,
  name_fr VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assoc_profiles (
  user_id INT PRIMARY KEY,
  logo VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  website VARCHAR(190) NULL,
  facebook VARCHAR(190) NULL,
  address VARCHAR(190) NULL,
  founded_year SMALLINT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_assoc_categories (
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (user_id, category_id),
  KEY idx_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO assoc_categories (icon, name_ar, name_fr, sort) VALUES
('🌿', 'البيئة والنظافة', 'Environnement et propreté', 1),
('🤝', 'التضامن والعمل الاجتماعي', 'Solidarité et action sociale', 2),
('🎭', 'الثقافة والفنون', 'Culture et arts', 3),
('⚽', 'الرياضة', 'Sport', 4),
('🏥', 'الصحة', 'Santé', 5),
('📚', 'التربية والتعليم', 'Éducation', 6),
('🧒', 'الطفولة والشباب', 'Enfance et jeunesse', 7),
('♿', 'ذوو الاحتياجات الخاصة', 'Personnes à besoins spécifiques', 8),
('🏘️', 'التنمية المحلية', 'Développement local', 9),
('🕌', 'الأعمال الخيرية', 'Œuvres caritatives', 10),
('👵', 'كبار السن', 'Personnes âgées', 11),
('🐾', 'الرفق بالحيوان', 'Protection animale', 12);

CREATE TABLE wilayas (
  id INT PRIMARY KEY,
  name_ar VARCHAR(120) NOT NULL,
  name_fr VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE communes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wilaya_id INT NOT NULL,
  name_ar VARCHAR(120) NOT NULL,
  name_fr VARCHAR(120) NOT NULL,
  KEY idx_wilaya (wilaya_id),
  CONSTRAINT fk_commune_wilaya FOREIGN KEY (wilaya_id) REFERENCES wilayas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('citizen','association','admin','superadmin') NOT NULL DEFAULT 'citizen',
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  wilaya_id INT NULL,
  commune_id INT NULL,
  about TEXT NULL,
  points INT NOT NULL DEFAULT 0,
  lang ENUM('ar','fr') NOT NULL DEFAULT 'ar',
  is_verified TINYINT(1) NOT NULL DEFAULT 1,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_role (role),
  KEY idx_commune (commune_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name_ar VARCHAR(80) NOT NULL,
  name_fr VARCHAR(80) NOT NULL,
  icon VARCHAR(16) NOT NULL DEFAULT '🏷️',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(12) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  wilaya_id INT NOT NULL,
  commune_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  photo VARCHAR(120) NOT NULL,
  after_photo VARCHAR(120) NULL,
  status ENUM('pending','published','in_progress','resolved','closed','rejected') NOT NULL DEFAULT 'pending',
  reject_reason TEXT NULL,
  handler_id INT NULL,
  upvotes INT NOT NULL DEFAULT 0,
  reopened TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_status (status),
  KEY idx_commune (commune_id),
  KEY idx_wilaya (wilaya_id),
  KEY idx_user (user_id),
  KEY idx_handler (handler_id),
  CONSTRAINT fk_c_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_c_cat FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_c_wilaya FOREIGN KEY (wilaya_id) REFERENCES wilayas(id),
  CONSTRAINT fk_c_commune FOREIGN KEY (commune_id) REFERENCES communes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE complaint_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  user_id INT NULL,
  event VARCHAR(20) NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_complaint (complaint_id),
  CONSTRAINT fk_ev_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE upvotes (
  complaint_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (complaint_id, user_id),
  CONSTRAINT fk_uv_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_uv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(40) NOT NULL,
  complaint_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_user_read (user_id, is_read),
  CONSTRAINT fk_n_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE points_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  points INT NOT NULL,
  reason VARCHAR(40) NOT NULL,
  complaint_id INT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_user (user_id),
  CONSTRAINT fk_p_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  icon VARCHAR(16) NOT NULL,
  name_ar VARCHAR(80) NOT NULL,
  name_fr VARCHAR(80) NOT NULL,
  desc_ar VARCHAR(190) NOT NULL,
  desc_fr VARCHAR(190) NOT NULL,
  role ENUM('citizen','association','admin') NOT NULL,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_badges (
  user_id INT NOT NULL,
  badge_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, badge_id),
  CONSTRAINT fk_ub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed: categories
-- ------------------------------------------------------------
INSERT INTO categories (name_ar, name_fr, icon, is_active) VALUES
('طرقات وأرصفة', 'Routes et trottoirs', '🛣️', 1),
('نظافة وقمامة', 'Propreté et déchets', '🗑️', 1),
('كهرباء', 'Électricité', '⚡', 1),
('ماء صالح للشرب', 'Eau potable', '🚰', 1),
('إنارة عمومية', 'Éclairage public', '💡', 1),
('صرف صحي', 'Assainissement', '🕳️', 1),
('مساحات خضراء', 'Espaces verts', '🌳', 1),
('نقل عمومي', 'Transport public', '🚌', 1),
('أخرى', 'Autre', '📌', 1);

-- ------------------------------------------------------------
-- Seed: badges (codes must match includes/functions.php)
-- ------------------------------------------------------------
INSERT INTO badges (code, icon, name_ar, name_fr, desc_ar, desc_fr, role, sort) VALUES
('first_report', '📣', 'أول بلاغ', 'Premier signalement', 'أول بلاغ منشور لك', 'Votre premier signalement publié', 'citizen', 1),
('reporter_5', '🗞️', 'مراسل الحي', 'Reporter du quartier', '5 بلاغات منشورة', '5 signalements publiés', 'citizen', 2),
('reporter_20', '📢', 'صوت المدينة', 'Voix de la ville', '20 بلاغًا منشورًا', '20 signalements publiés', 'citizen', 3),
('first_fixed', '🔧', 'أول إصلاح', 'Première réparation', 'أول بلاغ لك تم إصلاحه', 'Votre premier signalement réparé', 'citizen', 4),
('fixed_10', '🏗️', 'محرّك التغيير', 'Moteur du changement', '10 بلاغات لك تم إصلاحها', '10 de vos signalements réparés', 'citizen', 5),
('supporter_10', '👍', 'داعم الحي', 'Soutien du quartier', 'أكّدت 10 بلاغات لمواطنين آخرين', 'Vous avez confirmé 10 signalements', 'citizen', 6),
('assoc_first_fix', '🌟', 'أول إنجاز', 'Première action', 'أول إصلاح للجمعية', "Première réparation de l'association", 'association', 10),
('assoc_fix_5', '💪', 'فاعل خير', 'Acteur de terrain', '5 إصلاحات للجمعية', "5 réparations de l'association", 'association', 11),
('assoc_fix_20', '🏆', 'سند البلدية', 'Pilier de la commune', '20 إصلاحًا للجمعية', "20 réparations de l'association", 'association', 12),
('admin_fix_10', '🛠️', 'مشرف منجز', 'Admin efficace', '10 إصلاحات مسجلة', '10 réparations enregistrées', 'admin', 20),
('admin_fix_50', '🎖️', 'خادم البلدية', 'Serviteur de la commune', '50 إصلاحًا مسجلاً', '50 réparations enregistrées', 'admin', 21),
('volunteer_first_fix', '🔨', 'مصلح متطوع', 'Réparateur bénévole', 'أول إصلاح تطوعي مصادق عليه', 'Première réparation bénévole validée', 'citizen', 7),
('volunteer_fix_5', '🦸', 'بطل الحي', 'Héros du quartier', '5 إصلاحات تطوعية', '5 réparations bénévoles', 'citizen', 8),
('confirmer_1', '✅', 'مؤكّد الإصلاح', 'Confirmateur', 'أكدت أول إصلاح لبلاغك', 'Première réparation confirmée', 'citizen', 9),
('confirmer_5', '🔏', 'غالق المشاكل', 'Clôtureur de problèmes', 'أكدت غلق 5 بلاغات', '5 signalements clôturés confirmés', 'citizen', 10),
('planter_1', '🌱', 'غارس', 'Planteur', 'أول شجرة مغروسة معتمدة', 'Premier arbre planté approuvé', 'citizen', 30),
('planter_25', '🌳', 'صديق البيئة', "Ami de l'environnement", '25 شجرة مغروسة', '25 arbres plantés', 'citizen', 31),
('planter_100', '🌲', 'حارس الغابة', 'Gardien de la forêt', '100 شجرة مغروسة', '100 arbres plantés', 'citizen', 32);

-- ------------------------------------------------------------
-- Seed: the 58 wilayas of Algeria
-- ------------------------------------------------------------
INSERT INTO wilayas (id, name_ar, name_fr) VALUES
(1, 'أدرار', 'Adrar'),
(2, 'الشلف', 'Chlef'),
(3, 'الأغواط', 'Laghouat'),
(4, 'أم البواقي', 'Oum El Bouaghi'),
(5, 'باتنة', 'Batna'),
(6, 'بجاية', 'Béjaïa'),
(7, 'بسكرة', 'Biskra'),
(8, 'بشار', 'Béchar'),
(9, 'البليدة', 'Blida'),
(10, 'البويرة', 'Bouira'),
(11, 'تمنراست', 'Tamanrasset'),
(12, 'تبسة', 'Tébessa'),
(13, 'تلمسان', 'Tlemcen'),
(14, 'تيارت', 'Tiaret'),
(15, 'تيزي وزو', 'Tizi Ouzou'),
(16, 'الجزائر', 'Alger'),
(17, 'الجلفة', 'Djelfa'),
(18, 'جيجل', 'Jijel'),
(19, 'سطيف', 'Sétif'),
(20, 'سعيدة', 'Saïda'),
(21, 'سكيكدة', 'Skikda'),
(22, 'سيدي بلعباس', 'Sidi Bel Abbès'),
(23, 'عنابة', 'Annaba'),
(24, 'قالمة', 'Guelma'),
(25, 'قسنطينة', 'Constantine'),
(26, 'المدية', 'Médéa'),
(27, 'مستغانم', 'Mostaganem'),
(28, 'المسيلة', "M'Sila"),
(29, 'معسكر', 'Mascara'),
(30, 'ورقلة', 'Ouargla'),
(31, 'وهران', 'Oran'),
(32, 'البيض', 'El Bayadh'),
(33, 'إليزي', 'Illizi'),
(34, 'برج بوعريريج', 'Bordj Bou Arreridj'),
(35, 'بومرداس', 'Boumerdès'),
(36, 'الطارف', 'El Tarf'),
(37, 'تندوف', 'Tindouf'),
(38, 'تيسمسيلت', 'Tissemsilt'),
(39, 'الوادي', 'El Oued'),
(40, 'خنشلة', 'Khenchela'),
(41, 'سوق أهراس', 'Souk Ahras'),
(42, 'تيبازة', 'Tipaza'),
(43, 'ميلة', 'Mila'),
(44, 'عين الدفلى', 'Aïn Defla'),
(45, 'النعامة', 'Naâma'),
(46, 'عين تموشنت', 'Aïn Témouchent'),
(47, 'غرداية', 'Ghardaïa'),
(48, 'غليزان', 'Relizane'),
(49, 'تيميمون', 'Timimoun'),
(50, 'برج باجي مختار', 'Bordj Badji Mokhtar'),
(51, 'أولاد جلال', 'Ouled Djellal'),
(52, 'بني عباس', 'Béni Abbès'),
(53, 'عين صالح', 'In Salah'),
(54, 'عين قزام', 'In Guezzam'),
(55, 'تقرت', 'Touggourt'),
(56, 'جانت', 'Djanet'),
(57, 'المغير', "El M'Ghair"),
(58, 'المنيعة', 'El Meniaa');

-- ------------------------------------------------------------
-- Seed: communes — chef-lieu of every wilaya, plus the main
-- communes of Alger, Oran and Constantine. The super admin can
-- add more communes from the dashboard (Communes page).
-- ------------------------------------------------------------
INSERT INTO communes (wilaya_id, name_ar, name_fr) VALUES
(1, 'أدرار', 'Adrar'),
(2, 'الشلف', 'Chlef'),
(3, 'الأغواط', 'Laghouat'),
(4, 'أم البواقي', 'Oum El Bouaghi'),
(5, 'باتنة', 'Batna'),
(6, 'بجاية', 'Béjaïa'),
(7, 'بسكرة', 'Biskra'),
(8, 'بشار', 'Béchar'),
(9, 'البليدة', 'Blida'),
(10, 'البويرة', 'Bouira'),
(11, 'تمنراست', 'Tamanrasset'),
(12, 'تبسة', 'Tébessa'),
(13, 'تلمسان', 'Tlemcen'),
(14, 'تيارت', 'Tiaret'),
(15, 'تيزي وزو', 'Tizi Ouzou'),
(17, 'الجلفة', 'Djelfa'),
(18, 'جيجل', 'Jijel'),
(19, 'سطيف', 'Sétif'),
(20, 'سعيدة', 'Saïda'),
(21, 'سكيكدة', 'Skikda'),
(22, 'سيدي بلعباس', 'Sidi Bel Abbès'),
(23, 'عنابة', 'Annaba'),
(24, 'قالمة', 'Guelma'),
(26, 'المدية', 'Médéa'),
(27, 'مستغانم', 'Mostaganem'),
(28, 'المسيلة', "M'Sila"),
(29, 'معسكر', 'Mascara'),
(30, 'ورقلة', 'Ouargla'),
(32, 'البيض', 'El Bayadh'),
(33, 'إليزي', 'Illizi'),
(34, 'برج بوعريريج', 'Bordj Bou Arreridj'),
(35, 'بومرداس', 'Boumerdès'),
(36, 'الطارف', 'El Tarf'),
(37, 'تندوف', 'Tindouf'),
(38, 'تيسمسيلت', 'Tissemsilt'),
(39, 'الوادي', 'El Oued'),
(40, 'خنشلة', 'Khenchela'),
(41, 'سوق أهراس', 'Souk Ahras'),
(42, 'تيبازة', 'Tipaza'),
(43, 'ميلة', 'Mila'),
(44, 'عين الدفلى', 'Aïn Defla'),
(45, 'النعامة', 'Naâma'),
(46, 'عين تموشنت', 'Aïn Témouchent'),
(47, 'غرداية', 'Ghardaïa'),
(48, 'غليزان', 'Relizane'),
(49, 'تيميمون', 'Timimoun'),
(50, 'برج باجي مختار', 'Bordj Badji Mokhtar'),
(51, 'أولاد جلال', 'Ouled Djellal'),
(52, 'بني عباس', 'Béni Abbès'),
(53, 'عين صالح', 'In Salah'),
(54, 'عين قزام', 'In Guezzam'),
(55, 'تقرت', 'Touggourt'),
(56, 'جانت', 'Djanet'),
(57, 'المغير', "El M'Ghair"),
(58, 'المنيعة', 'El Meniaa');

-- Alger (16)
INSERT INTO communes (wilaya_id, name_ar, name_fr) VALUES
(16, 'الجزائر الوسطى', 'Alger-Centre'),
(16, 'سيدي امحمد', "Sidi M'Hamed"),
(16, 'المدنية', 'El Madania'),
(16, 'بلوزداد', 'Belouizdad'),
(16, 'باب الوادي', 'Bab El Oued'),
(16, 'القصبة', 'Casbah'),
(16, 'بولوغين', 'Bologhine'),
(16, 'الأبيار', 'El Biar'),
(16, 'حيدرة', 'Hydra'),
(16, 'بن عكنون', 'Ben Aknoun'),
(16, 'بئر مراد رايس', 'Bir Mourad Raïs'),
(16, 'القبة', 'Kouba'),
(16, 'حسين داي', 'Hussein Dey'),
(16, 'الحراش', 'El Harrach'),
(16, 'باش جراح', 'Bachdjerrah'),
(16, 'الدار البيضاء', 'Dar El Beïda'),
(16, 'باب الزوار', 'Bab Ezzouar'),
(16, 'برج الكيفان', 'Bordj El Kiffan'),
(16, 'المحمدية', 'Mohammadia'),
(16, 'براقي', 'Baraki'),
(16, 'بئر خادم', 'Birkhadem'),
(16, 'دراريـة', 'Draria'),
(16, 'الشراقة', 'Chéraga'),
(16, 'أولاد فايت', 'Ouled Fayet'),
(16, 'دالي إبراهيم', 'Dely Ibrahim'),
(16, 'عين البنيان', 'Aïn Benian'),
(16, 'سطاوالي', 'Staoueli'),
(16, 'زرالدة', 'Zéralda'),
(16, 'الرويبة', 'Rouiba'),
(16, 'الرغاية', 'Reghaïa'),
(16, 'عين طاية', 'Aïn Taya'),
(16, 'برج البحري', 'Bordj El Bahri'),
(16, 'بوزريعة', 'Bouzareah'),
(16, 'بني مسوس', 'Beni Messous'),
(16, 'وادي السمار', 'Oued Smar'),
(16, 'بئر توتة', 'Birtouta');

-- Oran (31)
INSERT INTO communes (wilaya_id, name_ar, name_fr) VALUES
(31, 'وهران', 'Oran'),
(31, 'بئر الجير', 'Bir El Djir'),
(31, 'السانية', 'Es Sénia'),
(31, 'أرزيو', 'Arzew'),
(31, 'عين الترك', 'Aïn El Turk'),
(31, 'قديل', 'Gdyel'),
(31, 'بطيوة', 'Bethioua'),
(31, 'وادي تليلات', 'Oued Tlélat'),
(31, 'المرسى الكبير', 'Mers El Kébir');

-- Constantine (25)
INSERT INTO communes (wilaya_id, name_ar, name_fr) VALUES
(25, 'قسنطينة', 'Constantine'),
(25, 'الخروب', 'El Khroub'),
(25, 'حامة بوزيان', 'Hamma Bouziane'),
(25, 'ديدوش مراد', 'Didouche Mourad'),
(25, 'عين سمارة', 'Aïn Smara'),
(25, 'زيغود يوسف', 'Zighoud Youcef');
