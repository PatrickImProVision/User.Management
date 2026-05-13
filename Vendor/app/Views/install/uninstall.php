<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php $hasDbPrefix = ($currentDbPrefix ?? '') !== ''; ?>
<h1>Uninstall</h1>
<p class="lead">Choose what to run. Actions execute in fixed order (one step per screen after you submit): backup → drop tables → reset <code>app/Config/Database.php</code> → delete install flag. You can tick any combination.</p>
<p class="hint">Table actions are limited to the configured <strong>DBPrefix</strong>: <code><?= esc(($currentDbPrefix ?? '') !== '' ? $currentDbPrefix : '(empty)') ?></code>.</p>

<?php if (($currentDbPrefix ?? '') === '') : ?>
    <div class="err">
        <strong>DBPrefix is empty.</strong> Backup and drop-table actions are refused when no prefix is configured, so unrelated tables cannot be backed up or dropped by mistake.
    </div>
<?php endif ?>

<?php if (! empty($errors)) : ?>
    <div class="err">
        <?php foreach ($errors as $k => $e) : ?>
            <div><strong><?= esc((string) $k) ?>:</strong> <?= esc(is_array($e) ? implode(' ', $e) : (string) $e) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= esc(site_url('install/uninstall/confirm')) ?>" class="card">
    <?= csrf_field() ?>
    <label class="field-check">
        <input type="checkbox" name="backup" value="1"<?= $hasDbPrefix ? ' checked' : ' disabled' ?>>
        <span class="field-check-text">Backup data to <code>writable/backup/</code> (SQL file)</span>
    </label>
    <label class="field-check">
        <input type="checkbox" name="drop_tables" value="1"<?= $hasDbPrefix ? '' : ' disabled' ?>>
        <span class="field-check-text">Drop all application tables that match your configured <strong>DBPrefix</strong> (full physical table names only)</span>
    </label>
    <label class="field-check">
        <input type="checkbox" name="delete_flag" value="1" checked>
        <span class="field-check-text">Delete install flag (allows running the installer again)</span>
    </label>
    <label class="field-check">
        <input type="checkbox" name="reset_database_config" value="1"<?= old('reset_database_config') ? ' checked' : '' ?>>
        <span class="field-check-text">Reset <code>app/Config/Database.php</code> to application defaults</span>
    </label>

    <div class="actions">
        <button type="submit" class="btn btn-danger">Run selected actions</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('/')) ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
