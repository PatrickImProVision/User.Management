-- Product Store: core tables (PostgreSQL). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(191) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  profile_image VARCHAR(255) NULL,
  is_active BOOLEAN NOT NULL DEFAULT FALSE,
  activation_guid VARCHAR(64) NULL,
  deactivation_guid VARCHAR(64) NULL,
  reset_guid VARCHAR(64) NULL,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_activation_guid_idx ON __DB_PREFIX__users (activation_guid);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_deactivation_guid_idx ON __DB_PREFIX__users (deactivation_guid);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_reset_guid_idx ON __DB_PREFIX__users (reset_guid);
