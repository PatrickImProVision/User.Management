<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database as DbConfig;
use Throwable;

/**
 * Installation / uninstallation operations (Installation Manager).
 */
final class InstallationService
{
    /** @var list<string> */
    public const SUPPORTED_DRIVERS = ['MySQLi', 'Postgre', 'SQLite3', 'SQLSRV', 'OCI8'];

    /**
     * Map DBDriver to folder under app/Database/SQL/Install/.
     */
    public static function sqlPresetFolder(string $dbDriver): ?string
    {
        return match ($dbDriver) {
            'MySQLi'  => 'mysql',
            'Postgre' => 'pgsql',
            'SQLite3' => 'sqlite',
            default   => null,
        };
    }

    /**
     * Sanitize table prefix for SQL identifiers (letters, digits, underscore; max 64).
     */
    public static function normalizeDbPrefix(string $raw): string
    {
        $p = preg_replace('/[^A-Za-z0-9_]/', '', $raw) ?? '';

        return substr($p, 0, 64);
    }

    /**
     * @param array{DBDriver:string,hostname?:string,port?:int|string,database?:string,username?:string,password?:string,schema?:string,foreignKeys?:bool,DBPrefix?:string} $cfg
     */
    public function testConnection(array $cfg): ?string
    {
        $driver = $cfg['DBDriver'] ?? '';
        if (! in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            return 'Unsupported database driver.';
        }

        $defaults = config(DbConfig::class)->default;
        $merged   = array_merge($defaults, $cfg);
        $merged   = self::normalizePostgresClientEncoding($merged);
        $merged['DBDebug'] = false;
        if ($driver === 'Postgre' && empty($merged['connect_timeout'])) {
            $merged['connect_timeout'] = '5';
        }

        try {
            // Trial connection only: merged POST data overrides Config\Database defaults.
            $db = \CodeIgniter\Database\Config::connect($merged, false);
            $db->query(
                $driver === 'Postgre' ? 'SELECT 1 AS ok' : ($driver === 'SQLite3' ? 'SELECT 1' : 'SELECT 1 AS ok'),
            );
            $db->close();

            return null;
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if ($driver === 'Postgre' && str_contains(strtolower($message), 'timed out')) {
                return 'Connection timed out. Check the PostgreSQL host and port. Some providers use a custom port instead of 5432.';
            }

            return $message;
        }
    }

    /**
     * PostgreSQL pg_set_client_encoding() does not accept MySQL's "utf8mb4".
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function normalizePostgresClientEncoding(array $config): array
    {
        if (($config['DBDriver'] ?? '') !== 'Postgre') {
            return $config;
        }

        $cs = (string) ($config['charset'] ?? 'UTF8');
        if (strcasecmp($cs, 'utf8mb4') === 0 || strcasecmp($cs, 'utf8') === 0) {
            $config['charset'] = 'UTF8';
        }

        return $config;
    }

    /**
     * Persists database (and optional app base URL) to PHP config files.
     *
     * @param array<string, scalar|null> $dbRow keys: DBDriver, hostname, port, database, username, password, DBPrefix?, schema?, foreignKeys?
     */
    public function persistConfigFiles(array $dbRow, ?string $baseURL = null): void
    {
        $driver = (string) ($dbRow['DBDriver'] ?? 'MySQLi');
        $charset  = $driver === 'Postgre' ? 'UTF8' : 'utf8mb4';
        $collat   = $driver === 'Postgre' ? '' : 'utf8mb4_general_ci';

        $default = [
            'DSN'          => '',
            'hostname'     => (string) ($dbRow['hostname'] ?? 'localhost'),
            'username'     => (string) ($dbRow['username'] ?? ''),
            'password'     => (string) ($dbRow['password'] ?? ''),
            'database'     => (string) ($dbRow['database'] ?? ''),
            'DBDriver'     => $driver,
            'DBPrefix'     => (string) ($dbRow['DBPrefix'] ?? ''),
            'pConnect'     => false,
            'DBDebug'      => true,
            'charset'      => $charset,
            'DBCollat'     => $collat,
            'swapPre'      => '',
            'encrypt'      => false,
            'compress'     => false,
            'strictOn'     => false,
            'failover'     => [],
            'port'         => (int) ($dbRow['port'] ?? 3306),
            'numberNative' => false,
            'foundRows'    => false,
            'schema'       => (string) ($dbRow['schema'] ?? 'public'),
            'foreignKeys'  => ! empty($dbRow['foreignKeys']),
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];

        if ($driver === 'Postgre') {
            $default['DBCollat'] = '';
        }

        $this->persistDatabaseConfig($default);

        if ($baseURL !== null && $baseURL !== '') {
            $this->persistAppBaseUrl($baseURL);
        }
    }

    /**
     * @param array<string, mixed> $default
     */
    private function persistDatabaseConfig(array $default): void
    {
        $path = APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Database.php';
        $php  = file_get_contents($path);
        if ($php === false) {
            throw new \RuntimeException('Could not read app/Config/Database.php.');
        }

        $replacement = 'public array $default = ' . $this->exportPhpArray($default, 4) . ';';
        $count = 0;
        $updated = preg_replace_callback(
            '/public\s+array\s+\$default\s*=\s*\[[\s\S]*?\n    \];/m',
            static fn (): string => $replacement,
            $php,
            1,
            $count,
        );
        if ($updated === null || $count === 0) {
            throw new \RuntimeException('Could not update app/Config/Database.php.');
        }

        if ($updated !== $php) {
            file_put_contents($path, $updated, LOCK_EX);
        }
    }

    private function persistAppBaseUrl(string $baseURL): void
    {
        if (! str_ends_with($baseURL, '/')) {
            $baseURL .= '/';
        }

        $path = APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'App.php';
        $php  = file_get_contents($path);
        if ($php === false) {
            throw new \RuntimeException('Could not read app/Config/App.php.');
        }

        $replacement = 'public string $baseURL = ' . var_export($baseURL, true) . ';';
        $count = 0;
        $updated = preg_replace_callback(
            '/public\s+string\s+\$baseURL\s*=\s*[^;]+;/',
            static fn (): string => $replacement,
            $php,
            1,
            $count,
        );
        if ($updated === null || $count === 0) {
            throw new \RuntimeException('Could not update app/Config/App.php.');
        }

        if ($updated !== $php) {
            file_put_contents($path, $updated, LOCK_EX);
        }
    }

    /**
     * @param array<string, mixed> $array
     */
    private function exportPhpArray(array $array, int $baseIndent): string
    {
        $indent = str_repeat(' ', $baseIndent);
        $childIndent = str_repeat(' ', $baseIndent + 4);
        $lines = ['['];
        foreach ($array as $key => $value) {
            $exportKey = var_export($key, true);
            if (is_array($value)) {
                $lines[] = $childIndent . $exportKey . ' => ' . $this->exportPhpArray($value, $baseIndent + 4) . ',';

                continue;
            }

            $lines[] = $childIndent . $exportKey . ' => ' . var_export($value, true) . ',';
        }
        $lines[] = $indent . ']';

        return implode(PHP_EOL, $lines);
    }

    /**
     * Restore app/Config/Database.php from the bundled template (Product Store defaults).
     *
     * @return string|null error message
     */
    public function resetDatabaseConfigToShipped(): ?string
    {
        $template = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'Database.php';
        if (! is_readable($template)) {
            return 'Database.php template is missing or not readable.';
        }

        $contents = file_get_contents($template);
        if ($contents === false || $contents === '') {
            return 'Could not read Database.php template.';
        }

        $target = APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Database.php';
        if (file_put_contents($target, $contents, LOCK_EX) === false) {
            return 'Could not write app/Config/Database.php (check permissions).';
        }

        return null;
    }

    /**
     * Runs ordered *.sql from app/Database/SQL/Install/{mysql|pgsql|sqlite}/.
     *
     * @return string|null error message
     */
    public function runPresetSql(): ?string
    {
        $dbConfig = config(DbConfig::class);
        $driver   = $dbConfig->default['DBDriver'] ?? '';
        $folder   = self::sqlPresetFolder($driver);

        if ($folder === null) {
            return 'No bundled preset SQL for driver "' . $driver . '". Configure tables manually, then continue.';
        }

        $dir = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'SQL' . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR . $folder;
        if (! is_dir($dir)) {
            return 'Preset SQL directory missing: ' . $dir;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        natsort($files);

        try {
            $db = AppDatabase::connection(false);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        $prefix = (string) ($dbConfig->default['DBPrefix'] ?? '');

        foreach ($files as $file) {
            $sql = (string) file_get_contents($file);
            $sql = trim($sql);
            if ($sql === '') {
                continue;
            }

            $sql = str_replace('__DB_PREFIX__', $prefix, $sql);

            $err = $this->executeSqlScript($db, $sql);
            if ($err !== null) {
                return basename($file) . ': ' . $err;
            }
        }

        return null;
    }

    /**
     * Uninstall backups written under writable/backup/ (see {@see backupDatabaseToWritable()}).
     * Not related to bundled SQL under app/Database/SQL/Install/.
     *
     * @return list<array{basename: string, mtime: int, bytes: int}>
     */
    public function listWritableUninstallBackups(): array
    {
        $dir = WRITEPATH . 'backup' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            return [];
        }

        $paths = glob($dir . 'uninstall_*.sql') ?: [];
        $out   = [];
        foreach ($paths as $p) {
            if (! is_file($p) || ! is_readable($p)) {
                continue;
            }
            $bn = basename($p);
            if (! preg_match('/^uninstall_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $bn)) {
                continue;
            }
            $out[] = [
                'basename' => $bn,
                'mtime'    => (int) filemtime($p),
                'bytes'    => (int) filesize($p),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    /**
     * Delete one uninstall backup from writable/backup/.
     *
     * @return string|null error message
     */
    public function deleteWritableUninstallBackup(string $requestedBasename): ?string
    {
        $path = $this->resolveWritableUninstallBackupPath($requestedBasename);
        if ($path === null) {
            return 'Backup file not found or not allowed.';
        }

        if (! unlink($path)) {
            return 'Could not delete backup file.';
        }

        return null;
    }

    /**
     * Execute a single uninstall backup file against the current configured database.
     * Uses only writable/backup/ — does not read app/Database/SQL/Install/.
     *
     * @return string|null error message
     */
    public function restoreFromWritableUninstallBackup(string $requestedBasename): ?string
    {
        $path = $this->resolveWritableUninstallBackupPath($requestedBasename);
        if ($path === null) {
            return 'Backup file not found or not allowed.';
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            return 'Could not read backup file.';
        }
        $sql = preg_replace("/^\xEF\xBB\xBF/", '', $sql) ?? $sql;
        $sql = trim($sql);
        if ($sql === '') {
            return 'Backup file is empty.';
        }

        try {
            $db = AppDatabase::connection(false);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        if (($db->DBDriver ?? '') === 'Postgre') {
            $sql = $this->stripPostgreUninstallBackupDropStatements($sql);
        }

        $prefixError = $this->validateBackupPrefixMatchesConnection($db, $sql);
        if ($prefixError !== null) {
            return $prefixError;
        }

        $missingTablesError = $this->ensureRestoreTargetTablesExist($db, $sql);
        if ($missingTablesError !== null) {
            return $missingTablesError;
        }

        $targets = $this->extractInsertTargetTables($sql);
        $prepareError = $this->preparePostgresRestoreTablesForReplay($db, $targets);
        if ($prepareError !== null) {
            return $prepareError;
        }

        $accountFieldsError = $this->ensurePostgresUserAccountFields($db);
        if ($accountFieldsError !== null) {
            return $accountFieldsError;
        }

        $err = $this->executeSqlScript($db, $sql);
        if ($err !== null) {
            return $err;
        }

        return $this->syncPostgresSequencesAfterRestore($db, $targets);
    }

    public function hasExistingUserAccounts(): bool
    {
        try {
            $db = AppDatabase::connection(false);
        } catch (Throwable) {
            return false;
        }

        try {
            $table = (string) ($db->DBPrefix ?? '') . 'users';
            if (! in_array($table, $this->listAllPhysicalTables($db), true)) {
                return false;
            }

            $row = $db->query('SELECT COUNT(*) AS total FROM ' . $this->quoteTableIdentifier($db, $table))->getRowArray();

            return (int) ($row['total'] ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function ensureAdministratorAccountsActive(): ?string
    {
        try {
            $db = AppDatabase::connection(false);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        try {
            $table = (string) ($db->DBPrefix ?? '') . 'users';
            if (! in_array($table, $this->listAllPhysicalTables($db), true)) {
                return null;
            }

            $err = $this->ensurePostgresUserAccountFields($db);
            if ($err !== null) {
                return $err;
            }

            $db->table('users')->whereIn('role', ['owner', 'administrator'])->update([
                'is_active'       => true,
                'activation_guid' => null,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function ensurePostgresUserAccountFields(BaseConnection $db): ?string
    {
        if (($db->DBDriver ?? '') !== 'Postgre') {
            return null;
        }

        $file = APPPATH . 'Database' . DIRECTORY_SEPARATOR . 'SQL' . DIRECTORY_SEPARATOR . 'Install'
            . DIRECTORY_SEPARATOR . 'pgsql' . DIRECTORY_SEPARATOR . '002_users_account_fields.sql';
        if (! is_readable($file)) {
            return 'PostgreSQL users account field source SQL is missing.';
        }

        $sql = (string) file_get_contents($file);
        $sql = trim(str_replace('__DB_PREFIX__', (string) ($db->DBPrefix ?? ''), $sql));
        if ($sql === '') {
            return null;
        }

        return $this->executeSqlScript($db, $sql);
    }

    private function quoteTableIdentifier(BaseConnection $db, string $table): string
    {
        return match ($db->DBDriver) {
            'MySQLi' => '`' . str_replace('`', '``', $table) . '`',
            default  => '"' . str_replace('"', '""', $table) . '"',
        };
    }

    /**
     * Older uninstall backups used DROP TABLE … CASCADE with no CREATE; replaying that removes
     * tables before INSERT and breaks restore. Strip those lines so data-only replay works after
     * preset SQL (or an existing schema).
     */
    private function stripPostgreUninstallBackupDropStatements(string $sql): string
    {
        $sql = preg_replace('/^\s*DROP\s+TABLE\s+(IF\s+EXISTS\s+)?[^;]+;\s*\R?/mi', '', $sql) ?? $sql;

        return $sql;
    }

    private function validateBackupPrefixMatchesConnection(BaseConnection $db, string $sql): ?string
    {
        $backupPrefix = $this->extractBackupPrefix($sql);
        if ($backupPrefix === null) {
            return null;
        }

        $currentPrefix = (string) ($db->DBPrefix ?? '');
        if ($backupPrefix === $currentPrefix) {
            return null;
        }

        return 'Backup was created with DBPrefix "' . ($backupPrefix === '' ? '(empty)' : $backupPrefix)
            . '", but the current database configuration uses DBPrefix "'
            . ($currentPrefix === '' ? '(empty)' : $currentPrefix)
            . '". Go back to database setup and use the same table prefix as the backup before restoring.';
    }

    private function extractBackupPrefix(string $sql): ?string
    {
        if (! preg_match('/^-- DBPrefix \(Config\\\\Database\):\s*(.+)$/m', $sql, $m)) {
            return null;
        }

        $prefix = trim($m[1]);

        return $prefix === '(empty)' ? '' : $prefix;
    }

    private function ensureRestoreTargetTablesExist(BaseConnection $db, string $sql): ?string
    {
        $targets = $this->extractInsertTargetTables($sql);
        if ($targets === []) {
            return null;
        }

        $existing = $this->listAllPhysicalTables($db);
        $createdByBackup = $this->extractCreateTargetTables($sql);
        $missing  = array_values(array_diff($targets, $existing, $createdByBackup));
        if ($missing === []) {
            return null;
        }

        $prefix = (string) ($db->DBPrefix ?? '');

        $schemaErr = $this->runPresetSql();
        if ($schemaErr !== null) {
            return 'Backup restore needs missing table(s): ' . implode(', ', $missing)
                . '. Automatic schema creation failed: ' . $schemaErr;
        }

        $existingAfterSchema = $this->listAllPhysicalTables($db);
        $stillMissing = array_values(array_diff($targets, $existingAfterSchema, $createdByBackup));
        if ($stillMissing === []) {
            return null;
        }

        return 'Backup restore requires missing table(s): ' . implode(', ', $stillMissing)
            . '. Automatic preset SQL ran, but these physical table names still do not exist. Current DBPrefix: "'
            . ($prefix === '' ? '(empty)' : $prefix) . '".';
    }

    /**
     * PostgreSQL uninstall backups are data-only. Preset SQL may have seeded rows, so clear
     * the target tables before replaying backup INSERTs with explicit IDs.
     *
     * @param list<string> $targets
     */
    private function preparePostgresRestoreTablesForReplay(BaseConnection $db, array $targets): ?string
    {
        if (($db->DBDriver ?? '') !== 'Postgre' || $targets === []) {
            return null;
        }

        $existing = $this->listAllPhysicalTables($db);
        $tables = array_values(array_intersect($targets, $existing));
        if ($tables === []) {
            return null;
        }

        $tableList = implode(', ', array_map(
            fn (string $table): string => $this->quoteTableIdentifier($db, $table),
            $tables,
        ));

        try {
            $db->query('TRUNCATE TABLE ' . $tableList . ' RESTART IDENTITY CASCADE');
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Explicit ID INSERTs do not advance PostgreSQL serial sequences.
     *
     * @param list<string> $targets
     */
    private function syncPostgresSequencesAfterRestore(BaseConnection $db, array $targets): ?string
    {
        if (($db->DBDriver ?? '') !== 'Postgre') {
            return null;
        }

        foreach ($targets as $table) {
            try {
                if (! in_array('id', $db->getFieldNames($table), true)) {
                    continue;
                }

                $row = $db->query('SELECT MAX("id") AS max_id FROM ' . $this->quoteTableIdentifier($db, $table))->getRowArray();
                $max = (int) ($row['max_id'] ?? 0);
                if ($max <= 0) {
                    continue;
                }

                $sequence = $db->query(
                    'SELECT pg_get_serial_sequence(' . $db->escape($table) . ', ' . $db->escape('id') . ') AS sequence_name',
                )->getRowArray();
                $sequenceName = (string) ($sequence['sequence_name'] ?? '');
                if ($sequenceName === '') {
                    continue;
                }

                $db->query('SELECT setval(' . $db->escape($sequenceName) . ', ' . $max . ', true)');
            } catch (Throwable $e) {
                return $e->getMessage();
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractInsertTargetTables(string $sql): array
    {
        preg_match_all('/\bINSERT\s+INTO\s+(?:`([^`]+)`|"((?:""|[^"])*)"|([A-Za-z_][A-Za-z0-9_.$]*))/i', $sql, $matches, PREG_SET_ORDER);

        return $this->normalizeSqlTargetMatches($matches);
    }

    /**
     * @return list<string>
     */
    private function extractCreateTargetTables(string $sql): array
    {
        preg_match_all('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`([^`]+)`|"((?:""|[^"])*)"|([A-Za-z_][A-Za-z0-9_.$]*))/i', $sql, $matches, PREG_SET_ORDER);

        return $this->normalizeSqlTargetMatches($matches);
    }

    /**
     * @param list<array<int, string>> $matches
     *
     * @return list<string>
     */
    private function normalizeSqlTargetMatches(array $matches): array
    {
        $tables = [];
        foreach ($matches as $m) {
            $table = $m[1] !== ''
                ? $m[1]
                : (($m[2] ?? '') !== '' ? str_replace('""', '"', $m[2]) : (string) ($m[3] ?? ''));
            if ($table === '') {
                continue;
            }

            if (str_contains($table, '.')) {
                $parts = explode('.', $table);
                $table = (string) end($parts);
            }

            $tables[] = $table;
        }

        return array_values(array_unique($tables));
    }

    private function resolveWritableUninstallBackupPath(string $requestedBasename): ?string
    {
        $bn = basename($requestedBasename);
        if ($bn === '' || $bn === '.' || $bn === '..') {
            return null;
        }
        if (! preg_match('/^uninstall_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $bn)) {
            return null;
        }

        $dir  = WRITEPATH . 'backup' . DIRECTORY_SEPARATOR;
        $full = $dir . $bn;
        if (! is_file($full) || ! is_readable($full)) {
            return null;
        }

        $realDir  = realpath($dir);
        $realFile = realpath($full);
        if ($realDir === false || $realFile === false) {
            return null;
        }

        $normDir  = rtrim(str_replace('\\', '/', $realDir), '/');
        $normFile = str_replace('\\', '/', $realFile);
        if ($normFile !== $normDir && ! str_starts_with($normFile, $normDir . '/')) {
            return null;
        }

        return $realFile;
    }

    private function executeSqlScript(BaseConnection $db, string $sql): ?string
    {
        $driver = $db->DBDriver;

        if ($driver === 'MySQLi') {
            $mysqli = $db->connID;
            if (! $mysqli->multi_query($sql)) {
                return $mysqli->error;
            }
            while ($mysqli->more_results() && $mysqli->next_result()) {
                if ($res = $mysqli->store_result()) {
                    $res->free();
                }
            }

            return null;
        }

        $split = $driver === 'Postgre'
            ? $this->splitPostgresSqlStatements($sql)
            : $this->splitSqlStatements($sql);

        foreach ($split as $stmt) {
            try {
                $result = $db->simpleQuery($stmt);
                if ($result === false) {
                    return 'SQL execution failed.' . $this->postgresQueryErrorSuffix($db);
                }
            } catch (Throwable $e) {
                return $e->getMessage() . $this->postgresQueryErrorSuffix($db);
            }
        }

        return null;
    }

    private function postgresQueryErrorSuffix(BaseConnection $db): string
    {
        if ($db->DBDriver !== 'Postgre') {
            return '';
        }
        $cid = $db->connID;
        if ($cid === false || $cid === null) {
            return '';
        }

        $err = pg_last_error($cid);
        if ($err === false || $err === '') {
            return '';
        }

        return ' — ' . $err;
    }

    /**
     * Parents before children so INSERT replay respects foreign keys.
     *
     * @param list<string> $tables
     * @param list<array{0:string,1:string}> $childParentPairs
     *
     * @return list<string>
     */
    private function topologicalSortTablesByForeignKeys(array $tables, array $childParentPairs): array
    {
        $tables = array_values(array_unique($tables));
        sort($tables, SORT_STRING);
        if ($tables === []) {
            return [];
        }

        $inDegree = [];
        $adj       = [];
        foreach ($tables as $t) {
            $inDegree[$t] = 0;
            $adj[$t]      = [];
        }

        foreach ($childParentPairs as $pair) {
            [$child, $parent] = $pair;
            if ($child === $parent || ! isset($inDegree[$child], $inDegree[$parent])) {
                continue;
            }
            $adj[$parent][] = $child;
            $inDegree[$child]++;
        }

        $queue = [];
        foreach ($tables as $t) {
            if ($inDegree[$t] === 0) {
                $queue[] = $t;
            }
        }
        sort($queue, SORT_STRING);

        $out = [];
        while ($queue !== []) {
            $n = array_shift($queue);
            $out[] = $n;
            $children = $adj[$n] ?? [];
            sort($children, SORT_STRING);
            foreach ($children as $child) {
                $inDegree[$child]--;
                if ($inDegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
            sort($queue, SORT_STRING);
        }

        foreach ($tables as $t) {
            if (! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function listPostgresTablesForDumpOrder(BaseConnection $db): array
    {
        $tables = $this->listPhysicalTablesForApp($db);
        if ($tables === []) {
            return [];
        }

        $rows = $db->query(
            "SELECT tc.table_name AS child_table, ccu.table_name AS parent_table
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON tc.constraint_schema = kcu.constraint_schema AND tc.constraint_name = kcu.constraint_name
             JOIN information_schema.constraint_column_usage AS ccu
               ON tc.constraint_schema = ccu.constraint_schema AND tc.constraint_name = ccu.constraint_name
             WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public'",
        )->getResultArray();

        $pairs = [];
        foreach ($rows as $r) {
            $c = (string) ($r['child_table'] ?? '');
            $p = (string) ($r['parent_table'] ?? '');
            if ($c !== '' && $p !== '') {
                $pairs[] = [$c, $p];
            }
        }

        return $this->topologicalSortTablesByForeignKeys($tables, $pairs);
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $parts = preg_split('/;\s*\R?/', $sql) ?: [];
        $out   = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Split on statement terminators without breaking on semicolons inside PostgreSQL
     * string literals, identifiers, or dollar-quoted bodies (naive split breaks INSERT data).
     *
     * @return list<string>
     */
    private function splitPostgresSqlStatements(string $sql): array
    {
        $out       = [];
        $current   = '';
        $len       = strlen($sql);
        $i         = 0;
        $inSingle  = false;
        $inDouble  = false;
        $dollarTag = null;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($dollarTag !== null) {
                if ($ch === '$') {
                    $close = '$' . $dollarTag . '$';
                    if (substr($sql, $i, strlen($close)) === $close) {
                        $current .= $close;
                        $i += strlen($close);
                        $dollarTag = null;

                        continue;
                    }
                }
                $current .= $ch;
                $i++;

                continue;
            }

            if ($inSingle) {
                $current .= $ch;
                if ($ch === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                    $current .= "'";
                    $i += 2;

                    continue;
                }
                if ($ch === "'") {
                    $inSingle = false;
                }
                $i++;

                continue;
            }

            if ($inDouble) {
                $current .= $ch;
                if ($ch === '"' && $i + 1 < $len && $sql[$i + 1] === '"') {
                    $current .= '"';
                    $i += 2;

                    continue;
                }
                if ($ch === '"') {
                    $inDouble = false;
                }
                $i++;

                continue;
            }

            // Line / block comments (not inside quotes or dollar-strings); semicolons inside must not split statements.
            if ($i + 1 < $len && $ch === '-' && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n" && $sql[$i] !== "\r") {
                    $i++;
                }
                if ($i < $len) {
                    if ($sql[$i] === "\r") {
                        $i++;
                    }
                    if ($i < $len && $sql[$i] === "\n") {
                        $i++;
                    }
                }

                continue;
            }
            if ($i + 1 < $len && $ch === '/' && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && ! ($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                if ($i + 1 < $len) {
                    $i += 2;
                }

                continue;
            }

            if ($ch === '$') {
                if ($i + 1 < $len && $sql[$i + 1] === '$') {
                    $dollarTag = '';
                    $current .= '$$';
                    $i += 2;

                    continue;
                }
                if (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)\$/', substr($sql, $i), $m)) {
                    $dollarTag = $m[1];
                    $current .= $m[0];
                    $i += strlen($m[0]);

                    continue;
                }
            }

            if ($ch === "'") {
                $inSingle = true;
                $current .= $ch;
                $i++;

                continue;
            }

            if ($ch === '"') {
                $inDouble = true;
                $current .= $ch;
                $i++;

                continue;
            }

            if ($ch === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '' && ! $this->isPostgresCommentOnlyStatement($trimmed)) {
                    $out[] = $trimmed;
                }
                $current = '';
                $i++;
                while ($i < $len && ctype_space($sql[$i])) {
                    $i++;
                }

                continue;
            }

            $current .= $ch;
            $i++;
        }

        $trimmed = trim($current);
        if ($trimmed !== '' && ! $this->isPostgresCommentOnlyStatement($trimmed)) {
            $out[] = $trimmed;
        }

        return $out;
    }

    private function isPostgresCommentOnlyStatement(string $s): bool
    {
        $t = trim($s);
        if ($t === '') {
            return true;
        }

        $withoutBlocks = preg_replace('/\/\*[\s\S]*?\*\//', '', $t) ?? $t;
        $withoutBlocks = trim($withoutBlocks);
        if ($withoutBlocks === '') {
            return true;
        }

        foreach (preg_split('/\R/', $withoutBlocks) ?: [] as $line) {
            $lineTrim = trim($line);
            if ($lineTrim === '') {
                continue;
            }
            if (! str_starts_with($lineTrim, '--')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{ok: true, path: string}|array{ok: false, error: string}
     */
    public function backupDatabaseToWritable(): array
    {
        try {
            $db = AppDatabase::connection();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $prefix = (string) ($db->DBPrefix ?? '');
        if ($prefix === '') {
            return ['ok' => false, 'error' => 'DBPrefix is empty in Config\\Database. Backup during uninstall is refused so unrelated tables are not included.'];
        }

        $dir = WRITEPATH . 'backup' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . 'uninstall_' . date('Y-m-d_His') . '.sql';
        $fh   = fopen($file, 'wb');
        if ($fh === false) {
            return ['ok' => false, 'error' => 'Could not open backup file for writing.'];
        }

        fwrite($fh, '-- Product Store backup ' . date('c') . PHP_EOL);
        fwrite($fh, '-- DBPrefix (Config\Database): ' . ($prefix === '' ? '(empty)' : $prefix) . PHP_EOL);
        fwrite($fh, '-- Physical table identifiers (prefix + logical name as stored in the database):' . PHP_EOL);

        $driver = $db->DBDriver;
        $tables = $driver === 'Postgre'
            ? $this->listPostgresTablesForDumpOrder($db)
            : $this->listPhysicalTablesForApp($db);

        foreach ($tables as $t) {
            fwrite($fh, '--   ' . $t . PHP_EOL);
        }

        foreach ($tables as $table) {
            $this->dumpTableSchemaAndData($db, $fh, $table, $driver);
        }

        fclose($fh);

        return ['ok' => true, 'path' => $file];
    }

    /**
     * Tables as stored in the database (prefix + logical name). Only names starting with
     * Config\Database::$default['DBPrefix'] / the connection DBPrefix are included.
     *
     * @return list<string>
     */
    private function listPhysicalTablesForApp(BaseConnection $db): array
    {
        $prefix = (string) ($db->DBPrefix ?? '');
        if ($prefix === '') {
            return [];
        }

        return array_values(array_filter(
            $this->listAllPhysicalTables($db),
            static fn (string $table): bool => str_starts_with($table, $prefix),
        ));
    }

    /**
     * All physical tables in the configured database/schema, regardless of prefix.
     *
     * @return list<string>
     */
    private function listAllPhysicalTables(BaseConnection $db): array
    {
        $db->resetDataCache();
        $listed = $db->listTables(false);
        if ($listed === false) {
            return [];
        }

        return array_values($listed);
    }

    /**
     * @param resource $fh
     */
    private function dumpTableSchemaAndData(BaseConnection $db, $fh, string $table, string $driver): void
    {
        if ($driver === 'MySQLi') {
            $create = $db->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->getRowArray();
            $ddl    = $create['Create Table'] ?? '';
            fwrite($fh, PHP_EOL . 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`;' . PHP_EOL);
            fwrite($fh, $ddl . ';' . PHP_EOL);

            $rows = $db->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->getResultArray();
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(
                    fn ($v) => $this->sqlLiteral($db, $v),
                    array_values($row),
                );
                $colList = implode(
                    ',',
                    array_map(static fn ($c) => '`' . str_replace('`', '``', (string) $c) . '`', $cols),
                );
                fwrite(
                    $fh,
                    'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . $colList . ') VALUES (' . implode(',', $vals) . ');' . PHP_EOL,
                );
            }

            return;
        }

        if ($driver === 'Postgre') {
            $safe = '"' . str_replace('"', '""', $table) . '"';
            fwrite($fh, PHP_EOL . '-- Table ' . $table . ' (data rows only — run preset install SQL first so tables exist.)' . PHP_EOL);
            $rows = $db->query('SELECT * FROM ' . $safe)->getResultArray();
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $colList = implode(
                    ',',
                    array_map(static fn ($c) => '"' . str_replace('"', '""', (string) $c) . '"', $cols),
                );
                $vals = array_map(fn ($v) => $this->sqlLiteral($db, $v), array_values($row));
                fwrite(
                    $fh,
                    'INSERT INTO ' . $safe . ' (' . $colList . ') VALUES (' . implode(',', $vals) . ');' . PHP_EOL,
                );
            }

            return;
        }

        if ($driver === 'SQLite3') {
            $safe = '"' . str_replace('"', '""', $table) . '"';
            $row  = $db->query('SELECT sql FROM sqlite_master WHERE type=' . $db->escape('table') . ' AND name=' . $db->escape($table))->getRowArray();
            $ddl  = (string) ($row['sql'] ?? '');
            fwrite($fh, PHP_EOL . 'DROP TABLE IF EXISTS ' . $safe . ';' . PHP_EOL);
            if ($ddl !== '') {
                fwrite($fh, $ddl . ';' . PHP_EOL);
            }
            $data = $db->query('SELECT * FROM ' . $safe)->getResultArray();
            foreach ($data as $r) {
                $cols = array_keys($r);
                $vals = array_map(fn ($v) => $this->sqlLiteral($db, $v), array_values($r));
                $colList = implode(',', array_map(static fn ($c) => '"' . str_replace('"', '""', (string) $c) . '"', $cols));
                fwrite($fh, 'INSERT INTO ' . $safe . ' (' . $colList . ') VALUES (' . implode(',', $vals) . ');' . PHP_EOL);
            }
        }
    }

    private function sqlLiteral(BaseConnection $db, mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }

        return $db->escape($v);
    }

    /**
     * @return string|null error
     */
    public function dropAllTables(): ?string
    {
        try {
            $db = AppDatabase::connection();
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        $prefix = (string) ($db->DBPrefix ?? '');
        if ($prefix === '') {
            return 'DBPrefix is empty in Config\\Database. Drop tables during uninstall is refused so unrelated tables are not dropped.';
        }

        $tables = $this->listPhysicalTablesForApp($db);
        $driver = $db->DBDriver;

        try {
            if ($driver === 'MySQLi') {
                $db->query('SET FOREIGN_KEY_CHECKS=0');
                foreach ($tables as $t) {
                    $db->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
                }
                $db->query('SET FOREIGN_KEY_CHECKS=1');

                return null;
            }

            if ($driver === 'Postgre') {
                foreach ($tables as $t) {
                    $db->query('DROP TABLE IF EXISTS "' . str_replace('"', '""', $t) . '" CASCADE');
                }

                return null;
            }

            if ($driver === 'SQLite3') {
                foreach ($tables as $t) {
                    $db->query('DROP TABLE IF EXISTS "' . str_replace('"', '""', $t) . '"');
                }

                return null;
            }

            return 'Automatic table drop is not implemented for this driver.';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
