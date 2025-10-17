-- Migration: add certificate_number column to certificate_issues
ALTER TABLE `certificate_issues`
  ADD COLUMN `certificate_number` VARCHAR(255) NULL AFTER `notes`;
