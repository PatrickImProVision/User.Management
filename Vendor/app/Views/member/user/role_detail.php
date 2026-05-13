<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Role Details</h1>
<p class="lead">Review this role before editing or deleting it.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <h2><?= esc((string) $role['name']) ?></h2>
    <p>Slug: <code><?= esc((string) $role['slug']) ?></code></p>
    <p>Description: <?= esc((string) ($role['description'] ?? '')) ?></p>
    <p>Level: <code><?= esc((string) $role['level']) ?></code></p>
    <p>Status: <code><?= ! empty($role['is_active']) ? 'active' : 'inactive' ?></code></p>
    <p>Type: <code><?= ! empty($role['is_system']) ? 'system' : 'custom' ?></code></p>
    <?php if (! empty($role['created_at'])) : ?>
        <p>Created: <code><?= esc((string) $role['created_at']) ?></code></p>
    <?php endif ?>
    <?php if (! empty($role['updated_at'])) : ?>
        <p>Updated: <code><?= esc((string) $role['updated_at']) ?></code></p>
    <?php endif ?>

    <div class="actions">
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Roles')) ?>">Back to roles</a>
        <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Roles/Edit/' . (int) $role['id'])) ?>">Edit role</a>
        <form method="post" action="<?= esc(site_url('Member/User/Roles/Delete/' . (int) $role['id'])) ?>" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete role</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
