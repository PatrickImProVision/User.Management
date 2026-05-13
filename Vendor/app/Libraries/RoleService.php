<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

final class RoleService
{
    private const OWNER = 'owner';
    private const ADMINISTRATOR = 'administrator';
    private const GUEST = 'guest';

    /** @var array<string, array{name: string, description: string, level: int, is_active: bool}> */
    private const DEFAULT_ROLES = [
        'guest'         => ['name' => 'Guest', 'description' => 'Public visitor access before login.', 'level' => 0, 'is_active' => true],
        'user'          => ['name' => 'User', 'description' => 'Standard member access to account and profile features.', 'level' => 1, 'is_active' => true],
        'author'        => ['name' => 'Author', 'description' => 'Creates owned content drafts when content features are enabled.', 'level' => 3, 'is_active' => true],
        'moderator'     => ['name' => 'Moderator', 'description' => 'Moderates community content and user activity when enabled.', 'level' => 6, 'is_active' => true],
        'administrator' => ['name' => 'Administrator', 'description' => 'Manages users, roles, and settings below administrator level.', 'level' => 8, 'is_active' => true],
        'owner'         => ['name' => 'Owner', 'description' => 'Full application ownership and highest-level role management.', 'level' => 10, 'is_active' => true],
    ];

    /** @var array<string, array{name: string, description: string, level: int, is_active: bool}> */
    private const OPTIONAL_ROLES = [
        'manager'  => ['name' => 'Manager', 'description' => 'Manages administrator-level operations below owner level.', 'level' => 9, 'is_active' => false],
        'editor'   => ['name' => 'Editor', 'description' => 'Edits and organizes content when content features are enabled.', 'level' => 4, 'is_active' => false],
        'reviewer' => ['name' => 'Reviewer', 'description' => 'Reviews and approves content before publication when enabled.', 'level' => 5, 'is_active' => false],
        'support'  => ['name' => 'Support', 'description' => 'Assists users and support workflows when enabled.', 'level' => 7, 'is_active' => false],
        'analyst'  => ['name' => 'Analyst', 'description' => 'Reviews reports and data-focused workflows when enabled.', 'level' => 2, 'is_active' => false],
    ];

    private static bool $baseRolesEnsured = false;

    /** @var list<array<string, mixed>>|null */
    private static ?array $roleRowsCache = null;

    /** @var array<string, array<string, mixed>> */
    private static array $roleBySlugCache = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function listRoles(): array
    {
        return $this->cachedRoleRows();
    }

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        $this->ensureBaseRoles();

        $options = [];
        foreach ($this->cachedRoleRows() as $role) {
            if (! $this->booleanField($role['is_active'] ?? false) || (string) $role['slug'] === self::GUEST) {
                continue;
            }

            $options[(string) $role['slug']] = (string) $role['name'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function assignableRoleOptionsFor(string $actorSlug): array
    {
        $this->ensureBaseRoles();

        $actorLevel = $this->roleLevel($actorSlug);
        if ($actorLevel === null) {
            return [];
        }

        $options = [];
        foreach ($this->cachedRoleRows() as $role) {
            if (
                ! $this->booleanField($role['is_active'] ?? false)
                || (string) $role['slug'] === self::GUEST
                || (int) ($role['level'] ?? 0) >= $actorLevel
            ) {
                continue;
            }

            $options[(string) $role['slug']] = (string) $role['name'];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public function effectiveRoleNames(string $slug): array
    {
        $this->ensureBaseRoles();

        $current = $this->findRole($slug);
        if (! is_array($current) || ! $this->booleanField($current['is_active'] ?? false)) {
            return ['Inactive or missing role'];
        }

        $level = (int) ($current['level'] ?? 1);

        $names = [];
        foreach ($this->cachedRoleRows() as $row) {
            if (! $this->booleanField($row['is_active'] ?? false) || (int) ($row['level'] ?? 0) > $level) {
                continue;
            }

            $names[] = (string) $row['name'];
        }

        return $names !== [] ? $names : ['User'];
    }

    public function roleExists(string $slug): bool
    {
        $this->ensureBaseRoles();

        $role = $this->findRole($slug);

        return $role !== null
            && $this->booleanField($role['is_active'] ?? false)
            && (string) ($role['slug'] ?? '') !== self::GUEST;
    }

    public function roleName(string $slug): string
    {
        $this->ensureBaseRoles();

        $role = $this->findRole($slug);
        if (is_array($role)) {
            return (string) $role['name'];
        }

        return ucfirst($slug !== '' ? $slug : 'user');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRole(string $slug): ?array
    {
        $this->ensureBaseRoles();

        $this->cachedRoleRows();

        return self::$roleBySlugCache[$slug] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRoleById(int $id): ?array
    {
        $this->ensureBaseRoles();

        foreach ($this->cachedRoleRows() as $role) {
            if ((int) ($role['id'] ?? 0) === $id) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRoleRow(array $row): array
    {
        $row['description'] = (string) ($row['description'] ?? '');
        $row['is_system'] = $this->booleanField($row['is_system'] ?? false);
        $row['is_active'] = $this->booleanField($row['is_active'] ?? false);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cachedRoleRows(): array
    {
        $this->ensureBaseRoles();

        if (self::$roleRowsCache !== null) {
            return self::$roleRowsCache;
        }

        $rows = AppDatabase::connection()
            ->table('roles')
            ->orderBy('level', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        self::$roleRowsCache = [];
        self::$roleBySlugCache = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeRoleRow($row);
            self::$roleRowsCache[] = $normalized;
            self::$roleBySlugCache[(string) $normalized['slug']] = $normalized;
        }

        return self::$roleRowsCache;
    }

    private function clearRoleCache(): void
    {
        self::$roleRowsCache = null;
        self::$roleBySlugCache = [];
    }

    private function booleanField(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 't', 'true', 'yes', 'on'], true);
        }

        return false;
    }


    public function isAdministrator(string $slug): bool
    {
        return $this->hasLevelAtLeast($slug, self::ADMINISTRATOR);
    }

    public function hasLevelAtLeast(string $slug, string $requiredSlug): bool
    {
        $role = $this->findRole($slug);
        $required = $this->findRole($requiredSlug);

        if (
            $role === null
            || $required === null
            || ! $this->booleanField($role['is_active'] ?? false)
            || ! $this->booleanField($required['is_active'] ?? false)
        ) {
            return false;
        }

        return (int) ($role['level'] ?? -1) >= (int) ($required['level'] ?? PHP_INT_MAX);
    }

    public function canChangeUserRole(string $actorSlug, string $currentTargetSlug, string $newTargetSlug): bool
    {
        if (! $this->isAdministrator($actorSlug)) {
            return false;
        }

        $actorLevel = $this->roleLevel($actorSlug);
        $currentTargetLevel = $this->roleLevel($currentTargetSlug, false);
        $newTargetLevel = $this->roleLevel($newTargetSlug);

        if ($actorLevel === null || $currentTargetLevel === null || $newTargetLevel === null) {
            return false;
        }

        return $actorLevel > $currentTargetLevel && $actorLevel > $newTargetLevel;
    }

    public function canManageCurrentUserRole(string $actorSlug, string $currentTargetSlug): bool
    {
        if (! $this->isAdministrator($actorSlug)) {
            return false;
        }

        $actorLevel = $this->roleLevel($actorSlug);
        $targetLevel = $this->roleLevel($currentTargetSlug, false);

        return $actorLevel !== null && $targetLevel !== null && $actorLevel > $targetLevel;
    }

    private function roleLevel(string $slug, bool $requireActive = true): ?int
    {
        $role = $this->findRole($slug);
        if ($role === null || ($requireActive && ! $this->booleanField($role['is_active'] ?? false))) {
            return null;
        }

        return (int) ($role['level'] ?? 0);
    }

    /**
     * @param array{slug: string, name: string, description?: string|null, level: int|string, is_active?: bool|string|null} $data
     */
    public function saveRole(array $data): ?string
    {
        $this->ensureBaseRoles();

        $slug = strtolower(trim($data['slug']));
        $slug = preg_replace('/[^a-z0-9_]/', '_', $slug) ?? '';
        $slug = trim($slug, '_');
        $name = trim($data['name']);
        $description = trim((string) ($data['description'] ?? ''));
        $level = (int) $data['level'];
        $isActive = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOL);

        if ($slug === '' || strlen($slug) > 50) {
            return 'Role slug is required and must be 50 characters or fewer.';
        }

        if ($name === '' || strlen($name) > 100) {
            return 'Role name is required and must be 100 characters or fewer.';
        }

        if ($description === '' || strlen($description) > 255) {
            return 'Role description is required and must be 255 characters or fewer.';
        }

        if ($level < 0 || $level > 10) {
            return 'Role level must be between 0 and 10.';
        }

        $db = AppDatabase::connection();
        $existing = $db->table('roles')->where('slug', $slug)->get()->getRowArray();
        if (is_array($existing) && $this->booleanField($existing['is_system'] ?? false)) {
            return 'System roles are protected.';
        }

        $row = [
            'slug'        => $slug,
            'name'        => $name,
            'description' => $description,
            'level'       => $level,
            'is_active'   => $isActive,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        try {
            if (is_array($existing)) {
                $db->table('roles')->where('slug', $slug)->update($row);
            } else {
                $row['is_system'] = false;
                $row['created_at'] = date('Y-m-d H:i:s');
                $db->table('roles')->insert($row);
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        $this->clearRoleCache();

        return null;
    }

    public function deleteRole(string $slug): ?string
    {
        $this->ensureBaseRoles();

        if (array_key_exists($slug, self::DEFAULT_ROLES)) {
            return 'Basic roles cannot be deleted.';
        }

        $db = AppDatabase::connection();
        if ($db->table('users')->where('role', $slug)->countAllResults() > 0) {
            return 'Role is assigned to one or more users.';
        }

        try {
            $db->table('roles')->where('slug', $slug)->delete();
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        $this->clearRoleCache();

        return null;
    }

    public function ensureBaseRoles(): void
    {
        if (self::$baseRolesEnsured) {
            return;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('roles')) {
            $this->createRolesTable($db);
            if (! $db->tableExists('roles')) {
                return;
            }
        }

        $this->ensureRoleColumns($db);

        $existingRows = [];
        foreach ($db->table('roles')->select('slug, description, level')->get()->getResultArray() as $existing) {
            $existingRows[(string) $existing['slug']] = $existing;
        }

        $inserted = false;
        foreach (self::DEFAULT_ROLES as $slug => $role) {
            if (isset($existingRows[$slug])) {
                continue;
            }

            $row = [
                'slug'        => $slug,
                'name'        => $role['name'],
                'description' => $role['description'],
                'level'       => $role['level'],
                'is_system'   => true,
                'is_active'   => $role['is_active'],
            ];

            $row['created_at'] = date('Y-m-d H:i:s');
            $row['updated_at'] = null;
            $db->table('roles')->insert($row);
            $inserted = true;
        }

        foreach (self::OPTIONAL_ROLES as $slug => $role) {
            if (isset($existingRows[$slug])) {
                continue;
            }

            $db->table('roles')->insert([
                'slug'        => $slug,
                'name'        => $role['name'],
                'description' => $role['description'],
                'level'       => $role['level'],
                'is_system'   => false,
                'is_active'   => $role['is_active'],
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => null,
            ]);
            $inserted = true;
        }

        $descriptionUpdated = $this->fillMissingRoleDescriptions($db, $existingRows);

        self::$baseRolesEnsured = true;
        if ($inserted || $descriptionUpdated) {
            $this->clearRoleCache();
        }
    }

    /**
     * @param array<string, array<string, mixed>> $existingRows
     */
    private function fillMissingRoleDescriptions(BaseConnection $db, array $existingRows): bool
    {
        $updated = false;
        foreach (self::DEFAULT_ROLES + self::OPTIONAL_ROLES as $slug => $role) {
            if (! isset($existingRows[$slug])) {
                continue;
            }

            if (trim((string) ($existingRows[$slug]['description'] ?? '')) !== '') {
                continue;
            }

            $db->table('roles')->where('slug', $slug)->update([
                'description' => $role['description'],
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $updated = true;
        }

        foreach ($existingRows as $slug => $row) {
            if (isset(self::DEFAULT_ROLES[$slug]) || isset(self::OPTIONAL_ROLES[$slug])) {
                continue;
            }

            if (trim((string) ($row['description'] ?? '')) !== '') {
                continue;
            }

            $db->table('roles')->where('slug', $slug)->update([
                'description' => 'Custom role at level ' . (int) ($row['level'] ?? 0) . '.',
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $updated = true;
        }

        return $updated;
    }

    private function ensureRoleColumns(BaseConnection $db): void
    {
        try {
            $fields = $db->getFieldNames($db->prefixTable('roles'));
        } catch (Throwable) {
            return;
        }

        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'roles');

        $columnSql = [];
        if (! in_array('is_active', $fields, true)) {
            $columnSql[] = match ($db->DBDriver ?? '') {
                'Postgre' => 'ALTER TABLE ' . $table . ' ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE',
                'MySQLi'  => 'ALTER TABLE ' . $table . ' ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1',
                'SQLite3' => 'ALTER TABLE ' . $table . ' ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1',
                default   => '',
            };
        }

        if (! in_array('description', $fields, true)) {
            $columnSql[] = match ($db->DBDriver ?? '') {
                'Postgre' => 'ALTER TABLE ' . $table . ' ADD COLUMN IF NOT EXISTS description VARCHAR(255) NOT NULL DEFAULT \'\'',
                'MySQLi'  => 'ALTER TABLE ' . $table . ' ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `name`',
                'SQLite3' => 'ALTER TABLE ' . $table . ' ADD COLUMN description TEXT NOT NULL DEFAULT \'\'',
                default   => '',
            };
        }

        foreach ($columnSql as $sql) {
            if ($sql === '') {
                continue;
            }

            try {
                $db->query($sql);
            } catch (Throwable) {
                // The column may already exist on restored or partially upgraded databases.
            }
        }
    }

    private function createRolesTable(BaseConnection $db): void
    {
        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'roles');
        $sql = match ($db->DBDriver ?? '') {
            'Postgre' => 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id SERIAL PRIMARY KEY,
                slug VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) NOT NULL DEFAULT \'\',
                level INTEGER NOT NULL DEFAULT 1,
                is_system BOOLEAN NOT NULL DEFAULT FALSE,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
            )',
            'MySQLi' => 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(50) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `description` VARCHAR(255) NOT NULL DEFAULT \'\',
                `level` INT NOT NULL DEFAULT 1,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `' . (string) ($db->DBPrefix ?? '') . 'roles_slug_unique` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
            'SQLite3' => 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT \'\',
                level INTEGER NOT NULL DEFAULT 1,
                is_system INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT
            )',
            default => '',
        };

        if ($sql !== '') {
            $db->query($sql);
        }
    }

    private function quoteTable(BaseConnection $db, string $table): string
    {
        if (($db->DBDriver ?? '') === 'MySQLi') {
            return '`' . str_replace('`', '``', $table) . '`';
        }

        return '"' . str_replace('"', '""', $table) . '"';
    }
}
