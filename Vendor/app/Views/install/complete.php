<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Finish installation</h1>
<p class="lead">Write the install flag to <code><?= esc(\App\Libraries\InstallationState::RELATIVE_FLAG_PATH) ?></code> under the writable directory. After this, the storefront is available and this wizard is locked (except uninstall).</p>

<?php if (! empty($restoredAccounts)) : ?>
    <div class="ok">
        <strong>Restored account found.</strong>
        Administrator registration was skipped. Finish installation and use the restored login when authentication is enabled.
    </div>
<?php endif ?>

<div class="card">
    <form method="post" action="<?= esc(site_url('install/finish')) ?>">
        <?= csrf_field() ?>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Mark application as installed</button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
