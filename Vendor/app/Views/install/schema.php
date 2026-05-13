<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php $restoreMode = ($mode ?? 'install') === 'restore'; ?>
<h1><?= $restoreMode ? 'Restore backup' : 'Database schema' ?></h1>
<?php if ($restoreMode) : ?>
    <p class="lead">Restore from <code>writable/backup/</code>. This path does not use the source-code preset SQL directly unless missing tables must be created before replaying the backup.</p>
<?php else : ?>
    <p class="lead">Run bundled SQL from <code>app/Database/SQL/Install/</code> for your driver<?= $presetFolder ? ' (<code>' . esc($presetFolder) . '</code>)' : '' ?>.</p>
<?php endif ?>

<?php if (! empty($errors)) : ?>
    <div class="err">
        <?php foreach ($errors as $k => $e) : ?>
            <div><strong><?= esc((string) $k) ?>:</strong> <?= esc(is_array($e) ? implode(' ', $e) : (string) $e) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if (! $restoreMode) : ?>
    <div class="card">
        <p>Driver: <code><?= esc($driver) ?></code></p>
        <p>Current table prefix: <code><?= esc($currentDbPrefix !== '' ? $currentDbPrefix : '(empty)') ?></code></p>
        <?php if ($presetFolder === null) : ?>
            <p class="hint">No automated SQL bundle for this driver. Create tables manually, then skip this step.</p>
        <?php else : ?>
            <p class="hint">Creates core tables using the current prefix (for example <code><?= esc(($currentDbPrefix ?? '') . 'users') ?></code>) if they do not already exist.</p>
        <?php endif ?>

        <form method="post" action="<?= esc(site_url('install/schema')) ?>" class="actions" style="margin-top:1rem;">
            <?= csrf_field() ?>
            <button type="submit" name="run" value="1" class="btn btn-primary">Run preset SQL</button>
        </form>

        <form method="post" action="<?= esc(site_url('install/schema')) ?>" class="actions" style="margin-top:0.75rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="skip" value="1">
            <button type="submit" class="btn btn-secondary">Skip (tables already exist or not needed)</button>
        </form>
    </div>
<?php endif ?>

<?php if ($restoreMode && ! empty($writableUninstallBackups)) : ?>
    <div class="card" style="margin-top:1rem;">
        <p style="margin:0 0 0.35rem;font-weight:600;">Restore from uninstall backup</p>
        <p class="hint" style="margin:0 0 1rem;">Runs the SQL dump created during uninstall (stored under <code>writable/backup/</code>). Use the same database driver and table prefix used when the backup was made. If required tables like <code><?= esc(($currentDbPrefix ?? '') . 'users') ?></code> are missing, restore will create the preset schema first and then replay the backup.</p>

        <form method="post" action="<?= esc(site_url('install/restore/schema')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="restore_schema">
            <label for="backup_file">Backup file</label>
            <select name="backup_file" id="backup_file">
                <?php foreach ($writableUninstallBackups as $b) : ?>
                    <option value="<?= esc($b['basename']) ?>"<?= old('backup_file') === $b['basename'] ? ' selected' : '' ?>>
                        <?= esc($b['basename']) ?> — <?= esc(date('Y-m-d H:i', $b['mtime'])) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <div class="actions" style="margin-top:1rem;">
                <button type="submit" name="restore_from_writable_backup" value="1" class="btn btn-primary">Run restore from backup</button>
                <button type="submit" class="btn btn-danger" formaction="<?= esc(site_url('install/backup/delete')) ?>" onclick="return confirm('Delete this backup file? This cannot be undone.');">Delete selected backup</button>
            </div>
        </form>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
