-- Product Store: account status fields for existing PostgreSQL installs.
ALTER TABLE __DB_PREFIX__users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL;
ALTER TABLE __DB_PREFIX__users ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE __DB_PREFIX__users ADD COLUMN IF NOT EXISTS activation_guid VARCHAR(64) NULL;
ALTER TABLE __DB_PREFIX__users ADD COLUMN IF NOT EXISTS deactivation_guid VARCHAR(64) NULL;
ALTER TABLE __DB_PREFIX__users ADD COLUMN IF NOT EXISTS reset_guid VARCHAR(64) NULL;
ALTER TABLE __DB_PREFIX__users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL;

CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_activation_guid_idx ON __DB_PREFIX__users (activation_guid);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_deactivation_guid_idx ON __DB_PREFIX__users (deactivation_guid);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_reset_guid_idx ON __DB_PREFIX__users (reset_guid);
