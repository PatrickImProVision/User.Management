<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\BaseController;
use App\Libraries\AppDatabase;
use App\Libraries\RoleService;
use CodeIgniter\HTTP\ResponseInterface;

class User extends BaseController
{
    protected $helpers = ['form', 'url'];

    public function profile(): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        return view('member/user/profile', [
            'title'          => 'User Profile',
            'user'           => $current,
            'primaryRole'    => $this->roleService()->roleName((string) ($current['role'] ?? 'user')),
            'effectiveRoles' => $this->roleService()->effectiveRoleNames((string) ($current['role'] ?? 'user')),
        ]);
    }

    public function memberList(): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        return view('member/user/list', [
            'title'      => 'Member List',
            'wideLayout' => true,
            'users'      => $this->memberListUsers(),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function searchMemberList(): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $this->response->setStatusCode(401)->setJSON([
                'users' => [],
                'error' => 'Log in to continue.',
            ]);
        }

        $users = [];
        foreach ($this->memberListUsers((string) $this->request->getGet('q')) as $user) {
            $users[] = [
                'id'                => (int) ($user['id'] ?? 0),
                'username'          => (string) ($user['username'] ?? ''),
                'email'             => (string) ($user['email'] ?? ''),
                'role'              => (string) ($user['role'] ?? ''),
                'role_name'         => (string) ($user['role_name'] ?? ''),
                'is_active'         => (bool) ($user['is_active'] ?? false),
                'profile_initial'   => (string) ($user['profile_initial'] ?? '?'),
                'profile_image_url' => (string) ($user['profile_image_url'] ?? ''),
                'profile_url'       => (string) ($user['profile_url'] ?? ''),
            ];
        }

        return $this->response->setJSON([
            'users' => $users,
        ]);
    }

    public function register(): ResponseInterface|string
    {
        if ($this->currentUser() !== null) {
            return redirect()->to(site_url('Member/User/MyProfile'));
        }

        return view('member/user/register', [
            'title'  => 'Register',
            'errors' => $this->flashErrors(),
        ]);
    }

    public function create(): ResponseInterface
    {
        if ($this->currentUser() !== null) {
            return redirect()->to(site_url('Member/User/MyProfile'));
        }

        $rules = [
            'username'         => 'required|min_length[3]|max_length[100]',
            'email'            => 'required|valid_email|max_length[191]',
            'password'         => 'required|min_length[8]|max_length[200]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $db = AppDatabase::connection();
        $registrationError = 'This e-mail cannot be registered.';
        $existing = $db->table('users')
            ->groupStart()
            ->where('email', (string) $this->request->getPost('email'))
            ->orWhere('username', (string) $this->request->getPost('username'))
            ->groupEnd()
            ->get()
            ->getRowArray();
        if (is_array($existing)) {
            return redirect()->back()->withInput()->with('errors', ['register' => $registrationError]);
        }

        $activationGuid = $this->newGuid();
        $deactivationGuid = $this->newGuid();

        try {
            $db->table('users')->insert([
                'username'          => (string) $this->request->getPost('username'),
                'email'             => (string) $this->request->getPost('email'),
                'password_hash'     => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role'              => 'user',
                'profile_image'     => null,
                'is_active'         => false,
                'activation_guid'   => $activationGuid,
                'deactivation_guid' => $deactivationGuid,
                'reset_guid'        => null,
                'last_login_at'     => null,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => null,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errors', ['register' => $registrationError]);
        }

        return redirect()->to(site_url('Member/User/Login'))->with(
            'message',
            'Registration saved. Activate using: ' . site_url('Member/User/Activate/' . $activationGuid),
        );
    }

    public function newUser(): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['user' => 'Only administrator-level accounts can create users.']);
        }

        return view('member/user/create', [
            'title'       => 'Create User',
            'roleOptions' => $this->roleService()->assignableRoleOptionsFor((string) ($current['role'] ?? '')),
            'errors'      => $this->flashErrors(),
        ]);
    }

    public function createUser(): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['user' => 'Only administrator-level accounts can create users.']);
        }

        $rules = [
            'username'         => 'required|min_length[3]|max_length[100]',
            'email'            => 'required|valid_email|max_length[191]',
            'password'         => 'required|min_length[8]|max_length[200]',
            'password_confirm' => 'required|matches[password]',
            'role'             => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $role = (string) $this->request->getPost('role');
        $roleOptions = $this->roleService()->assignableRoleOptionsFor((string) ($current['role'] ?? ''));
        if (! array_key_exists($role, $roleOptions)) {
            return redirect()->back()->withInput()->with('errors', ['role' => 'Choose a role below your own level.']);
        }

        $isActive = $this->request->getPost('is_active') !== null;
        $db = AppDatabase::connection();

        try {
            $db->table('users')->insert([
                'username'          => (string) $this->request->getPost('username'),
                'email'             => (string) $this->request->getPost('email'),
                'password_hash'     => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'role'              => $role,
                'profile_image'     => null,
                'is_active'         => $isActive,
                'activation_guid'   => $isActive ? null : $this->newGuid(),
                'deactivation_guid' => $this->newGuid(),
                'reset_guid'        => null,
                'last_login_at'     => null,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => null,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errors', ['user' => $e->getMessage()]);
        }

        return redirect()->to(site_url('Member/User/AssignRole'))->with('message', 'User created. You can now manage the account from the list.');
    }

    public function login(): ResponseInterface|string
    {
        if ($this->currentUser() !== null) {
            return redirect()->to(site_url('Member/User/MyProfile'));
        }

        return view('member/user/login', [
            'title'  => 'Login',
            'errors' => $this->flashErrors(),
        ]);
    }

    public function authenticate(): ResponseInterface
    {
        if ($this->currentUser() !== null) {
            return redirect()->to(site_url('Member/User/MyProfile'));
        }

        $login = trim((string) $this->request->getPost('login'));
        $password = (string) $this->request->getPost('password');
        if ($login === '' || $password === '') {
            return redirect()->back()->withInput()->with('errors', ['login' => 'Enter username/e-mail and password.']);
        }

        $db = AppDatabase::connection();
        $user = $db->table('users')
            ->groupStart()
                ->where('username', $login)
                ->orWhere('email', $login)
            ->groupEnd()
            ->get()
            ->getRowArray();

        if (! is_array($user) || ! password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return redirect()->back()->withInput()->with('errors', ['login' => 'Invalid login details.']);
        }

        if (! (bool) ($user['is_active'] ?? false)) {
            return redirect()->back()->withInput()->with('errors', ['login' => 'Account is not active. Use the activation link first.']);
        }

        $this->rememberCurrentUser($user);

        $db->table('users')->where('id', (int) $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(site_url('Member/User/MyProfile'))->with('message', 'Login successful.');
    }

    public function forgotPassword(): ResponseInterface|string
    {
        if ($this->currentUser() !== null) {
            return redirect()->to(site_url('Member/User/MyProfile'));
        }

        return view('member/user/forgot_password', [
            'title'  => 'Forgot Password',
            'errors' => $this->flashErrors(),
        ]);
    }

    public function sendForgotPassword(): ResponseInterface
    {
        if ($this->currentUser() !== null) {
            return redirect()->to(site_url('Member/User/MyProfile'));
        }

        $email = trim((string) $this->request->getPost('email'));
        if ($email === '') {
            return redirect()->back()->withInput()->with('errors', ['email' => 'Enter your e-mail address.']);
        }

        $db = AppDatabase::connection();
        $user = $db->table('users')->where('email', $email)->get()->getRowArray();
        if (! is_array($user)) {
            return redirect()->back()->withInput()->with('errors', ['email' => 'No account found for that e-mail.']);
        }

        $guid = $this->newGuid();
        $db->table('users')->where('id', (int) $user['id'])->update([
            'reset_guid' => $guid,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->back()->with('message', 'Password reset token created: ' . $guid);
    }

    public function activate(string $guid): ResponseInterface|string
    {
        $db = AppDatabase::connection();
        $user = $db->table('users')->where('activation_guid', $guid)->get()->getRowArray();
        if (! is_array($user)) {
            return view('member/user/activation', [
                'title'   => 'Activate Account',
                'success' => false,
                'message' => 'Activation link is invalid or already used.',
            ]);
        }

        $db->table('users')->where('id', (int) $user['id'])->update([
            'is_active'       => true,
            'activation_guid' => null,
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return view('member/user/activation', [
            'title'   => 'Activate Account',
            'success' => true,
            'message' => 'Account activated. You can now log in.',
        ]);
    }

    public function edit(int $id): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $user = AppDatabase::connection()->table('users')->where('id', $id)->get()->getRowArray();
        if (! is_array($user)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['edit' => 'User not found.']);
        }

        if ((int) ($current['id'] ?? 0) !== (int) ($user['id'] ?? 0) && ! $this->canChangeUserRole($current, $user)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['edit' => 'You cannot edit that profile.']);
        }

        $canChangeRole = $this->canChangeUserRole($current, $user);

        return view('member/user/edit', [
            'title'          => (int) ($current['id'] ?? 0) === (int) ($user['id'] ?? 0) ? 'Edit Profile' : 'Edit User',
            'user'           => $user,
            'current'        => $current,
            'canManageRoles' => $canChangeRole,
            'roleOptions'    => $canChangeRole
                ? $this->roleService()->assignableRoleOptionsFor((string) ($current['role'] ?? ''))
                : [],
            'effectiveRoles' => $this->roleService()->effectiveRoleNames((string) ($user['role'] ?? 'user')),
            'backUrl'        => (int) ($current['id'] ?? 0) === (int) ($user['id'] ?? 0)
                ? site_url('Member/User/MyProfile')
                : site_url('Member/User/Profile/' . (int) $user['id']),
            'errors'         => $this->flashErrors(),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $rules = [
            'username' => 'required|min_length[3]|max_length[100]',
            'email'    => 'required|valid_email|max_length[191]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $db = AppDatabase::connection();
        $user = $db->table('users')->where('id', $id)->get()->getRowArray();
        if (! is_array($user)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['edit' => 'User not found.']);
        }

        if ((int) ($current['id'] ?? 0) !== (int) ($user['id'] ?? 0) && ! $this->canChangeUserRole($current, $user)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['edit' => 'You cannot edit that profile.']);
        }

        $data = [
            'username'   => (string) $this->request->getPost('username'),
            'email'      => (string) $this->request->getPost('email'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->canChangeUserRole($current, $user)) {
            $role = (string) $this->request->getPost('role');
            if (! $this->roleService()->canChangeUserRole(
                (string) ($current['role'] ?? ''),
                (string) ($user['role'] ?? ''),
                $role,
            )) {
                return redirect()->back()->withInput()->with('errors', ['role' => 'Choose a valid role.']);
            }

            $data['role'] = $role;
            $data['is_active'] = (string) $this->request->getPost('is_active') === '1';
            $data['activation_guid'] = $data['is_active']
                ? null
                : ((string) ($user['activation_guid'] ?? '') !== '' ? (string) $user['activation_guid'] : $this->newGuid());
        } elseif ($this->request->getPost('role') !== null) {
            return redirect()->back()->withInput()->with('errors', ['role' => 'Only a higher-level administrator can change this role.']);
        }

        $imageResult = $this->storeProfileImage();
        if ($imageResult['error'] !== null) {
            return redirect()->back()->withInput()->with('errors', ['profile_image' => $imageResult['error']]);
        }

        if ($imageResult['path'] !== null) {
            $data['profile_image'] = $imageResult['path'];
        }

        try {
            $db->table('users')->where('id', $id)->update($data);
        } catch (\Throwable $e) {
            if ($imageResult['path'] !== null) {
                $this->deletePublicUserImage($imageResult['path']);
            }

            return redirect()->back()->withInput()->with('errors', ['edit' => $e->getMessage()]);
        }

        if ($imageResult['path'] !== null) {
            $this->deletePublicUserImage((string) ($user['profile_image'] ?? ''));
        }

        $redirectUrl = (int) ($current['id'] ?? 0) === (int) ($user['id'] ?? 0)
            ? site_url('Member/User/MyProfile')
            : site_url('Member/User/Profile/' . (int) $user['id']);

        return redirect()->to($redirectUrl)->with('message', 'User updated.');
    }

    /**
     * @return array{path: string|null, error: string|null}
     */
    private function storeProfileImage(): array
    {
        $image = $this->request->getFile('profile_image');
        if ($image === null || $image->getError() === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        if (! $image->isValid()) {
            return ['path' => null, 'error' => $image->getErrorString()];
        }

        if ($image->getSizeByUnit('kb') > 2048) {
            return ['path' => null, 'error' => 'Profile image must be 2 MB or smaller.'];
        }

        $extension = strtolower($image->getClientExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return ['path' => null, 'error' => 'Profile image must be JPG, PNG, GIF, or WEBP.'];
        }

        if (@getimagesize($image->getTempName()) === false) {
            return ['path' => null, 'error' => 'Uploaded file is not a valid image.'];
        }

        $uploadDir = $this->publicUploadDirectory();
        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Could not create profile image upload folder.'];
        }

        $fileName = $image->getRandomName();
        if (! $image->move($uploadDir, $fileName)) {
            return ['path' => null, 'error' => 'Could not save profile image.'];
        }

        return ['path' => 'uploads/user-images/' . $fileName, 'error' => null];
    }

    private function publicUploadDirectory(): string
    {
        $scriptFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $publicRoot = $scriptFile !== '' ? dirname($scriptFile) : FCPATH;

        return rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'user-images' . DIRECTORY_SEPARATOR;
    }

    private function deletePublicUserImage(string $path): void
    {
        if ($path === '' || ! str_starts_with($path, 'uploads/user-images/')) {
            return;
        }

        $base = realpath($this->publicUploadDirectory());
        $file = realpath(dirname($this->publicUploadDirectory(), 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($base === false || $file === false || ! str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($file)) {
            unlink($file);
        }
    }

    public function deactivate(string $guid): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! hash_equals((string) ($current['deactivation_guid'] ?? ''), $guid)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['deactivate' => 'Invalid deactivation link.']);
        }

        AppDatabase::connection()->table('users')->where('id', (int) $current['id'])->update([
            'is_active'         => false,
            'deactivation_guid' => $this->newGuid(),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->forgetCurrentUser();

        return view('member/user/deactivated', [
            'title' => 'Account Deactivated',
        ]);
    }

    public function logout(): ResponseInterface
    {
        $this->forgetCurrentUser();

        return redirect()->to(site_url('Member/User/Login'))->with('message', 'Logged out.');
    }

    public function roles(): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        return view('member/user/roles', [
            'title'      => 'Manage Roles',
            'wideLayout' => true,
            'roles'      => $this->roleService()->listRoles(),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function assignRole(): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only higher-level administrators can assign roles.']);
        }

        return view('member/user/role_assign', [
            'title'       => 'Assign User Roles',
            'wideLayout'  => true,
            'users'       => $this->manageableUsers($current),
            'roleOptions' => $this->roleService()->assignableRoleOptionsFor((string) ($current['role'] ?? '')),
            'errors'      => $this->flashErrors(),
        ]);
    }

    public function searchUsers(): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $this->response->setStatusCode(401)->setJSON([
                'users' => [],
                'error' => 'Log in to continue.',
            ]);
        }

        if (! $this->isAdministrator($current)) {
            return $this->response->setStatusCode(403)->setJSON([
                'users' => [],
                'error' => 'Only higher-level administrators can search users.',
            ]);
        }

        $users = [];
        foreach ($this->manageableUsers($current, (string) $this->request->getGet('q')) as $user) {
            $id = (int) ($user['id'] ?? 0);
            $users[] = [
                'id'          => $id,
                'username'    => (string) ($user['username'] ?? ''),
                'email'       => (string) ($user['email'] ?? ''),
                'role'        => (string) ($user['role'] ?? ''),
                'role_name'   => (string) ($user['role_name'] ?? ''),
                'is_active'   => (bool) ($user['is_active'] ?? false),
                'profile_initial'   => (string) ($user['profile_initial'] ?? '?'),
                'profile_image_url' => (string) ($user['profile_image_url'] ?? ''),
                'view_url'    => site_url('Member/User/Profile/' . $id),
                'edit_url'    => site_url('Member/User/Edit/' . $id),
                'delete_url'  => site_url('Member/User/Delete/' . $id),
                'assign_url'  => site_url('Member/User/AssignRole'),
            ];
        }

        return $this->response->setJSON([
            'users' => $users,
        ]);
    }

    public function saveAssignedRole(): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only higher-level administrators can assign roles.']);
        }

        $userId = (int) $this->request->getPost('user_id');
        $newRole = (string) $this->request->getPost('role');
        if ($userId <= 0 || $newRole === '') {
            return redirect()->back()->withInput()->with('errors', ['role' => 'Choose a user and role.']);
        }

        $target = AppDatabase::connection()->table('users')->where('id', $userId)->get()->getRowArray();
        if (! is_array($target)) {
            return redirect()->back()->withInput()->with('errors', ['user' => 'User not found.']);
        }

        if ((int) ($current['id'] ?? 0) === (int) ($target['id'] ?? 0)) {
            return redirect()->back()->withInput()->with('errors', ['role' => 'You cannot assign your own role.']);
        }

        if (! $this->roleService()->canChangeUserRole(
            (string) ($current['role'] ?? ''),
            (string) ($target['role'] ?? ''),
            $newRole,
        )) {
            return redirect()->back()->withInput()->with('errors', ['role' => 'You can only assign active roles below your own level to users below your level.']);
        }

        AppDatabase::connection()->table('users')->where('id', (int) $target['id'])->update([
            'role'       => $newRole,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(site_url('Member/User/AssignRole'))->with('message', 'Role assigned.');
    }

    public function viewUser(int $id): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $user = AppDatabase::connection()->table('users')->where('id', $id)->get()->getRowArray();
        if (! is_array($user)) {
            return redirect()->to(site_url('Member/List'))->with('errors', ['user' => 'User not found.']);
        }

        $canManageUser = $this->canChangeUserRole($current, $user);

        return view('member/user/user_detail', [
            'title'          => 'User Details',
            'user'           => $user,
            'current'        => $current,
            'primaryRole'    => $this->roleService()->roleName((string) ($user['role'] ?? 'user')),
            'effectiveRoles' => $this->roleService()->effectiveRoleNames((string) ($user['role'] ?? 'user')),
            'canManageUser'  => $canManageUser,
            'backUrl'        => $canManageUser ? site_url('Member/User/AssignRole') : site_url('Member/List'),
            'errors'         => $this->flashErrors(),
        ]);
    }

    public function deleteUser(int $id): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['user' => 'Only administrator-level accounts can delete users.']);
        }

        $db = AppDatabase::connection();
        $user = $db->table('users')->where('id', $id)->get()->getRowArray();
        if (! is_array($user)) {
            return redirect()->to(site_url('Member/User/AssignRole'))->with('errors', ['user' => 'User not found.']);
        }

        if (! $this->canChangeUserRole($current, $user)) {
            return redirect()->to(site_url('Member/User/AssignRole'))->with('errors', ['user' => 'You cannot delete your own account or a user at/above your role level.']);
        }

        try {
            $db->table('users')->where('id', (int) $user['id'])->delete();
        } catch (\Throwable $e) {
            return redirect()->to(site_url('Member/User/AssignRole'))->with('errors', ['user' => $e->getMessage()]);
        }

        $this->deletePublicUserImage((string) ($user['profile_image'] ?? ''));

        return redirect()->to(site_url('Member/User/AssignRole'))->with('message', 'User deleted.');
    }

    public function newRole(): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        return view('member/user/role_form', [
            'title'  => 'Add Role',
            'mode'   => 'create',
            'role'   => null,
            'errors' => $this->flashErrors(),
        ]);
    }

    public function createRole(): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        $err = $this->roleService()->saveRole([
            'slug'        => (string) $this->request->getPost('slug'),
            'name'        => (string) $this->request->getPost('name'),
            'description' => (string) $this->request->getPost('description'),
            'level'       => (string) $this->request->getPost('level'),
            'is_active'   => $this->request->getPost('is_active') !== null,
        ]);

        if ($err !== null) {
            return redirect()->back()->withInput()->with('errors', ['role' => $err]);
        }

        return redirect()->to(site_url('Member/User/Roles'))->with('message', 'Role created.');
    }

    public function viewRole(int $id): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        $role = $this->roleService()->findRoleById($id);
        if ($role === null) {
            return redirect()->to(site_url('Member/User/Roles'))->with('errors', ['role' => 'Role not found.']);
        }

        return view('member/user/role_detail', [
            'title' => 'Role Details',
            'role'  => $role,
        ]);
    }

    public function editRole(int $id): ResponseInterface|string
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        $role = $this->roleService()->findRoleById($id);
        if ($role === null) {
            return redirect()->to(site_url('Member/User/Roles'))->with('errors', ['role' => 'Role not found.']);
        }

        return view('member/user/role_form', [
            'title'  => 'Edit Role',
            'mode'   => 'edit',
            'role'   => $role,
            'errors' => $this->flashErrors(),
        ]);
    }

    public function updateRole(int $id): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        $role = $this->roleService()->findRoleById($id);
        if ($role === null) {
            return redirect()->to(site_url('Member/User/Roles'))->with('errors', ['role' => 'Role not found.']);
        }

        $err = $this->roleService()->saveRole([
            'slug'        => (string) $role['slug'],
            'name'        => (string) $this->request->getPost('name'),
            'description' => (string) $this->request->getPost('description'),
            'level'       => (string) $this->request->getPost('level'),
            'is_active'   => $this->request->getPost('is_active') !== null,
        ]);

        if ($err !== null) {
            return redirect()->back()->withInput()->with('errors', ['role' => $err]);
        }

        return redirect()->to(site_url('Member/User/Roles/View/' . $id))->with('message', 'Role updated.');
    }

    public function deleteRole(int $id): ResponseInterface
    {
        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->isAdministrator($current)) {
            return redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['roles' => 'Only administrators can manage roles.']);
        }

        $role = $this->roleService()->findRoleById($id);
        if ($role === null) {
            return redirect()->to(site_url('Member/User/Roles'))->with('errors', ['role' => 'Role not found.']);
        }

        $err = $this->roleService()->deleteRole((string) $role['slug']);
        if ($err !== null) {
            return redirect()->to(site_url('Member/User/Roles'))->with('errors', ['role' => $err]);
        }

        return redirect()->to(site_url('Member/User/Roles'))->with('message', 'Role deleted.');
    }

    /**
     * @return array<string, mixed>|ResponseInterface
     */
    private function requireLogin(): array|ResponseInterface
    {
        $user = $this->currentUser();
        if ($user === null) {
            return redirect()->to(site_url('Member/User/Login'))->with('errors', ['auth' => 'Log in to continue.']);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        $id = session()->get('member_user_id');
        if (! is_numeric($id)) {
            return null;
        }

        $user = AppDatabase::connection()->table('users')->where('id', (int) $id)->get()->getRowArray();
        if (
            ! is_array($user)
            || ! (bool) ($user['is_active'] ?? false)
            || ! $this->roleService()->roleExists((string) ($user['role'] ?? ''))
        ) {
            $this->forgetCurrentUser();

            return null;
        }

        $this->rememberCurrentUser($user);

        return $user;
    }

    /**
     * @param array<string, mixed> $current
     */
    private function canAccessUser(array $current, int $id): bool
    {
        return (int) $current['id'] === $id || $this->isAdministrator($current);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isAdministrator(array $user): bool
    {
        return $this->roleService()->isAdministrator((string) ($user['role'] ?? ''));
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $target
     */
    private function canChangeUserRole(array $actor, array $target): bool
    {
        if ((int) ($actor['id'] ?? 0) === (int) ($target['id'] ?? 0)) {
            return false;
        }

        return $this->roleService()->canManageCurrentUserRole(
            (string) ($actor['role'] ?? ''),
            (string) ($target['role'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $actor
     *
     * @return list<array<string, mixed>>
     */
    private function manageableUsers(array $actor, string $search = ''): array
    {
        $builder = AppDatabase::connection()
            ->table('users')
            ->select('id, username, email, role, profile_image, is_active, created_at, last_login_at')
            ->orderBy('username', 'ASC');

        $search = trim($search);
        if ($search !== '') {
            $builder
                ->groupStart()
                ->like('username', $search)
                ->orLike('email', $search)
                ->orLike('role', $search)
                ->groupEnd();
        }

        $rows = $builder->get()->getResultArray();

        $users = [];
        foreach ($rows as $row) {
            if (! $this->canChangeUserRole($actor, $row)) {
                continue;
            }

            $row['role_name'] = $this->roleService()->roleName((string) ($row['role'] ?? ''));
            $row['profile_initial'] = strtoupper(substr(trim((string) ($row['username'] ?? '')), 0, 1) ?: '?');
            $row['profile_image_url'] = $this->profileImageUrl((string) ($row['profile_image'] ?? ''));
            $users[] = $row;
        }

        return $users;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memberListUsers(string $search = ''): array
    {
        $builder = AppDatabase::connection()
            ->table('users')
            ->select('id, username, email, role, profile_image, is_active, created_at, last_login_at')
            ->orderBy('username', 'ASC');

        $search = trim($search);
        if ($search !== '') {
            $builder
                ->groupStart()
                ->like('username', $search)
                ->orLike('email', $search)
                ->orLike('role', $search)
                ->groupEnd();
        }

        $users = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $row['role_name'] = $this->roleService()->roleName((string) ($row['role'] ?? ''));
            $row['profile_initial'] = strtoupper(substr(trim((string) ($row['username'] ?? '')), 0, 1) ?: '?');
            $row['profile_image_url'] = $this->profileImageUrl((string) ($row['profile_image'] ?? ''));
            $row['profile_url'] = site_url('Member/User/Profile/' . (int) ($row['id'] ?? 0));
            $users[] = $row;
        }

        return $users;
    }

    private function profileImageUrl(string $profileImage): string
    {
        $profileImage = trim($profileImage);
        if ($profileImage === '') {
            return '';
        }

        $scriptFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $publicRoot = $scriptFile !== '' ? dirname($scriptFile) : FCPATH;

        return is_file(rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profileImage))
            ? base_url($profileImage)
            : base_url('Vendor/public/' . $profileImage);
    }

    private function roleService(): RoleService
    {
        return new RoleService();
    }

    /**
     * @param array<string, mixed> $user
     */
    private function rememberCurrentUser(array $user): void
    {
        $role = (string) ($user['role'] ?? '');
        session()->set([
            'member_user_id'           => (int) $user['id'],
            'member_username'          => (string) $user['username'],
            'member_role'              => $role,
            'member_can_manage_roles'  => $this->roleService()->isAdministrator($role),
        ]);
    }

    private function forgetCurrentUser(): void
    {
        session()->remove(['member_user_id', 'member_username', 'member_role', 'member_can_manage_roles']);
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function flashErrors(): array
    {
        $errors = session()->getFlashdata('errors');
        if (! is_array($errors)) {
            $errors = [];
        }

        $validation = session()->getFlashdata('_ci_validation_errors');
        if (is_array($validation)) {
            foreach ($validation as $field => $message) {
                $errors[$field] = is_array($message) ? implode(' ', $message) : (string) $message;
            }
        }

        return $errors;
    }

    private function newGuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
