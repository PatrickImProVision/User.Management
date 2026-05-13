<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Account Deactivated</h1>
<p class="lead">Your account has been deactivated and you have been logged out.</p>

<div class="ok">
    Account status updated.
</div>

<div class="actions">
    <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Login')) ?>">Login</a>
</div>
<?= $this->endSection() ?>
