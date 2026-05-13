<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1><?= esc($title ?? 'Edit Profile') ?></h1>
<p class="lead">Update account details and profile image.</p>

<?= $this->include('member/user/_flash') ?>

<?php
$profileImage = trim((string) ($user['profile_image'] ?? ''));
$profileInitial = strtoupper(substr(trim((string) $user['username']), 0, 1) ?: '?');
$profileImageUrl = '';
if ($profileImage !== '') {
    $scriptFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $publicRoot = $scriptFile !== '' ? dirname($scriptFile) : FCPATH;
    $profileImageUrl = is_file(rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profileImage))
        ? base_url($profileImage)
        : base_url('Vendor/public/' . $profileImage);
}
?>

<form method="post" action="<?= esc(site_url('Member/User/Edit/' . (int) $user['id'])) ?>" class="card" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="profile-head">
        <?php if ($profileImageUrl !== '') : ?>
            <img class="user-avatar" src="<?= esc($profileImageUrl) ?>" alt="<?= esc((string) $user['username'], 'attr') ?> profile image">
        <?php else : ?>
            <div class="user-avatar-fallback" aria-label="No profile image"><?= esc($profileInitial) ?></div>
        <?php endif ?>
        <div>
            <strong><?= esc((string) $user['username']) ?></strong>
            <p class="hint">Upload a JPG, PNG, GIF, or WEBP image up to 2 MB.</p>
        </div>
    </div>

    <label for="username">Username</label>
    <input type="text" name="username" id="username" value="<?= old('username', (string) $user['username'], 'attr') ?>" required minlength="3">

    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" value="<?= old('email', (string) $user['email'], 'attr') ?>" required>

    <?php if (! empty($canManageRoles)) : ?>
        <label for="role">Primary role</label>
        <select name="role" id="role" required>
            <?php foreach (($roleOptions ?? []) as $value => $label) : ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= old('role', (string) $user['role']) === (string) $value ? 'selected' : '' ?>>
                    <?= esc((string) $label) ?>
                </option>
            <?php endforeach ?>
        </select>
        <p class="hint">Only roles below your own active role level can be assigned. Users cannot change their own role.</p>
        <input type="hidden" name="is_active" value="0">
        <label class="field-check">
            <input type="checkbox" name="is_active" value="1" <?= old('is_active', ! empty($user['is_active']) ? '1' : '') ? 'checked' : '' ?>>
            <span class="field-check-text">Active user account</span>
        </label>
    <?php else : ?>
        <p>Access roles:
            <?php foreach (($effectiveRoles ?? ['User']) as $role) : ?>
                <code><?= esc($role) ?></code>
            <?php endforeach ?>
        </p>
        <p class="hint">Role changes require a higher-level Administrator, Manager, or Owner account.</p>
    <?php endif ?>

    <label for="profile_image">Profile image</label>
    <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/gif,image/webp">

    <div class="actions">
        <button type="submit" class="btn btn-primary">Save user</button>
        <a class="btn btn-secondary" href="<?= esc($backUrl ?? site_url('Member/User/MyProfile')) ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
