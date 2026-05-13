<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\InstallationState;
use App\Libraries\RoleService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Forces the installer when the app is not installed; limits install routes once installed.
 */
final class InstallGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        // Do not use getSegment(2) on short paths (e.g. "/") — it throws HTTPException when out of range.
        $segments = $request->getUri()->getSegments();
        $seg1     = $segments[0] ?? '';
        $seg2     = $segments[1] ?? '';

        $onInstaller = ($seg1 === 'install');
        $installed   = InstallationState::isInstalled();

        if (! $installed && ! $onInstaller) {
            return redirect()->to(site_url('install'));
        }

        if (! $installed && $onInstaller && is_numeric(session()->get('member_user_id')) && ! $this->canAccessInstaller()) {
            return $this->installerForbidden('Only Administrator, Manager, or Owner accounts can access install or restore.');
        }

        if ($installed && $onInstaller) {
            if ($seg2 === 'uninstall') {
                if (! $this->canAccessInstaller()) {
                    return is_numeric(session()->get('member_user_id'))
                        ? redirect()->to(site_url('Member/User/MyProfile'))->with('errors', ['install' => 'Only Administrator, Manager, or Owner accounts can access uninstall.'])
                        : redirect()->to(site_url('Member/User/Login'))->with('errors', ['install' => 'Log in as Administrator, Manager, or Owner to access uninstall.']);
                }

                $seg3 = $segments[2] ?? '';
                if (! in_array($seg3, ['', 'confirm', 'next'], true)) {
                    return redirect()->to('/');
                }
            } else {
                return redirect()->to('/');
            }
        }

        return null;
    }

    private function canAccessInstaller(): bool
    {
        if ((bool) session()->get('member_can_manage_roles')) {
            return true;
        }

        $role = (string) (session()->get('member_role') ?? '');

        if (in_array($role, ['administrator', 'manager', 'owner'], true)) {
            return true;
        }

        try {
            return $role !== '' && (new RoleService())->isAdministrator($role);
        } catch (\Throwable) {
            return false;
        }
    }

    private function installerForbidden(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(403)
            ->setBody($message);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
