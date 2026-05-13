<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>User Profile</h1>
<p class="lead">View this member profile.</p>

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

<div class="card prose">
    <div class="profile-head">
        <?php if ($profileImageUrl !== '') : ?>
            <img class="user-avatar" src="<?= esc($profileImageUrl) ?>" alt="<?= esc((string) $user['username'], 'attr') ?> profile image">
        <?php else : ?>
            <div class="user-avatar-fallback" aria-label="No profile image"><?= esc($profileInitial) ?></div>
        <?php endif ?>
        <div>
            <h2><?= esc((string) $user['username']) ?></h2>
            <p>User account</p>
        </div>
    </div>
    <p>E-mail: <code><?= esc((string) $user['email']) ?></code></p>
    <p>Primary role: <code><?= esc((string) ($primaryRole ?? ucfirst((string) $user['role']))) ?></code></p>
    <p>Access roles:
        <?php foreach (($effectiveRoles ?? ['User']) as $role) : ?>
            <code><?= esc($role) ?></code>
        <?php endforeach ?>
    </p>
    <p>Status: <code><?= ! empty($user['is_active']) ? 'active' : 'inactive' ?></code></p>
    <?php if (! empty($user['created_at'])) : ?>
        <p>Created: <code><?= esc((string) $user['created_at']) ?></code></p>
    <?php endif ?>
    <?php if (! empty($user['last_login_at'])) : ?>
        <p>Last login: <code><?= esc((string) $user['last_login_at']) ?></code></p>
    <?php endif ?>

    <div class="actions">
        <a class="btn btn-secondary" href="<?= esc($backUrl ?? site_url('Member/List')) ?>">Back to users</a>
        <?php if (! empty($canManageUser)) : ?>
            <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Edit/' . (int) $user['id'])) ?>">Edit user</a>
            <form method="post" action="<?= esc(site_url('Member/User/Delete/' . (int) $user['id'])) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">Delete user</button>
            </form>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
