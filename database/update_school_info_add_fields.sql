-- Add principal_designation and short_code columns to school_info table
ALTER TABLE `school_info`
  ADD COLUMN `principal_designation` VARCHAR(100) DEFAULT '' AFTER `principal_name`,
  ADD COLUMN `short_code` VARCHAR(50) DEFAULT '' AFTER `name`;
