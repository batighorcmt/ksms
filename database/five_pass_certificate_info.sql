--
-- Table structure for table `five_pass_certificate_info`
--
CREATE TABLE IF NOT EXISTS `five_pass_certificate_info` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `gpa` VARCHAR(10) NOT NULL,
  `exam_year` VARCHAR(10) NOT NULL,
  `certificate_id` VARCHAR(32) NOT NULL,
  `issue_date` DATE NOT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
