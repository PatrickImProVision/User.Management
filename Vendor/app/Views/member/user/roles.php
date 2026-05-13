<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Manage Roles</h1>
<p class="lead">Roles are stored in the database. Higher levels include lower-level access.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <div class="role-list-head">
        <h2>Current roles</h2>
        <div class="role-list-head-actions">
            <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/AssignRole')) ?>">Assign User Roles</a>
            <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Roles/New')) ?>">Add Role</a>
        </div>
    </div>
    <div class="role-list-columns" aria-hidden="true">
        <span>Role</span>
        <span>Level</span>
        <span>Status</span>
        <span>Type</span>
        <span>Actions</span>
    </div>
    <?php foreach ($roles as $role) : ?>
        <div class="role-list-row">
            <div class="role-list-main">
                <strong><?= esc((string) $role['name']) ?></strong>
                <p><code><?= esc((string) $role['slug']) ?></code></p>
                <?php if (trim((string) ($role['description'] ?? '')) !== '') : ?>
                    <p><?= esc((string) $role['description']) ?></p>
                <?php endif ?>
            </div>
            <div class="role-list-level">
                <span class="role-level-badge"><?= esc((string) $role['level']) ?></span>
            </div>
            <div>
                <span class="status-pill <?= ! empty($role['is_active']) ? 'status-active' : 'status-inactive' ?>">
                    <?= ! empty($role['is_active']) ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <div>
                <span class="status-pill <?= ! empty($role['is_system']) ? 'status-system' : 'status-custom' ?>">
                    <?= ! empty($role['is_system']) ? 'System' : 'Custom' ?>
                </span>
            </div>
            <div class="role-list-actions">
                <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Roles/View/' . (int) $role['id'])) ?>">View</a>
                <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Roles/Edit/' . (int) $role['id'])) ?>">Edit</a>
                <form method="post" action="<?= esc(site_url('Member/User/Roles/Delete/' . (int) $role['id'])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="actions">
    <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/MyProfile')) ?>">Back to profile</a>
</div>
<?= $this->endSection() ?>
