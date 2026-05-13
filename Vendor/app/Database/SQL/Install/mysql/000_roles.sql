-- Product Store: manageable roles (MySQL / MariaDB). Token __DB_PREFIX__ is replaced at install time.
CREATE TABLE IF NOT EXISTS `__DB_PREFIX__roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `level` INT NOT NULL DEFAULT 1,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `__DB_PREFIX__roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `__DB_PREFIX__roles` (`slug`, `name`, `description`, `level`, `is_system`, `is_active`, `created_at`) VALUES
  ('guest', 'Guest', 'Public visitor access before login.', 0, 1, 1, NOW()),
  ('user', 'User', 'Standard member access to account and profile features.', 1, 1, 1, NOW()),
  ('analyst', 'Analyst', 'Reviews reports and data-focused workflows when enabled.', 2, 0, 0, NOW()),
  ('author', 'Author', 'Creates owned content drafts when content features are enabled.', 3, 1, 1, NOW()),
  ('editor', 'Editor', 'Edits and organizes content when content features are enabled.', 4, 0, 0, NOW()),
  ('reviewer', 'Reviewer', 'Reviews and approves content before publication when enabled.', 5, 0, 0, NOW()),
  ('moderator', 'Moderator', 'Moderates community content and user activity when enabled.', 6, 1, 1, NOW()),
  ('support', 'Support', 'Assists users and support workflows when enabled.', 7, 0, 0, NOW()),
  ('administrator', 'Administrator', 'Manages users, roles, and settings below administrator level.', 8, 1, 1, NOW()),
  ('manager', 'Manager', 'Manages administrator-level operations below owner level.', 9, 0, 0, NOW()),
  ('owner', 'Owner', 'Full application ownership and highest-level role management.', 10, 1, 1, NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `level` = VALUES(`level`),
  `is_system` = VALUES(`is_system`),
  `is_active` = VALUES(`is_active`);
