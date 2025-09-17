-- Lesson Evaluation Table
CREATE TABLE `lesson_evaluation` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `date` DATE NOT NULL,
  `evaluated_students` TEXT NOT NULL, -- Comma separated student IDs (JSON or CSV)
  `is_completed` TINYINT(1) NOT NULL DEFAULT 0, -- 1 = পড়া হয়েছে, 0 = হয়নি
  `remarks` TEXT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`),
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
