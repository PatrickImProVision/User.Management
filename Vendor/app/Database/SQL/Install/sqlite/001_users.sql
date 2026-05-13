-- Product Store: core tables (SQLite). Token __DB_PREFIX__ is replaced at install time (may be empty).
CREATE TABLE IF NOT EXISTS __DB_PREFIX__users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  profile_image TEXT,
  is_active INTEGER NOT NULL DEFAULT 0,
  activation_guid TEXT,
  deactivation_guid TEXT,
  reset_guid TEXT,
  last_login_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_activation_guid_idx ON __DB_PREFIX__users (activation_guid);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_deactivation_guid_idx ON __DB_PREFIX__users (deactivation_guid);
CREATE INDEX IF NOT EXISTS __DB_PREFIX__users_reset_guid_idx ON __DB_PREFIX__users (reset_guid);
