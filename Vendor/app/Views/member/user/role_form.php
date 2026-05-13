<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$isEdit = ($mode ?? '') === 'edit';
$roleSlug = (string) ($role['slug'] ?? '');
$roleName = (string) ($role['name'] ?? '');
$roleDescription = (string) ($role['description'] ?? '');
$roleLevel = (string) ($role['level'] ?? '1');
$roleActive = ! empty($role['is_active']);
$roleSystem = ! empty($role['is_system']);
$roleId = (int) ($role['id'] ?? 0);
$action = $isEdit ? site_url('Member/User/Roles/Edit/' . $roleId) : site_url('Member/User/Roles/New');
?>

<h1><?= $isEdit ? 'Edit Role' : 'Add Role' ?></h1>
<p class="lead"><?= $isEdit ? 'Edit this role separately from the role list.' : 'Create a new custom database role.' ?></p>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc($action) ?>" class="card">
    <?= csrf_field() ?>

    <label for="slug">Slug</label>
    <input
        type="text"
        name="slug"
        id="slug"
        value="<?= old('slug', $roleSlug, 'attr') ?>"
        required
        maxlength="50"
        placeholder="content_editor"
        <?= $isEdit ? 'readonly' : '' ?>
    >
    <p class="hint">Use letters, numbers, and underscores. The slug is saved in <code>users.role</code>.</p>

    <?php if ($roleSystem) : ?>
        <div class="err">System roles are protected by the User Manager contract and cannot be edited here.</div>
    <?php endif ?>

    <label for="name">Name</label>
    <input type="text" name="name" id="name" value="<?= old('name', $roleName, 'attr') ?>" required maxlength="100" <?= $roleSystem ? 'readonly' : '' ?>>

    <label for="description">Description</label>
    <textarea name="description" id="description" maxlength="255" rows="3" required <?= $roleSystem ? 'readonly' : '' ?>><?= esc(old('description', $roleDescription)) ?></textarea>
    <p class="hint">Describe what this level is intended to do. Maximum 255 characters.</p>

    <label for="level">Level</label>
    <input type="number" name="level" id="level" value="<?= old('level', $roleLevel, 'attr') ?>" min="0" max="10" required <?= $roleSystem ? 'readonly' : '' ?>>
    <p class="hint">Use levels 0 to 10. A user receives all active roles with a level less than or equal to their primary role.</p>

    <label class="field-check">
        <input type="checkbox" name="is_active" value="1" <?= old('is_active', $roleActive ? '1' : '') ? 'checked' : '' ?> <?= $roleSystem ? 'disabled' : '' ?>>
        <span class="field-check-text">Role is active and assignable</span>
    </label>

    <div class="actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save role' : 'Create role' ?></button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Roles')) ?>">Back to roles</a>
        <?php if ($isEdit) : ?>
            <button
                type="submit"
                class="btn btn-danger"
                formaction="<?= esc(site_url('Member/User/Roles/Delete/' . $roleId)) ?>"
            >Delete role</button>
        <?php endif ?>
    </div>
</form>
<?= $this->endSection() ?>
