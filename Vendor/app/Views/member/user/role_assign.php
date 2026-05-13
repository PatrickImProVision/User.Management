<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Assign User Roles</h1>
<p class="lead">Use the user list to view, edit, delete, or assign roles to accounts below your current role level.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <div class="role-list-head">
        <h2>Current users</h2>
        <div class="role-list-head-actions">
            <a class="btn btn-primary" href="<?= esc(site_url('Member/User/Create')) ?>">Create User</a>
            <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Roles')) ?>">Manage Roles</a>
        </div>
    </div>
    <?php if (empty($users)) : ?>
        <div class="err">No users are currently available for your role level to manage.</div>
    <?php endif ?>

    <?php if (empty($roleOptions)) : ?>
        <div class="err">No assignable roles are currently available below your role level.</div>
    <?php endif ?>

    <label for="user-search">Search users</label>
    <input type="search" id="user-search" placeholder="Search by username, e-mail, or role..." autocomplete="off" data-search-url="<?= esc(site_url('Member/User/AssignRole/Search'), 'attr') ?>">
    <p class="hint" id="user-search-status">Type to search users without reloading the page.</p>

    <div class="user-list-columns" aria-hidden="true">
        <span>User</span>
        <span>Current role</span>
        <span>Status</span>
        <span>Assign role</span>
        <span>Actions</span>
    </div>
    <div id="user-list-results" data-csrf-name="<?= esc(csrf_token(), 'attr') ?>" data-csrf-hash="<?= esc(csrf_hash(), 'attr') ?>">
        <?php foreach ($users as $user) : ?>
            <div class="user-list-row">
                <div class="role-list-main user-list-account">
                    <?php if (! empty($user['profile_image_url'])) : ?>
                        <img class="member-list-avatar" src="<?= esc((string) $user['profile_image_url']) ?>" alt="<?= esc((string) $user['username'], 'attr') ?> profile image">
                    <?php else : ?>
                        <div class="member-list-avatar-fallback" aria-label="No profile image"><?= esc((string) ($user['profile_initial'] ?? '?')) ?></div>
                    <?php endif ?>
                    <div>
                        <strong><?= esc((string) $user['username']) ?></strong>
                        <p><code><?= esc((string) $user['email']) ?></code></p>
                    </div>
                </div>
                <div>
                    <span class="role-level-badge" title="<?= esc((string) $user['role'], 'attr') ?>">
                        <?= esc((string) $user['role_name']) ?>
                    </span>
                </div>
                <div>
                    <span class="status-pill <?= ! empty($user['is_active']) ? 'status-active' : 'status-inactive' ?>">
                        <?= ! empty($user['is_active']) ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                <form method="post" action="<?= esc(site_url('Member/User/AssignRole')) ?>" class="user-role-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= esc((string) $user['id'], 'attr') ?>">
                    <?php
                    $selectedRole = old('user_id') === (string) $user['id']
                        ? old('role', (string) $user['role'])
                        : (string) $user['role'];
                    ?>
                    <select name="role" aria-label="New role for <?= esc((string) $user['username'], 'attr') ?>" required <?= empty($roleOptions) ? 'disabled' : '' ?>>
                        <option value="">Choose role...</option>
                        <?php foreach ($roleOptions as $slug => $name) : ?>
                            <option value="<?= esc((string) $slug, 'attr') ?>" <?= $selectedRole === (string) $slug ? 'selected' : '' ?>>
                                <?= esc((string) $name) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <button type="submit" class="btn btn-primary" <?= empty($roleOptions) ? 'disabled' : '' ?>>Save Role</button>
                </form>
                <div class="user-list-actions">
                    <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Profile/' . (int) $user['id'])) ?>">View</a>
                    <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Edit/' . (int) $user['id'])) ?>">Edit</a>
                    <form method="post" action="<?= esc(site_url('Member/User/Delete/' . (int) $user['id'])) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <p class="hint">You cannot assign your own role, assign inactive roles, or assign roles at/above your level.</p>
</div>

<div class="actions">
    <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/MyProfile')) ?>">Back to profile</a>
</div>
<script>
(function () {
    var search = document.getElementById('user-search');
    var results = document.getElementById('user-list-results');
    var status = document.getElementById('user-search-status');
    if (! search || ! results || ! status) {
        return;
    }

    var roleOptions = <?= json_encode($roleOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var roleKeys = Object.keys(roleOptions || {});
    var csrfName = results.getAttribute('data-csrf-name') || '';
    var csrfHash = results.getAttribute('data-csrf-hash') || '';
    var timer = null;
    var requestId = 0;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function csrfField() {
        if (csrfName === '' || csrfHash === '') {
            return '';
        }

        return '<input type="hidden" name="' + escapeHtml(csrfName) + '" value="' + escapeHtml(csrfHash) + '">';
    }

    function roleSelect(user) {
        var disabled = roleKeys.length === 0 ? ' disabled' : '';
        var html = '<select name="role" aria-label="New role for ' + escapeHtml(user.username) + '" required' + disabled + '>';
        html += '<option value="">Choose role...</option>';
        roleKeys.forEach(function (slug) {
            html += '<option value="' + escapeHtml(slug) + '"' + (String(user.role) === slug ? ' selected' : '') + '>';
            html += escapeHtml(roleOptions[slug]) + '</option>';
        });
        html += '</select>';

        return html;
    }

    function avatar(user) {
        if (user.profile_image_url) {
            return '<img class="member-list-avatar" src="' + escapeHtml(user.profile_image_url) + '" alt="' + escapeHtml(user.username) + ' profile image">';
        }

        return '<div class="member-list-avatar-fallback" aria-label="No profile image">' + escapeHtml(user.profile_initial || '?') + '</div>';
    }

    function renderUsers(users) {
        if (! Array.isArray(users) || users.length === 0) {
            results.innerHTML = '<div class="err">No users matched your search.</div>';
            return;
        }

        results.innerHTML = users.map(function (user) {
            var active = Boolean(user.is_active);
            var disabled = roleKeys.length === 0 ? ' disabled' : '';

            return '<div class="user-list-row">'
                + '<div class="role-list-main user-list-account">' + avatar(user) + '<div><strong>' + escapeHtml(user.username) + '</strong><p><code>' + escapeHtml(user.email) + '</code></p></div></div>'
                + '<div><span class="role-level-badge" title="' + escapeHtml(user.role) + '">' + escapeHtml(user.role_name) + '</span></div>'
                + '<div><span class="status-pill ' + (active ? 'status-active' : 'status-inactive') + '">' + (active ? 'Active' : 'Inactive') + '</span></div>'
                + '<form method="post" action="' + escapeHtml(user.assign_url) + '" class="user-role-form">'
                + csrfField()
                + '<input type="hidden" name="user_id" value="' + escapeHtml(user.id) + '">'
                + roleSelect(user)
                + '<button type="submit" class="btn btn-primary"' + disabled + '>Save Role</button>'
                + '</form>'
                + '<div class="user-list-actions">'
                + '<a class="btn btn-secondary" href="' + escapeHtml(user.view_url) + '">View</a>'
                + '<a class="btn btn-secondary" href="' + escapeHtml(user.edit_url) + '">Edit</a>'
                + '<form method="post" action="' + escapeHtml(user.delete_url) + '">' + csrfField() + '<button type="submit" class="btn btn-danger">Delete</button></form>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    function runSearch() {
        var id = ++requestId;
        var url = search.getAttribute('data-search-url') + '?q=' + encodeURIComponent(search.value.trim());
        status.textContent = 'Searching...';

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('Search failed.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (id !== requestId) {
                    return;
                }

                renderUsers(payload.users || []);
                status.textContent = search.value.trim() === ''
                    ? 'Showing all manageable users.'
                    : 'Showing search results for "' + search.value.trim() + '".';
            })
            .catch(function () {
                if (id !== requestId) {
                    return;
                }

                status.textContent = 'Could not search users. Try again.';
            });
    }

    search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(runSearch, 250);
    });
})();
</script>
<?= $this->endSection() ?>
