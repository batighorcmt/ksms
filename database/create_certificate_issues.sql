-- Migration: create certificate_issues table
-- Run this SQL in your MySQL to create the tracking table
CREATE TABLE IF NOT EXISTS `certificate_issues` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `student_id` INT NOT NULL,
  `issued_by` INT DEFAULT NULL,
  `certificate_type` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_issued_by` (`issued_by`),
  INDEX `idx_issued_at` (`issued_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
