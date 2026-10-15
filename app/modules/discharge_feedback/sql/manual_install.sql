-- ONLY needed if the module "Activate" button could not create the tables.
-- Run in the MAIN database (phpMyAdmin > main DB > SQL). Change `tbl` if your db_prefix is different.

CREATE TABLE IF NOT EXISTS `tbldischarge_feedback_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id` INT(11) NOT NULL DEFAULT 0,
  `branch_name` VARCHAR(191) NULL,
  `lead_id` INT(11) NOT NULL,
  `hash` VARCHAR(64) NOT NULL,
  `patient_name` VARCHAR(191) NULL,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(50) NULL,
  `discharged_at` DATETIME NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `send_count` INT(11) NOT NULL DEFAULT 0,
  `last_sent_at` DATETIME NULL,
  `last_attempt_at` DATETIME NULL,
  `last_channels` VARCHAR(50) NULL,
  `last_error` TEXT NULL,
  `completed_at` DATETIME NULL,
  `completed_via` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  UNIQUE KEY `branch_lead` (`branch_id`, `lead_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tbldischarge_feedback_responses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) NULL,
  `branch_id` INT(11) NOT NULL DEFAULT 0,
  `lead_id` INT(11) NULL,
  `patient_id` INT(11) NULL,
  `consent_pdf_id` INT(11) NULL,
  `consent_submission_id` INT(11) NULL,
  `form_file` VARCHAR(191) NULL,
  `patient_name` VARCHAR(191) NULL,
  `form_data_json` LONGTEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `submitted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbldischarge_feedback_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) NOT NULL,
  `channel` VARCHAR(10) NOT NULL,
  `recipient` VARCHAR(191) NULL,
  `is_reminder` TINYINT(1) NOT NULL DEFAULT 0,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `error` TEXT NULL,
  `sent_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tbldischarge_feedback_settings` (
  `name` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `tbldischarge_feedback_settings` (`name`, `value`) VALUES
  ('start_date', NOW()),
  ('cron_key', MD5(RAND()));
