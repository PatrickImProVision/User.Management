<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Profile</h1>
<p class="lead">Visible after login.</p>

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
            <p>Member account</p>
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
    <div class="actions">
        <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Edit/' . (int) $user['id'])) ?>">Edit profile</a>
        <?php if (! empty($user['deactivation_guid'])) : ?>
            <a class="btn btn-danger" href="<?= esc(site_url('Member/User/DeActivate/' . $user['deactivation_guid'])) ?>">Deactivate account</a>
        <?php endif ?>
        <form method="post" action="<?= esc(site_url('Member/User/Logout')) ?>" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">Logout</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
