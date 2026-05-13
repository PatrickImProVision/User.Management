-- Product Store: manageable roles (PostgreSQL). Token __DB_PREFIX__ is replaced at install time.
CREATE TABLE IF NOT EXISTS __DB_PREFIX__roles (
  id SERIAL PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  level INTEGER NOT NULL DEFAULT 1,
  is_system BOOLEAN NOT NULL DEFAULT FALSE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL
);

INSERT INTO __DB_PREFIX__roles (slug, name, description, level, is_system, is_active, created_at)
VALUES
  ('guest', 'Guest', 'Public visitor access before login.', 0, TRUE, TRUE, CURRENT_TIMESTAMP),
  ('user', 'User', 'Standard member access to account and profile features.', 1, TRUE, TRUE, CURRENT_TIMESTAMP),
  ('analyst', 'Analyst', 'Reviews reports and data-focused workflows when enabled.', 2, FALSE, FALSE, CURRENT_TIMESTAMP),
  ('author', 'Author', 'Creates owned content drafts when content features are enabled.', 3, TRUE, TRUE, CURRENT_TIMESTAMP),
  ('editor', 'Editor', 'Edits and organizes content when content features are enabled.', 4, FALSE, FALSE, CURRENT_TIMESTAMP),
  ('reviewer', 'Reviewer', 'Reviews and approves content before publication when enabled.', 5, FALSE, FALSE, CURRENT_TIMESTAMP),
  ('moderator', 'Moderator', 'Moderates community content and user activity when enabled.', 6, TRUE, TRUE, CURRENT_TIMESTAMP),
  ('support', 'Support', 'Assists users and support workflows when enabled.', 7, FALSE, FALSE, CURRENT_TIMESTAMP),
  ('administrator', 'Administrator', 'Manages users, roles, and settings below administrator level.', 8, TRUE, TRUE, CURRENT_TIMESTAMP),
  ('manager', 'Manager', 'Manages administrator-level operations below owner level.', 9, FALSE, FALSE, CURRENT_TIMESTAMP),
  ('owner', 'Owner', 'Full application ownership and highest-level role management.', 10, TRUE, TRUE, CURRENT_TIMESTAMP)
ON CONFLICT (slug) DO UPDATE SET
  name = EXCLUDED.name,
  description = EXCLUDED.description,
  level = EXCLUDED.level,
  is_system = EXCLUDED.is_system,
  is_active = EXCLUDED.is_active;
