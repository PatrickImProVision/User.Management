<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php if ($msg = session()->getFlashdata('message')): ?>
    <div class="ok"><?= esc($msg) ?></div>
<?php endif ?>

<h1>Product Store</h1>
<p class="lead">CodeIgniter 4 application with an installation wizard, preset SQL, and guarded routes until install completes.</p>

<div class="card prose">
    <h2>Installation</h2>
    <p><a href="<?= site_url('install') ?>">Open the installation wizard</a> to configure the database, run presets, and create an admin user.</p>
    <?php if (\App\Libraries\InstallationState::isInstalled()): ?>
        <p><a href="<?= site_url('install/uninstall') ?>">Uninstall</a> (backup, drop database, or remove flag only).</p>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
