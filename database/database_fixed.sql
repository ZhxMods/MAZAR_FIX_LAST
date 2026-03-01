-- ============================================================
--  MAZAR Educational Platform — database_fixed.sql
--  Import via phpMyAdmin or: mysql -u USER -p mazar_db < database_fixed.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Disable foreign key checks during import
SET FOREIGN_KEY_CHECKS = 0;

-- ────────────────────────────────────────────────────────────
-- TABLE: levels (Create first - no dependencies)
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `levels`;
CREATE TABLE `levels` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar`   VARCHAR(100) NOT NULL,
  `name_fr`   VARCHAR(100) NOT NULL,
  `name_en`   VARCHAR(100) NOT NULL,
  `slug`      VARCHAR(60)  NOT NULL,
  `order_num` TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert levels first (needed for foreign keys)
INSERT INTO `levels` (`id`, `name_ar`, `name_fr`, `name_en`, `slug`, `order_num`) VALUES
(1,  'السنة الأولى ابتدائي',  '1ère Année Primaire',   '1st Primary',          '1-primaire',  1),
(2,  'السنة الثانية ابتدائي', '2ème Année Primaire',   '2nd Primary',          '2-primaire',  2),
(3,  'السنة الثالثة ابتدائي', '3ème Année Primaire',   '3rd Primary',          '3-primaire',  3),
(4,  'السنة الرابعة ابتدائي', '4ème Année Primaire',   '4th Primary',          '4-primaire',  4),
(5,  'السنة الخامسة ابتدائي', '5ème Année Primaire',   '5th Primary',          '5-primaire',  5),
(6,  'السنة السادسة ابتدائي', '6ème Année Primaire',   '6th Primary',          '6-primaire',  6),
(7,  'السنة الأولى إعدادي',   '1ère Année Collège',    '1st Middle School',    '1-college',   7),
(8,  'السنة الثانية إعدادي',  '2ème Année Collège',    '2nd Middle School',    '2-college',   8),
(9,  'السنة الثالثة إعدادي',  '3ème Collège',          '3rd Middle School',    '3-college',   9),
(10, 'السنة الأولى ثانوي',    '1ère Année Lycée',      '1st High School',      '1-lycee',    10),
(11, 'السنة الثانية ثانوي',   'Tronc Commun',          '2nd High School',      'tc-lycee',   11),
(12, 'البكالوريا الأولى',     '1ère Bac',              '1st Bac',              '1-bac',      12),
(13, 'البكالوريا الثانية',    '2ème Bac',              '2nd Bac',              '2-bac',      13);

-- ────────────────────────────────────────────────────────────
-- TABLE: users
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `full_name`      VARCHAR(120)     NOT NULL,
  `email`          VARCHAR(180)     NOT NULL,
  `password`       VARCHAR(255)     NOT NULL,
  `grade_level_id` INT UNSIGNED     NOT NULL DEFAULT 13,
  `role`           ENUM('student','staff','admin','super_admin') NOT NULL DEFAULT 'student',
  `xp_points`      INT UNSIGNED     NOT NULL DEFAULT 0,
  `level`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status`         ENUM('active','banned') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `grade_level_id` (`grade_level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: subjects
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_ar`   VARCHAR(100) NOT NULL,
  `name_fr`   VARCHAR(100) NOT NULL,
  `name_en`   VARCHAR(100) NOT NULL,
  `level_id`  INT UNSIGNED NOT NULL,
  `icon`      VARCHAR(60)  NOT NULL DEFAULT 'BookOpen',
  `color`     VARCHAR(20)  NOT NULL DEFAULT '#3B82F6',
  `order_num` TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `level_id` (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample subjects
INSERT INTO `subjects` (`name_ar`, `name_fr`, `name_en`, `level_id`, `icon`, `color`, `order_num`) VALUES
('الرياضيات',    'Mathématiques',  'Mathematics',  9,  'Calculator',   '#3B82F6', 1),
('الفيزياء والكيمياء', 'Physique-Chimie', 'Physics-Chemistry', 9, 'Zap', '#F59E0B', 2),
('اللغة العربية','Langue Arabe',   'Arabic',       9,  'BookOpen',     '#10B981', 3),
('اللغة الفرنسية','Français',      'French',       9,  'Globe',        '#8B5CF6', 4),
('التربية الإسلامية','Islam',      'Islamic Ed.',  9,  'Star',         '#EF4444', 5),
('الرياضيات',    'Mathématiques',  'Mathematics',  13, 'Calculator',   '#3B82F6', 1),
('الفيزياء والكيمياء', 'Physique-Chimie', 'Physics-Chemistry', 13, 'Zap', '#F59E0B', 2),
('علوم الحياة والأرض','Sciences de la Vie et de la Terre', 'Life Sciences', 13, 'Leaf', '#10B981', 3),
('الفلسفة',      'Philosophie',   'Philosophy',   13, 'Brain',        '#8B5CF6', 4),
('التاريخ والجغرافيا','Histoire-Géo', 'History-Geo', 13, 'Map',        '#EC4899', 5);

-- ────────────────────────────────────────────────────────────
-- TABLE: lessons
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `lessons`;
CREATE TABLE `lessons` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title_ar`    VARCHAR(255)  NOT NULL,
  `title_fr`    VARCHAR(255)  NOT NULL,
  `title_en`    VARCHAR(255)  NOT NULL,
  `desc_ar`     TEXT,
  `desc_fr`     TEXT,
  `desc_en`     TEXT,
  `type`        ENUM('video','pdf','book') NOT NULL DEFAULT 'video',
  `url`         TEXT          NOT NULL,
  `thumbnail`   VARCHAR(500)  DEFAULT NULL,
  `level_id`    INT UNSIGNED  NOT NULL,
  `subject_id`  INT UNSIGNED  NOT NULL,
  `xp_reward`   SMALLINT      NOT NULL DEFAULT 10,
  `duration`    SMALLINT      NOT NULL DEFAULT 0,
  `published`   TINYINT(1)    NOT NULL DEFAULT 1,
  `order_num`   SMALLINT      NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `level_id`   (`level_id`),
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: user_lesson_completions
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `user_lesson_completions`;
CREATE TABLE `user_lesson_completions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `lesson_id`    INT UNSIGNED NOT NULL,
  `completed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_completion` (`user_id`, `lesson_id`),
  KEY `lesson_id` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: quizzes
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `quizzes`;
CREATE TABLE `quizzes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lesson_id`  INT UNSIGNED NOT NULL,
  `title_ar`   VARCHAR(255) NOT NULL,
  `title_fr`   VARCHAR(255) NOT NULL,
  `title_en`   VARCHAR(255) NOT NULL,
  `pass_score` TINYINT      NOT NULL DEFAULT 60,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: quiz_questions
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `quiz_questions`;
CREATE TABLE `quiz_questions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quiz_id`     INT UNSIGNED NOT NULL,
  `question_ar` TEXT         NOT NULL,
  `question_fr` TEXT         NOT NULL,
  `question_en` TEXT         NOT NULL,
  `order_num`   TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: quiz_options
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `quiz_options`;
CREATE TABLE `quiz_options` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `option_ar`   VARCHAR(500) NOT NULL,
  `option_fr`   VARCHAR(500) NOT NULL,
  `option_en`   VARCHAR(500) NOT NULL,
  `is_correct`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: user_quiz_attempts
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `user_quiz_attempts`;
CREATE TABLE `user_quiz_attempts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `quiz_id`    INT UNSIGNED NOT NULL,
  `score`      TINYINT      NOT NULL DEFAULT 0,
  `passed`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `quiz_id` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: activity_log
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `action`     VARCHAR(80)  NOT NULL,
  `details`    VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`    (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: ai_config (Super Admin AI Configuration)
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `ai_config`;
CREATE TABLE `ai_config` (
  `id`            INT UNSIGNED      NOT NULL DEFAULT 1,
  `provider`      VARCHAR(50)       NOT NULL DEFAULT 'openai',
  `model`         VARCHAR(100)      NOT NULL DEFAULT 'gpt-4o',
  `api_key`       TEXT              DEFAULT NULL,
  `system_prompt` TEXT              DEFAULT NULL,
  `enabled`       TINYINT(1)        NOT NULL DEFAULT 1,
  `temperature`   DECIMAL(3,2)      NOT NULL DEFAULT 0.70,
  `max_tokens`    INT UNSIGNED      NOT NULL DEFAULT 1000,
  `updated_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`    INT UNSIGNED      DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default AI configuration
INSERT INTO `ai_config` (`id`, `provider`, `model`, `system_prompt`, `enabled`, `temperature`, `max_tokens`)
VALUES (1, 'openai', 'gpt-4o', 'You are MAZAR AI, an educational assistant for Moroccan students. Provide helpful, accurate, and age-appropriate educational responses in the user\'s language (Arabic, French, or English).', 1, 0.70, 1000);

-- ────────────────────────────────────────────────────────────
-- TABLE: audit_log (Security Audit Trail)
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED      NOT NULL DEFAULT 0,
  `action`      VARCHAR(100)      NOT NULL,
  `details`     TEXT              DEFAULT NULL,
  `ip_address`  VARCHAR(45)       DEFAULT NULL,
  `user_agent`  VARCHAR(255)      DEFAULT NULL,
  `created_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`     (`user_id`),
  KEY `action`      (`action`),
  KEY `created_at`  (`created_at`),
  KEY `ip_address`  (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- TABLE: login_attempts (Rate Limiting)
-- ────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `identifier`    VARCHAR(255)      NOT NULL,
  `attempts`      INT UNSIGNED      NOT NULL DEFAULT 1,
  `last_attempt`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` DATETIME          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`),
  KEY `blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- ADD FOREIGN KEY CONSTRAINTS (After all tables are created)
-- ────────────────────────────────────────────────────────────

-- Users foreign key
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_level` FOREIGN KEY (`grade_level_id`) REFERENCES `levels` (`id`) ON DELETE RESTRICT;

-- Subjects foreign key
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE;

-- Lessons foreign keys
ALTER TABLE `lessons`
  ADD CONSTRAINT `fk_lessons_level` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lessons_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

-- User lesson completions foreign keys
ALTER TABLE `user_lesson_completions`
  ADD CONSTRAINT `fk_ulc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ulc_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

-- Quizzes foreign key
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quiz_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

-- Quiz questions foreign key
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `fk_qq_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

-- Quiz options foreign key
ALTER TABLE `quiz_options`
  ADD CONSTRAINT `fk_qo_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE;

-- User quiz attempts foreign keys
ALTER TABLE `user_quiz_attempts`
  ADD CONSTRAINT `fk_uqa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uqa_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

-- Activity log foreign key
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ────────────────────────────────────────────────────────────
-- INSERT DEFAULT ADMIN USER (After foreign keys are added)
-- ────────────────────────────────────────────────────────────

-- Default admin (password: Admin@1234)
-- You can change this password after first login
INSERT INTO `users` (`full_name`, `email`, `password`, `grade_level_id`, `role`, `xp_points`, `level`, `status`)
VALUES ('Super Admin', 'admin@mazar.ma',
        '$2y$12$Qz0kLkBJf5kZzU1vXXXXXeKfF8dNklEjHuGvqBUHi6OdS1UzBxYhS',
        13, 'super_admin', 0, 1, 'active');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
