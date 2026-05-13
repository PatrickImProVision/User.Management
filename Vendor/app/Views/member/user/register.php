<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Register</h1>
<p class="lead">Create a public member account. The account must be activated before login.</p>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc(site_url('Member/User/Register')) ?>" class="card">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" name="username" id="username" value="<?= old('username', '', 'attr') ?>" required minlength="3">

    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" value="<?= old('email', '', 'attr') ?>" required>

    <div class="row row-user-pass">
        <div>
            <label for="password">Password</label>
            <div class="field-password">
                <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" id="register-password-toggle" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
        </div>
        <div>
            <label for="password_confirm">Confirm password</label>
            <div class="field-password">
                <input type="password" name="password_confirm" id="password_confirm" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" id="register-password-confirm-toggle" aria-label="Show confirm password" aria-pressed="false">Show</button>
            </div>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Register</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Login')) ?>">Login</a>
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
    wire('register-password-toggle', 'password', 'password');
    wire('register-password-confirm-toggle', 'password_confirm', 'confirm password');
})();
</script>
<?= $this->endSection() ?>
