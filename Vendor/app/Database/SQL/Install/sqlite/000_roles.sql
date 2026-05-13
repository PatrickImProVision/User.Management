-- Product Store: manageable roles (SQLite). Token __DB_PREFIX__ is replaced at install time.
CREATE TABLE IF NOT EXISTS __DB_PREFIX__roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  level INTEGER NOT NULL DEFAULT 1,
  is_system INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

INSERT INTO __DB_PREFIX__roles (slug, name, description, level, is_system, is_active, created_at) VALUES
  ('guest', 'Guest', 'Public visitor access before login.', 0, 1, 1, CURRENT_TIMESTAMP),
  ('user', 'User', 'Standard member access to account and profile features.', 1, 1, 1, CURRENT_TIMESTAMP),
  ('analyst', 'Analyst', 'Reviews reports and data-focused workflows when enabled.', 2, 0, 0, CURRENT_TIMESTAMP),
  ('author', 'Author', 'Creates owned content drafts when content features are enabled.', 3, 1, 1, CURRENT_TIMESTAMP),
  ('editor', 'Editor', 'Edits and organizes content when content features are enabled.', 4, 0, 0, CURRENT_TIMESTAMP),
  ('reviewer', 'Reviewer', 'Reviews and approves content before publication when enabled.', 5, 0, 0, CURRENT_TIMESTAMP),
  ('moderator', 'Moderator', 'Moderates community content and user activity when enabled.', 6, 1, 1, CURRENT_TIMESTAMP),
  ('support', 'Support', 'Assists users and support workflows when enabled.', 7, 0, 0, CURRENT_TIMESTAMP),
  ('administrator', 'Administrator', 'Manages users, roles, and settings below administrator level.', 8, 1, 1, CURRENT_TIMESTAMP),
  ('manager', 'Manager', 'Manages administrator-level operations below owner level.', 9, 0, 0, CURRENT_TIMESTAMP),
  ('owner', 'Owner', 'Full application ownership and highest-level role management.', 10, 1, 1, CURRENT_TIMESTAMP)
ON CONFLICT(slug) DO UPDATE SET
  name = excluded.name,
  description = excluded.description,
  level = excluded.level,
  is_system = excluded.is_system,
  is_active = excluded.is_active;
