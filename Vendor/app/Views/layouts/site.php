<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Product Store') ?></title>
    <style>
        :root { --bg: #0f1419; --card: #1a2332; --text: #e7ecf3; --muted: #8b9cb3; --accent: #3d8bfd; --danger: #e05252; --ok: #3ecf8e; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Segoe UI, Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; min-height: 100vh; }
        .topbar { border-bottom: 1px solid rgba(255,255,255,.06); background: rgba(12,16,22,.92); position: sticky; top: 0; z-index: 10; backdrop-filter: blur(8px); }
        .nav-wrap { max-width: 900px; margin: 0 auto; padding: 0.75rem 1.25rem; display: flex; gap: 1rem; align-items: center; justify-content: space-between; }
        .brand { color: var(--text); text-decoration: none; font-weight: 650; letter-spacing: 0.01em; }
        .nav { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
        .nav a, .nav summary { color: var(--text); text-decoration: none; font-size: 0.9rem; padding: 0.45rem 0.65rem; border-radius: 6px; cursor: pointer; list-style: none; }
        .nav a:hover, .nav summary:hover { background: rgba(255,255,255,.08); }
        .nav details { position: relative; }
        .nav summary::-webkit-details-marker { display: none; }
        .nav summary::after { content: "▾"; color: var(--muted); margin-left: 0.35rem; font-size: 0.75rem; }
        .nav details:not([open]) > .dropdown { display: none; }
        .nav details:hover > .dropdown, .nav details:focus-within > .dropdown, .nav details[open] > .dropdown { display: block; }
        .dropdown { min-width: 210px; position: absolute; right: 0; top: 100%; background: var(--card); border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 0.35rem; box-shadow: 0 16px 35px rgba(0,0,0,.28); }
        .dropdown a { display: block; white-space: nowrap; color: var(--text); }
        .dropdown form { margin: 0; }
        .nav button.nav-link { width: 100%; display: block; text-align: left; background: transparent; color: var(--text); font-weight: 400; padding: 0.45rem 0.65rem; border-radius: 6px; }
        .nav button.nav-link:hover { background: rgba(255,255,255,.08); }
        @media (max-width: 620px) { .nav-wrap { align-items: flex-start; flex-direction: column; } .dropdown { left: 0; right: auto; } }
        .wrap { max-width: 640px; margin: 0 auto; padding: 2rem 1.25rem; }
        .wrap-wide { max-width: 1180px; }
        h1 { font-size: 1.35rem; font-weight: 600; margin: 0 0 0.5rem; }
        p.lead { color: var(--muted); margin: 0 0 1.5rem; font-size: 0.95rem; }
        .card { background: var(--card); border-radius: 10px; padding: 1.5rem; border: 1px solid rgba(255,255,255,.06); margin-bottom: 1rem; }
        .card:last-of-type { margin-bottom: 0; }
        label { display: block; font-size: 0.8rem; color: var(--muted); margin: 0.75rem 0 0.25rem; }
        input:not([type="checkbox"]):not([type="radio"]), select, textarea { width: 100%; padding: 0.55rem 0.65rem; border-radius: 6px; border: 1px solid rgba(255,255,255,.12); background: #0c1016; color: var(--text); font-size: 0.95rem; }
        input[type="checkbox"], input[type="radio"] { width: 1.125rem; height: 1.125rem; margin: 0; padding: 0; flex-shrink: 0; accent-color: var(--accent); cursor: pointer; }
        label.field-check { display: flex; align-items: flex-start; gap: 0.65rem; margin: 0.6rem 0 0; font-size: 0.9rem; color: var(--text); line-height: 1.45; cursor: pointer; }
        label.field-check:first-of-type { margin-top: 0; }
        label.field-check .field-check-text { flex: 1; min-width: 0; color: var(--text); }
        label.field-check .field-check-text code { vertical-align: baseline; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .row-host-port { grid-template-columns: 1fr minmax(5rem, 6.75rem); align-items: end; }
        .row-db-prefix { grid-template-columns: 1fr minmax(6.5rem, 9rem); align-items: start; }
        .row-user-pass { grid-template-columns: 1fr 1fr; align-items: end; }
        .field-password { position: relative; width: 100%; }
        .field-password > input { padding-right: 5.75rem; position: relative; z-index: 1; }
        .field-password > input::-ms-reveal, .field-password > input::-ms-clear { display: none; }
        @media (max-width: 520px) { .row { grid-template-columns: 1fr; } }
        .actions { margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
        button, .btn { cursor: pointer; border: 0; border-radius: 6px; padding: 0.6rem 1rem; font-size: 0.9rem; font-weight: 500; text-decoration: none; display: inline-block; }
        button.password-toggle {
            position: absolute;
            right: 0.35rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            min-width: 4.35rem;
            background: #263245;
            color: var(--text);
            border: 1px solid rgba(255,255,255,.14);
            padding: 0.35rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 4px;
            line-height: 1.2;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        button.password-toggle:hover { background: #30405a; }
        button.password-toggle:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
        button.password-toggle:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-secondary { background: rgba(255,255,255,.08); color: var(--text); }
        .btn-danger { background: var(--danger); color: #fff; }
        a.btn-primary, .prose a.btn-primary { color: #fff; }
        a.btn-secondary, .prose a.btn-secondary { color: var(--text); }
        a.btn-danger, .prose a.btn-danger { color: #fff; }
        .err { background: rgba(224,82,82,.12); border: 1px solid rgba(224,82,82,.35); color: #ffb4b4; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .ok { background: rgba(62,207,142,.12); border: 1px solid rgba(62,207,142,.35); color: #b8f5d3; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .hint { font-size: 0.8rem; color: var(--muted); margin-top: 0.35rem; }
        code { font-size: 0.85em; background: rgba(0,0,0,.35); padding: 0.1em 0.35em; border-radius: 4px; }
        .prose h2 { font-size: 1rem; font-weight: 600; margin: 0 0 0.5rem; color: var(--text); }
        .prose p { color: var(--muted); margin: 0.5rem 0; }
        .prose pre { background: #0c1016; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 0.85rem 1rem; overflow-x: auto; font-size: 0.82rem; color: var(--text); margin: 0.75rem 0; }
        .prose a { color: var(--accent); text-decoration: none; }
        .prose a:hover { text-decoration: underline; }
        .profile-head { display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; }
        .user-avatar, .user-avatar-fallback { width: 5.25rem; height: 5.25rem; border-radius: 50%; border: 2px solid rgba(255,255,255,.14); flex-shrink: 0; }
        .user-avatar { display: block; object-fit: cover; background: #0c1016; }
        .user-avatar-fallback { display: grid; place-items: center; background: linear-gradient(135deg, rgba(61,139,253,.9), rgba(62,207,142,.65)); color: #fff; font-size: 2rem; font-weight: 700; }
        .profile-head h2 { margin-bottom: 0.15rem; }
        .role-list-head { display: flex; gap: 1rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .role-list-head-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .role-list-columns, .role-list-row { display: grid; grid-template-columns: minmax(12rem, 1.4fr) 4.5rem 6.75rem 6.75rem 18rem; gap: 0.75rem; align-items: center; }
        .role-list-columns { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .role-list-row { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.8rem 0.75rem; margin-top: 0.65rem; }
        .role-list-row:hover { background: rgba(255,255,255,.045); }
        .role-list-main p { margin-bottom: 0; }
        .role-list-level { display: flex; align-items: center; justify-content: center; }
        .role-level-badge { display: inline-grid; place-items: center; min-width: 2.25rem; height: 2.25rem; border-radius: 999px; background: rgba(61,139,253,.16); color: #b9d6ff; border: 1px solid rgba(61,139,253,.35); font-weight: 700; }
        .status-pill { display: inline-flex; align-items: center; justify-content: center; width: 6.25rem; border-radius: 999px; padding: 0.25rem 0.55rem; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(255,255,255,.12); }
        .status-active { background: rgba(62,207,142,.13); color: #b8f5d3; border-color: rgba(62,207,142,.35); }
        .status-inactive { background: rgba(139,156,179,.12); color: #c0cad8; border-color: rgba(139,156,179,.28); }
        .status-system { background: rgba(61,139,253,.13); color: #b9d6ff; border-color: rgba(61,139,253,.32); }
        .status-custom { background: rgba(255,255,255,.08); color: var(--text); }
        .role-list-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; align-items: stretch; }
        .role-list-actions form { margin: 0; display: contents; }
        .role-list-actions .btn { width: 100%; min-width: 0; min-height: 2.35rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .user-list-columns, .user-list-row { display: grid; grid-template-columns: minmax(16rem, 1.45fr) minmax(8rem, 0.8fr) 6.75rem minmax(14rem, 1fr) 15rem; gap: 0.75rem; align-items: center; }
        .user-list-columns { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .user-list-row { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.8rem 0.75rem; margin-top: 0.65rem; }
        .user-list-row:hover { background: rgba(255,255,255,.045); }
        .user-list-account { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
        .user-list-row .role-level-badge { width: 100%; height: auto; min-height: 2.25rem; padding: 0.35rem 0.7rem; text-align: center; }
        .user-role-form { display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: center; margin: 0; }
        .user-role-form .btn { white-space: nowrap; min-height: 2.35rem; }
        .user-list-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; align-items: stretch; }
        .user-list-actions form { margin: 0; display: contents; }
        .user-list-actions .btn { width: 100%; min-width: 0; min-height: 2.35rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .member-list-columns, .member-list-row { display: grid; grid-template-columns: minmax(13rem, 1.2fr) minmax(13rem, 1fr) minmax(8rem, 0.8fr) 6.75rem 6.5rem; gap: 0.75rem; align-items: center; }
        .member-list-columns { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,.08); margin-top: 1rem; }
        .member-list-row { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.8rem 0.75rem; margin-top: 0.65rem; }
        .member-list-row:hover { background: rgba(255,255,255,.045); }
        .member-list-user { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
        .member-list-profile-link { display: inline-flex; align-items: center; gap: 0.65rem; color: var(--text); text-decoration: none; }
        .member-list-profile-link:hover { color: var(--accent); text-decoration: none; }
        .member-list-avatar, .member-list-avatar-fallback { width: 2.35rem; height: 2.35rem; border-radius: 50%; border: 1px solid rgba(255,255,255,.14); flex-shrink: 0; }
        .member-list-avatar { display: block; object-fit: cover; background: #0c1016; }
        .member-list-avatar-fallback { display: grid; place-items: center; background: linear-gradient(135deg, rgba(61,139,253,.9), rgba(62,207,142,.65)); color: #fff; font-size: 0.95rem; font-weight: 700; }
        .member-list-row .role-level-badge { width: 100%; height: auto; min-height: 2.25rem; padding: 0.35rem 0.7rem; text-align: center; }
        .member-list-row .btn { width: 100%; min-height: 2.35rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        @media (max-width: 760px) { .role-list-columns, .user-list-columns, .member-list-columns { display: none; } .role-list-head, .role-list-row, .user-list-row, .member-list-row { display: flex; align-items: flex-start; flex-direction: column; } .role-list-actions, .user-list-actions, .user-role-form { width: 100%; } }
        .site-footer { max-width: 1040px; margin: 0 auto; padding: 0 1.25rem 1.5rem; color: var(--muted); font-size: 0.75rem; text-align: center; }
        .site-footer code { color: var(--text); }
        .meta { font-size: 0.75rem; color: var(--muted); text-align: center; margin-top: 1.5rem; }
    </style>
</head>
<body>
<?php
helper('form');

$installed = \App\Libraries\InstallationState::isInstalled();
$memberUserId = session()->get('member_user_id');
$memberUsername = (string) (session()->get('member_username') ?? '');
$memberLoggedIn = is_numeric($memberUserId);
$memberCanManageRoles = $memberLoggedIn && (bool) session()->get('member_can_manage_roles');
if ($memberLoggedIn && session()->get('member_can_manage_roles') === null) {
    $memberRole = (string) (session()->get('member_role') ?? '');
    $memberCanManageRoles = $memberRole !== '' && (new \App\Libraries\RoleService())->isAdministrator($memberRole);
}
$showApplicationMenu = $installed ? $memberCanManageRoles : (! $memberLoggedIn || $memberCanManageRoles);
?>
<header class="topbar">
    <div class="nav-wrap">
        <a class="brand" href="<?= esc(site_url('/')) ?>">Product Store</a>
        <nav class="nav" aria-label="Main navigation">
            <a href="<?= esc(site_url('/')) ?>">Home</a>
            <?php if ($installed) : ?>
                <details>
                    <summary><?= $memberLoggedIn ? esc($memberUsername !== '' ? $memberUsername : 'Account') : 'Member' ?></summary>
                    <div class="dropdown">
                        <?php if ($memberLoggedIn) : ?>
                            <a href="<?= esc(site_url('Member/User/MyProfile')) ?>">My Profile</a>
                            <a href="<?= esc(site_url('Member/List')) ?>">Member List</a>
                            <?php if ($memberCanManageRoles) : ?>
                                <a href="<?= esc(site_url('Member/User/Create')) ?>">Create User</a>
                                <a href="<?= esc(site_url('Member/User/Roles')) ?>">Manage Roles</a>
                                <a href="<?= esc(site_url('Member/User/AssignRole')) ?>">Assign User Roles</a>
                            <?php endif ?>
                            <form method="post" action="<?= esc(site_url('Member/User/Logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="nav-link" type="submit">Logout</button>
                            </form>
                        <?php else : ?>
                            <a href="<?= esc(site_url('Member/User/Login')) ?>">Login</a>
                            <a href="<?= esc(site_url('Member/User/Register')) ?>">Register</a>
                            <a href="<?= esc(site_url('Member/User/ForgotPassword')) ?>">Forgot Password</a>
                        <?php endif ?>
                    </div>
                </details>
            <?php endif ?>
            <?php if ($showApplicationMenu) : ?>
                <details>
                    <summary>Application</summary>
                    <div class="dropdown">
                        <?php if ($installed) : ?>
                            <a href="<?= esc(site_url('install/uninstall')) ?>">Uninstall</a>
                        <?php else : ?>
                            <a href="<?= esc(site_url('install')) ?>">Install / Restore</a>
                        <?php endif ?>
                    </div>
                </details>
            <?php endif ?>
        </nav>
    </div>
</header>
<div class="wrap <?= ! empty($wideLayout) ? 'wrap-wide' : '' ?>">
    <?= $this->renderSection('main') ?>
</div>
<footer class="site-footer">
    Environment: <code><?= esc(ENVIRONMENT) ?></code>
    · Rendered in <code>{elapsed_time}</code> seconds
    · Memory: <code>{memory_usage}</code> MB
</footer>
<script>
(function () {
    document.querySelectorAll('.nav details').forEach(function (details) {
        details.addEventListener('mouseenter', function () {
            details.open = true;
        });
        details.addEventListener('mouseleave', function () {
            details.open = false;
        });
        details.addEventListener('focusin', function () {
            details.open = true;
        });
        details.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (! details.contains(document.activeElement)) {
                    details.open = false;
                }
            }, 0);
        });
    });
})();
</script>
</body>
</html>
