<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Installation Manager</h1>
<p class="lead">Choose whether to start a fresh install or restore from an uninstall backup.</p>

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

<div class="card prose">
    <h2>Restore from backup</h2>
    <p>Backup file(s) were found under <code>writable/backup/</code>. Restore uses those files only, while still using the same connection test and database save step as install.</p>
    <p>Latest backup: <code><?= esc($backups[0]['basename'] ?? 'unknown') ?></code></p>
    <div class="actions">
        <a class="btn btn-primary" href="<?= esc(site_url('install/restore')) ?>">Restore backup</a>
    </div>
</div>

<div class="card prose">
    <h2>Delete backup</h2>
    <p>Remove an uninstall backup from <code>writable/backup/</code>. This does not touch source-code install SQL.</p>
    <form method="post" action="<?= esc(site_url('install/backup/delete')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="start">
        <label for="backup_file_delete">Backup file</label>
        <select name="backup_file" id="backup_file_delete">
            <?php foreach ($backups as $b) : ?>
                <option value="<?= esc($b['basename'], 'attr') ?>">
                    <?= esc($b['basename']) ?> - <?= esc(date('Y-m-d H:i', $b['mtime'])) ?>
                </option>
            <?php endforeach ?>
        </select>
        <div class="actions">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this backup file? This cannot be undone.');">Delete selected backup</button>
        </div>
    </form>
</div>

<div class="card prose">
    <h2>Fresh install</h2>
    <p>Use the source-code preset SQL from <code>app/Database/SQL/Install/</code> and create a new administrator account.</p>
    <div class="actions">
        <a class="btn btn-secondary" href="<?= esc(site_url('install/new')) ?>">Start fresh install</a>
    </div>
</div>
<?= $this->endSection() ?>
