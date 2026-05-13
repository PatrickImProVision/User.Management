-- Product Store: core tables (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'user',
  `profile_image` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `activation_guid` VARCHAR(64) NULL DEFAULT NULL,
  `deactivation_guid` VARCHAR(64) NULL DEFAULT NULL,
  `reset_guid` VARCHAR(64) NULL DEFAULT NULL,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `__DB_PREFIX__users_username_unique` (`username`),
  UNIQUE KEY `__DB_PREFIX__users_email_unique` (`email`),
  KEY `__DB_PREFIX__users_activation_guid_idx` (`activation_guid`),
  KEY `__DB_PREFIX__users_deactivation_guid_idx` (`deactivation_guid`),
  KEY `__DB_PREFIX__users_reset_guid_idx` (`reset_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
