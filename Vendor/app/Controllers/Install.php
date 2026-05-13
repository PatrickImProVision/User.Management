<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AppDatabase;
use App\Libraries\InstallationService;
use App\Libraries\InstallationState;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database as DbConfig;

class Install extends BaseController
{
    protected $helpers = ['form', 'url'];

    private const SESSION_DB              = 'install_database_configured';
    private const SESSION_SCHEMA          = 'install_schema_ready';
    private const SESSION_MODE            = 'install_mode';
    private const SESSION_UNINSTALL_QUEUE = 'uninstall_queue';

    /**
     * Merge custom flash "errors" with validation flash from redirect()->withInput().
     *
     * @return array<string, string|list<string>>
     */
    private function popInstallFlashErrors(): array
    {
        $errors = session()->getFlashdata('errors');
        if (! is_array($errors)) {
            $errors = [];
        }

        $validation = session()->getFlashdata('_ci_validation_errors');
        if (is_array($validation)) {
            foreach ($validation as $field => $message) {
                $errors[$field] = is_array($message) ? implode(' ', $message) : (string) $message;
            }
        }

        return $errors;
    }

    public function index(): ResponseInterface|string
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        $svc     = new InstallationService();
        $backups = $svc->listWritableUninstallBackups();
        if ($backups !== []) {
            return view('install/start', [
                'backups' => $backups,
                'errors'  => $this->popInstallFlashErrors(),
            ]);
        }

        return $this->showDatabaseForm('install');
    }

    public function fresh(): ResponseInterface|string
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        return $this->showDatabaseForm('install');
    }

    public function restore(): ResponseInterface|string
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        $svc = new InstallationService();
        if ($svc->listWritableUninstallBackups() === []) {
            return redirect()->to(site_url('install/new'))->with('message', 'No backups found. Starting a fresh install.');
        }

        return $this->showDatabaseForm('restore');
    }

    public function deleteBackup(): ResponseInterface
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! $this->request->is('post')) {
            return redirect()->to(site_url('install'));
        }

        $backup = (string) $this->request->getPost('backup_file');
        $svc    = new InstallationService();
        $err    = $svc->deleteWritableUninstallBackup($backup);
        if ($err !== null) {
            return redirect()->back()->withInput()->with('errors', ['backup_delete' => $err]);
        }

        if ($svc->listWritableUninstallBackups() === []) {
            session()->set(self::SESSION_MODE, 'install');

            return redirect()->to(site_url('install/new'))->with('message', 'Backup deleted. No backups remain, so fresh install is available.');
        }

        $returnTo = (string) $this->request->getPost('return_to');
        $target   = $returnTo === 'restore_schema'
            ? site_url('install/restore/schema')
            : site_url('install');

        return redirect()->to($target)->with('message', 'Backup deleted.');
    }

    private function showDatabaseForm(string $mode): string
    {
        return view('install/database', [
            'drivers'     => InstallationService::SUPPORTED_DRIVERS,
            'mode'        => $mode === 'restore' ? 'restore' : 'install',
            'flowTitle'   => $mode === 'restore' ? 'Restore from Backup' : 'Installation Manager',
            'flowLead'    => $mode === 'restore'
                ? 'Connect the database you want to restore into. The connection test and saved settings are the same as install.'
                : 'Connect your database. Settings are written to <code>app/Config/Database.php</code> and read by <code>Config\Database</code>.',
            'submitLabel' => $mode === 'restore' ? 'Save & continue to restore' : 'Save & continue',
            'errors'      => $this->popInstallFlashErrors(),
        ]);
    }

    public function testConnection(): ResponseInterface
    {
        if (InstallationState::isInstalled()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Already installed.']);
        }

        $rules = [
            'DBDriver' => 'required|in_list[' . implode(',', InstallationService::SUPPORTED_DRIVERS) . ']',
            'hostname' => 'permit_empty|max_length[255]',
            'port'     => 'permit_empty|integer',
            'database' => 'required|max_length[500]',
            'username' => 'permit_empty|max_length[255]',
            'password' => 'permit_empty|max_length[255]',
            'schema'   => 'permit_empty|max_length[64]',
            'DBPrefix' => 'required|max_length[64]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'ok'    => false,
                'error' => implode(' ', $this->validator->getErrors()),
            ]);
        }

        $driver = (string) $this->request->getPost('DBDriver');
        $cfg    = $this->buildDbConfigFromRequest($driver);
        if (($cfg['DBPrefix'] ?? '') === '') {
            return $this->response->setJSON([
                'ok'    => false,
                'error' => 'Table prefix is required and may only contain letters, digits, and underscore.',
            ]);
        }

        $svc = new InstallationService();
        $err = $svc->testConnection($cfg);

        return $this->response->setJSON(['ok' => $err === null, 'error' => $err]);
    }

    public function saveDatabase(): ResponseInterface
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        $rules = [
            'DBDriver' => 'required|in_list[' . implode(',', InstallationService::SUPPORTED_DRIVERS) . ']',
            'hostname' => 'permit_empty|max_length[255]',
            'port'     => 'permit_empty|integer',
            'database' => 'required|max_length[500]',
            'username' => 'permit_empty|max_length[255]',
            'password' => 'permit_empty|max_length[255]',
            'schema'   => 'permit_empty|max_length[64]',
            'baseURL'  => 'permit_empty|max_length[500]',
            'DBPrefix' => 'required|max_length[64]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $driver = (string) $this->request->getPost('DBDriver');
        $cfg    = $this->buildDbConfigFromRequest($driver);

        if (($cfg['DBPrefix'] ?? '') === '') {
            return redirect()->back()->withInput()->with('errors', ['DBPrefix' => 'Table prefix is required and may only contain letters, digits, and underscore.']);
        }

        $svc = new InstallationService();
        $err = $svc->testConnection($cfg);
        if ($err !== null) {
            return redirect()->back()->withInput()->with('errors', ['database' => $err]);
        }

        $mode = (string) $this->request->getPost('install_mode');
        $mode = $mode === 'restore' ? 'restore' : 'install';

        $baseURL = $this->request->getPost('baseURL');
        $baseURL = is_string($baseURL) ? trim($baseURL) : '';
        if ($baseURL !== '' && ! str_ends_with($baseURL, '/')) {
            $baseURL .= '/';
        }

        $row = [
            'DBDriver'    => $cfg['DBDriver'],
            'hostname'    => $cfg['hostname'] ?? '',
            'port'        => $cfg['port'] ?? 3306,
            'database'    => $cfg['database'] ?? '',
            'username'    => $cfg['username'] ?? '',
            'password'    => $cfg['password'] ?? '',
            'DBPrefix'    => $cfg['DBPrefix'] ?? '',
            'schema'      => $cfg['schema'] ?? 'public',
            'foreignKeys' => $cfg['foreignKeys'] ?? false,
        ];

        try {
            $svc->persistConfigFiles($row, $baseURL !== '' ? $baseURL : null);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errors', ['config' => $e->getMessage()]);
        }

        session()->set(self::SESSION_DB, true);
        session()->set(self::SESSION_MODE, $mode);
        session()->remove(self::SESSION_SCHEMA);
        session()->remove('install_admin_created');
        session()->remove('install_restored_accounts');

        return redirect()->to($mode === 'restore' ? site_url('install/restore/schema') : site_url('install/schema'));
    }

    public function schema(): ResponseInterface|string
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! session()->get(self::SESSION_DB)) {
            return redirect()->to(site_url('install'));
        }

        $dbConfig = config(DbConfig::class);
        $driver   = (string) ($dbConfig->default['DBDriver'] ?? '');
        $folder   = InstallationService::sqlPresetFolder($driver);
        $svc      = new InstallationService();
        $mode     = session()->get(self::SESSION_MODE) === 'restore' ? 'restore' : 'install';
        $backups  = $svc->listWritableUninstallBackups();

        if ($mode === 'restore' && $backups === []) {
            session()->set(self::SESSION_MODE, 'install');

            return redirect()->to(site_url('install/schema'))->with('errors', ['restore' => 'No backups found. Continue with fresh install.']);
        }

        return view('install/schema', [
            'driver'                   => $driver,
            'presetFolder'             => $folder,
            'currentDbPrefix'          => (string) ($dbConfig->default['DBPrefix'] ?? ''),
            'writableUninstallBackups' => $backups,
            'mode'                     => $mode,
            'errors'                   => $this->popInstallFlashErrors(),
        ]);
    }

    public function runSchema(): ResponseInterface
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! session()->get(self::SESSION_DB)) {
            return redirect()->to(site_url('install'));
        }

        $mode = session()->get(self::SESSION_MODE) === 'restore' ? 'restore' : 'install';

        if ($this->request->getPost('skip')) {
            if ($mode === 'restore') {
                return redirect()->back()->withInput()->with('errors', ['restore' => 'Choose a backup to restore.']);
            }

            session()->set(self::SESSION_SCHEMA, true);

            return redirect()->to(site_url('install/admin'));
        }

        if ($this->request->getPost('restore_from_writable_backup')) {
            $svc     = new InstallationService();
            $allowed = array_column($svc->listWritableUninstallBackups(), 'basename');
            $pick    = (string) $this->request->getPost('backup_file');
            if ($pick === '' || ! in_array($pick, $allowed, true)) {
                return redirect()->back()->withInput()->with('errors', ['restore' => 'Select a valid backup from writable/backup/.']);
            }

            $err = $svc->restoreFromWritableUninstallBackup($pick);
            if ($err !== null) {
                return redirect()->back()->withInput()->with('errors', ['restore' => $err]);
            }

            $err = $svc->ensureAdministratorAccountsActive();
            if ($err !== null) {
                return redirect()->back()->withInput()->with('errors', ['restore' => $err]);
            }

            session()->set(self::SESSION_SCHEMA, true);
            if ($svc->hasExistingUserAccounts()) {
                session()->set('install_admin_created', true);
                session()->set('install_restored_accounts', true);

                return redirect()->to(site_url('install/complete'));
            }

            return redirect()->to(site_url('install/admin'));
        }

        if ($mode === 'restore') {
            return redirect()->back()->withInput()->with('errors', ['restore' => 'Choose a backup to restore.']);
        }

        if (! $this->request->getPost('run')) {
            return redirect()->back()->withInput()->with('errors', ['schema' => 'Choose preset SQL, restore from backup, or skip.']);
        }

        $svc = new InstallationService();
        $err = $svc->runPresetSql();
        if ($err !== null) {
            return redirect()->back()->withInput()->with('errors', ['schema' => $err]);
        }

        session()->set(self::SESSION_SCHEMA, true);

        return redirect()->to(site_url('install/admin'));
    }

    public function admin(): ResponseInterface|string
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! session()->get(self::SESSION_DB) || ! session()->get(self::SESSION_SCHEMA)) {
            return redirect()->to(site_url('install'));
        }

        if (session()->get('install_restored_accounts')) {
            return redirect()->to(site_url('install/complete'));
        }

        return view('install/admin', [
            'errors' => $this->popInstallFlashErrors(),
        ]);
    }

    public function saveAdmin(): ResponseInterface
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! session()->get(self::SESSION_DB) || ! session()->get(self::SESSION_SCHEMA)) {
            return redirect()->to(site_url('install'));
        }

        if (session()->get('install_restored_accounts')) {
            return redirect()->to(site_url('install/complete'));
        }

        $rules = [
            'username'         => 'required|min_length[3]|max_length[100]',
            'email'            => 'required|valid_email|max_length[191]',
            'password'         => 'required|min_length[8]|max_length[200]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $db = AppDatabase::connection();

        try {
            $db->table('users')->insert([
                'username'      => (string) $this->request->getPost('username'),
                'email'         => (string) $this->request->getPost('email'),
                'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role'          => 'owner',
                'profile_image' => null,
                'is_active'     => true,
                'activation_guid'   => null,
                'deactivation_guid' => bin2hex(random_bytes(16)),
                'reset_guid'        => null,
                'last_login_at'     => null,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => null,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errors', ['username' => $e->getMessage()]);
        }

        session()->set('install_admin_created', true);

        return redirect()->to(site_url('install/complete'));
    }

    public function complete(): ResponseInterface|string
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! session()->get('install_admin_created')) {
            return redirect()->to(site_url('install'));
        }

        return view('install/complete', [
            'restoredAccounts' => (bool) session()->get('install_restored_accounts'),
        ]);
    }

    public function finish(): ResponseInterface
    {
        if (InstallationState::isInstalled()) {
            return redirect()->to('/');
        }

        if (! session()->get('install_admin_created')) {
            return redirect()->to(site_url('install'));
        }

        $restoredAccounts = (bool) session()->get('install_restored_accounts');

        InstallationState::markInstalled();
        session()->remove(self::SESSION_DB);
        session()->remove(self::SESSION_SCHEMA);
        session()->remove(self::SESSION_MODE);
        session()->remove('install_admin_created');
        session()->remove('install_restored_accounts');

        $message = $restoredAccounts
            ? 'Installation complete. Use your restored login when authentication is enabled.'
            : 'Installation complete. Sign in when authentication is enabled.';

        return redirect()->to('/')->with('message', $message);
    }

    public function uninstall(): ResponseInterface|string
    {
        if (! InstallationState::isInstalled()) {
            return redirect()->to(site_url('install'));
        }

        $dbConfig = config(DbConfig::class);

        return view('install/uninstall', [
            'currentDbPrefix' => (string) ($dbConfig->default['DBPrefix'] ?? ''),
            'errors'          => $this->popInstallFlashErrors(),
        ]);
    }

    /**
     * Queue uninstall steps (fixed order: backup → drop tables → reset DB config → delete install flag).
     */
    public function uninstallConfirm(): ResponseInterface
    {
        if (! InstallationState::isInstalled()) {
            return redirect()->to(site_url('install'));
        }

        if (! $this->request->is('post')) {
            return redirect()->to(site_url('install/uninstall'));
        }

        $backup     = (bool) $this->request->getPost('backup');
        $drop       = (bool) $this->request->getPost('drop_tables');
        $flag       = (bool) $this->request->getPost('delete_flag');
        $resetDbPhp = (bool) $this->request->getPost('reset_database_config');

        if (! $backup && ! $drop && ! $flag && ! $resetDbPhp) {
            return redirect()->back()->withInput()->with('errors', ['options' => 'Select at least one uninstall action.']);
        }

        $queue = [];
        if ($backup) {
            $queue[] = 'backup';
        }
        if ($drop) {
            $queue[] = 'drop_tables';
        }
        if ($resetDbPhp) {
            $queue[] = 'reset_database_config';
        }
        if ($flag) {
            $queue[] = 'delete_flag';
        }

        session()->set(self::SESSION_UNINSTALL_QUEUE, $queue);

        return redirect()->to(site_url('install/uninstall/next'));
    }

    /**
     * Run the next queued uninstall step (one HTTP request per step).
     */
    public function uninstallNext(): ResponseInterface|string
    {
        $queue = session()->get(self::SESSION_UNINSTALL_QUEUE);
        if (! is_array($queue)) {
            return redirect()->to(site_url('install/uninstall'))->with('errors', ['queue' => 'No uninstall steps queued. Open uninstall and submit again.']);
        }

        if ($queue === []) {
            session()->remove(self::SESSION_UNINSTALL_QUEUE);

            return redirect()->to(site_url('install/uninstall'))->with('errors', ['queue' => 'Nothing left to run.']);
        }

        if (! InstallationState::isInstalled()) {
            session()->remove(self::SESSION_UNINSTALL_QUEUE);

            return redirect()->to(site_url('install'));
        }

        $step = array_shift($queue);
        session()->set(self::SESSION_UNINSTALL_QUEUE, $queue);

        $svc     = new InstallationService();
        $summary = '';

        switch ($step) {
            case 'backup':
                $res = $svc->backupDatabaseToWritable();
                if (! $res['ok']) {
                    session()->remove(self::SESSION_UNINSTALL_QUEUE);

                    return redirect()->to(site_url('install/uninstall'))->withInput()->with('errors', ['backup' => $res['error']]);
                }
                $summary = 'Backup file: ' . basename($res['path']);

                break;

            case 'drop_tables':
                $err = $svc->dropAllTables();
                if ($err !== null) {
                    session()->remove(self::SESSION_UNINSTALL_QUEUE);

                    return redirect()->to(site_url('install/uninstall'))->withInput()->with('errors', ['drop' => $err]);
                }
                $summary = 'Application tables (matching your configured table prefix) were dropped.';

                break;

            case 'reset_database_config':
                $err = $svc->resetDatabaseConfigToShipped();
                if ($err !== null) {
                    session()->remove(self::SESSION_UNINSTALL_QUEUE);

                    return redirect()->to(site_url('install/uninstall'))->withInput()->with('errors', ['reset_database_config' => $err]);
                }
                $summary = 'Config/Database.php was reset.';

                break;

            case 'delete_flag':
                InstallationState::clearFlag();
                $summary = 'Install flag removed.';

                break;

            default:
                session()->remove(self::SESSION_UNINSTALL_QUEUE);

                return redirect()->to(site_url('install/uninstall'))->with('errors', ['queue' => 'Invalid uninstall step.']);
        }

        if ($queue === []) {
            session()->remove(self::SESSION_UNINSTALL_QUEUE);

            return redirect()->to(site_url('install'))->with('message', 'Uninstall finished. ' . $summary);
        }

        return view('install/uninstall_progress', [
            'completed_step' => $step,
            'summary'        => $summary,
            'remaining'      => $queue,
            'step_labels'    => $this->uninstallStepLabels(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function uninstallStepLabels(): array
    {
        return [
            'backup'                => 'Backup database',
            'drop_tables'           => 'Drop application tables',
            'reset_database_config' => 'Reset Database.php',
            'delete_flag'           => 'Delete install flag',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDbConfigFromRequest(string $driver): array
    {
        $prefix = InstallationService::normalizeDbPrefix((string) $this->request->getPost('DBPrefix'));

        $port = $this->request->getPost('port');
        $port = $port !== null && $port !== '' ? (int) $port : match ($driver) {
            'Postgre' => 5432,
            'SQLSRV'  => 1433,
            default   => 3306,
        };

        $database = (string) $this->request->getPost('database');
        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');
        $hostname = (string) $this->request->getPost('hostname');

        if ($driver === 'SQLite3') {
            return [
                'DBDriver'    => 'SQLite3',
                'database'    => $database,
                'hostname'    => '',
                'username'    => '',
                'password'    => '',
                'port'        => $port,
                'foreignKeys' => true,
                'DBPrefix'    => $prefix,
            ];
        }

        $cfg = [
            'DBDriver' => $driver,
            'hostname' => $hostname,
            'port'     => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'DBPrefix' => $prefix,
        ];

        if ($driver === 'Postgre') {
            $schema = (string) ($this->request->getPost('schema') ?: 'public');
            $cfg['schema'] = $schema;
        }

        return $cfg;
    }
}
