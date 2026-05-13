<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Create User</h1>
<p class="lead">Create an active test user with a role below your current level.</p>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc(site_url('Member/User/Create')) ?>" class="card">
    <?= csrf_field() ?>

    <?php if (empty($roleOptions)) : ?>
        <div class="err">No assignable roles are currently available below your role level.</div>
    <?php endif ?>

    <label for="username">Username</label>
    <input type="text" name="username" id="username" value="<?= old('username', '', 'attr') ?>" required minlength="3" maxlength="100">

    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" value="<?= old('email', '', 'attr') ?>" required maxlength="191">

    <label for="role">Role</label>
    <select name="role" id="role" required <?= empty($roleOptions) ? 'disabled' : '' ?>>
        <option value="">Choose role...</option>
        <?php foreach ($roleOptions as $slug => $name) : ?>
            <option value="<?= esc((string) $slug, 'attr') ?>" <?= old('role') === (string) $slug ? 'selected' : '' ?>>
                <?= esc((string) $name) ?>
            </option>
        <?php endforeach ?>
    </select>

    <div class="row row-user-pass">
        <div>
            <label for="password">Password</label>
            <div class="field-password">
                <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" id="create-password-toggle" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
        </div>
        <div>
            <label for="password_confirm">Confirm password</label>
            <div class="field-password">
                <input type="password" name="password_confirm" id="password_confirm" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" id="create-password-confirm-toggle" aria-label="Show confirm password" aria-pressed="false">Show</button>
            </div>
        </div>
    </div>

    <label class="field-check">
        <input type="checkbox" name="is_active" value="1" <?= old('is_active', '1') ? 'checked' : '' ?>>
        <span class="field-check-text">Create as active user</span>
    </label>

    <div class="actions">
        <button type="submit" class="btn btn-primary" <?= empty($roleOptions) ? 'disabled' : '' ?>>Create user</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/AssignRole')) ?>">Assign User Roles</a>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/MyProfile')) ?>">Cancel</a>
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
    wire('create-password-toggle', 'password', 'password');
    wire('create-password-confirm-toggle', 'password_confirm', 'confirm password');
})();
</script>
<?= $this->endSection() ?>
