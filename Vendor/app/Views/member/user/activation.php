<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Activate Account</h1>
<p class="lead">Visible before login.</p>

<div class="<?= ! empty($success) ? 'ok' : 'err' ?>">
    <?= esc($message ?? '') ?>
</div>

<div class="actions">
    <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Login')) ?>">Login</a>
    <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Register')) ?>">Register</a>
</div>
<?= $this->endSection() ?>
