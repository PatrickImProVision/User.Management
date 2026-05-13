<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
/** @var list<string> $drivers */
$oldDriver = old('DBDriver', 'MySQLi', false);
$mode = ($mode ?? 'install') === 'restore' ? 'restore' : 'install';
?>
<h1><?= esc($flowTitle ?? 'Installation Manager') ?></h1>
<p class="lead"><?= $flowLead ?? 'Connect your database. Settings are written to <code>app/Config/Database.php</code> and read by <code>Config\Database</code>.' ?></p>

<?php if ($msg = session()->getFlashdata('message')) : ?>
    <div class="ok"><?= esc($msg) ?></div>
<?php endif ?>

<?php if (! empty($errors)) : ?>
    <div class="err">
        <?php foreach ($errors as $field => $e) : ?>
            <div><strong><?= esc((string) $field) ?>:</strong> <?= esc(is_array($e) ? implode(' ', $e) : (string) $e) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div id="test-result" class="hint" style="display:none;margin:0 0 1rem;" aria-live="polite"></div>

<form method="post" action="<?= esc(site_url('install/database')) ?>" id="install-db-form">
    <?= csrf_field() ?>
    <input type="hidden" name="install_mode" value="<?= esc($mode, 'attr') ?>">
    <div class="card">
        <label for="baseURL">Site base URL (optional)</label>
        <input type="url" name="baseURL" id="baseURL" value="<?= old('baseURL', 'http://localhost/Product.Store/', 'attr') ?>"
               placeholder="http://localhost/Product.Store/">
        <p class="hint">Include trailing slash. Writes <code>Config\App::$baseURL</code> for links and redirects.</p>

        <label for="DBDriver">Database driver</label>
        <select name="DBDriver" id="DBDriver" required>
            <?php foreach ($drivers as $d) : ?>
                <option value="<?= esc($d, 'attr') ?>" <?= $oldDriver === $d ? 'selected' : '' ?>><?= esc($d) ?></option>
            <?php endforeach ?>
        </select>

        <div class="row row-host-port" id="host-port-row">
            <div>
                <label for="hostname">Host name</label>
                <input type="text" name="hostname" id="hostname" value="<?= old('hostname', 'localhost', 'attr') ?>">
            </div>
            <div>
                <label for="port">Port</label>
                <input type="number" name="port" id="port" value="<?= old('port', '3306', 'attr') ?>">
            </div>
        </div>
        <p class="hint" id="port-hint" style="margin-top:0.15rem;"></p>

        <div id="schema-wrap" style="display:none;">
            <label for="schema">PostgreSQL schema</label>
            <input type="text" name="schema" id="schema" value="<?= old('schema', 'public', 'attr') ?>">
        </div>

        <div class="row row-db-prefix" id="db-prefix-row">
            <div>
                <label for="database" id="database-label">Database name</label>
                <input type="text" name="database" id="database" value="<?= old('database', '', 'attr') ?>"
                       placeholder="product_store" required>
                <p class="hint" id="database-hint"></p>
            </div>
            <div>
                <label for="DBPrefix">Table prefix</label>
                <input type="text" name="DBPrefix" id="DBPrefix" value="<?= old('DBPrefix', '', 'attr') ?>"
                       maxlength="64" autocomplete="off" placeholder="ps_" required>
            </div>
        </div>
        <p class="hint" style="margin-top:0.15rem;">Prefix is required: letters, digits, and underscore only. CodeIgniter prepends it to logical table names (e.g. <code>users</code> → <code>ps_users</code> with <code>ps_</code>). Preset SQL uses it when you run schema.</p>

        <div id="auth-fields" class="row row-user-pass">
            <div>
                <label for="username">User name</label>
                <input type="text" name="username" id="username" value="<?= old('username', '', 'attr') ?>">
            </div>
            <div>
                <label for="password">Password</label>
                <div class="field-password">
                    <input type="password" name="password" id="password" value="<?= old('password', '', 'attr') ?>" autocomplete="new-password">
                    <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="button" class="btn btn-secondary" id="btn-test">Test connection</button>
            <button type="submit" class="btn btn-primary"><?= esc($submitLabel ?? 'Save & continue') ?></button>
        </div>
    </div>
</form>

<script>
(function () {
    const hostPortRow = document.getElementById('host-port-row');
    const driver = document.getElementById('DBDriver');
    const hostname = document.getElementById('hostname');
    const port = document.getElementById('port');
    const schemaWrap = document.getElementById('schema-wrap');
    const auth = document.getElementById('auth-fields');
    const passwordToggle = document.getElementById('password-toggle');
    const dbLabel = document.getElementById('database-label');
    const dbHint = document.getElementById('database-hint');
    const portHint = document.getElementById('port-hint');
    const database = document.getElementById('database');

    function sync(fromDriverChange) {
        const d = driver.value;
        const isSql = d === 'SQLite3';
        hostname.disabled = isSql;
        port.disabled = isSql;
        if (hostPortRow) {
            hostPortRow.style.opacity = isSql ? '0.45' : '1';
        }
        auth.querySelectorAll('input').forEach(i => { i.disabled = isSql; });
        if (passwordToggle) {
            passwordToggle.disabled = isSql;
        }
        auth.style.opacity = isSql ? '0.45' : '1';
        schemaWrap.style.display = d === 'Postgre' ? 'block' : 'none';
        const sch = document.getElementById('schema');
        sch.disabled = d !== 'Postgre';
        if (fromDriverChange) {
            if (d === 'Postgre' && (port.value === '3306' || port.value === '')) {
                port.value = '5432';
            }
            if (d === 'MySQLi' && port.value === '5432') {
                port.value = '3306';
            }
        }
        dbLabel.textContent = isSql ? 'Database file path' : 'Database name';
        database.placeholder = isSql ? '<?= esc(WRITEPATH) ?>product_store.db' : 'product_store';
        dbHint.textContent = isSql
            ? 'Absolute path to the SQLite file. The directory must exist and be writable.'
            : '';
        if (portHint) {
            portHint.textContent = d === 'Postgre'
                ? 'Use your provider port. It may be different from 5432.'
                : '';
        }
    }
    driver.addEventListener('change', function () {
        sync(true);
    });
    sync(false);

    if (passwordToggle) {
        const pwdInput = document.getElementById('password');
        passwordToggle.addEventListener('click', function () {
            if (! pwdInput || pwdInput.disabled) {
                return;
            }
            const show = pwdInput.type === 'password';
            pwdInput.type = show ? 'text' : 'password';
            passwordToggle.textContent = show ? 'Hide' : 'Show';
            passwordToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            passwordToggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    }

    document.getElementById('btn-test').addEventListener('click', async function () {
        const fd = new FormData(document.getElementById('install-db-form'));
        const el = document.getElementById('test-result');
        el.style.display = 'block';
        el.className = 'hint';
        el.textContent = 'Testing…';
        el.style.color = 'var(--muted)';
        try {
            const r = await fetch('<?= site_url('install/test-connection') ?>', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const j = await r.json();
            el.className = j.ok ? 'ok' : 'err';
            el.innerHTML = j.ok
                ? '<strong>Connection successful.</strong> Database credentials are valid.'
                : '<strong>Connection failed.</strong> ' + escapeHtml(j.error || 'Unknown error.');
            el.style.color = '';
        } catch (e) {
            el.innerHTML = '<strong>Connection failed.</strong> Request failed.';
            el.className = 'err';
            el.style.color = '';
        }
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
<?= $this->endSection() ?>
