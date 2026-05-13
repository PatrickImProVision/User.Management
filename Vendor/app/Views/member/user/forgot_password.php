<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Forgot Password</h1>
<p class="lead">Request a reset token for an existing public member account.</p>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc(site_url('Member/User/ForgotPassword')) ?>" class="card">
    <?= csrf_field() ?>
    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" value="<?= old('email', '', 'attr') ?>" required>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Create reset token</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Login')) ?>">Back to login</a>
    </div>
</form>
<?= $this->endSection() ?>
