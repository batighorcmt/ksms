-- Exams schema for dynamic exam management
-- Run this on your MySQL database (adjust names/types as needed)
CREATE TABLE IF NOT EXISTS `exam_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `academic_year_id` INT DEFAULT NULL,
  `class_id` INT NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `exam_type_id` INT DEFAULT NULL,
  `result_publish_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `exam_subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `exam_date` DATE DEFAULT NULL,
  `exam_time` VARCHAR(50) DEFAULT NULL,
  `full_mark` INT DEFAULT 100,
  `pass_mark` INT DEFAULT 33
);

-- Links between a term exam and tutorial exams (for cumulative tabulation)
CREATE TABLE IF NOT EXISTS `exam_tutorial_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `term_exam_id` INT NOT NULL,
  `tutorial_exam_id` INT NOT NULL
);

-- Basic indexing
ALTER TABLE `exams` ADD INDEX (`class_id`);
ALTER TABLE `exam_subjects` ADD INDEX (`exam_id`);
