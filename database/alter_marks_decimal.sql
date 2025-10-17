-- Ensure marks support decimals (e.g., 53.25)
-- Run these ALTERs in MySQL if your current schema uses INT for marks.

-- Option A: If table and column already exist but integer type
ALTER TABLE `marks` MODIFY `marks_obtained` DECIMAL(6,2) NOT NULL DEFAULT 0.00;

-- Option B: If creating fresh, define as DECIMAL directly
-- CREATE TABLE `marks` (
--   `id` INT AUTO_INCREMENT PRIMARY KEY,
--   `exam_id` INT NOT NULL,
--   `subject_id` INT NOT NULL,
--   `student_id` INT NOT NULL,
--   `marks_obtained` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
--   KEY (`exam_id`), KEY (`subject_id`), KEY (`student_id`)
-- );
