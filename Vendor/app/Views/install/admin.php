<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Owner account</h1>
<p class="lead">Create the first user with the <code>owner</code> role.</p>

<?php if (! empty($errors)) : ?>
    <div class="err">
        <?php foreach ($errors as $k => $e) : ?>
            <div><strong><?= esc((string) $k) ?>:</strong> <?= esc(is_array($e) ? implode(' ', $e) : (string) $e) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= esc(site_url('install/admin')) ?>" class="card">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" name="username" id="username" value="<?= old('username', '', 'attr') ?>" required minlength="3">

    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" value="<?= old('email', '', 'attr') ?>" required>

    <div class="row row-user-pass">
        <div>
            <label for="password">Password</label>
            <div class="field-password">
                <input type="password" name="password" id="password" value="<?= old('password', '', 'attr') ?>" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" id="admin-password-toggle" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
        </div>
        <div>
            <label for="password_confirm">Confirm password</label>
            <div class="field-password">
                <input type="password" name="password_confirm" id="password_confirm" value="<?= old('password_confirm', '', 'attr') ?>" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" id="admin-password-confirm-toggle" aria-label="Show confirm password" aria-pressed="false">Show</button>
            </div>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Save owner</button>
    </div>
</form>
<script>
(function () {
    function wire(toggleId, inputId, phrase) {
        var btn = document.getElementById(toggleId);
        var input = document.getElementById(inputId);
        if (! btn || ! input) {
            return;
        }
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-label', (show ? 'Hide ' : 'Show ') + phrase);
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    }
    wire('admin-password-toggle', 'password', 'password');
    wire('admin-password-confirm-toggle', 'password_confirm', 'confirm password');
})();
</script>
<?= $this->endSection() ?>
