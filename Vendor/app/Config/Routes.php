<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('install', static function (RouteCollection $routes): void {
    $routes->get('/', 'Install::index');
    $routes->get('new', 'Install::fresh');
    $routes->get('restore', 'Install::restore');
    $routes->post('backup/delete', 'Install::deleteBackup');
    $routes->post('database', 'Install::saveDatabase');
    $routes->post('test-connection', 'Install::testConnection');
    $routes->get('schema', 'Install::schema');
    $routes->post('schema', 'Install::runSchema');
    $routes->get('restore/schema', 'Install::schema');
    $routes->post('restore/schema', 'Install::runSchema');
    $routes->get('admin', 'Install::admin');
    $routes->post('admin', 'Install::saveAdmin');
    $routes->get('complete', 'Install::complete');
    $routes->post('finish', 'Install::finish');
    $routes->get('uninstall', 'Install::uninstall');
    $routes->post('uninstall/confirm', 'Install::uninstallConfirm');
    $routes->get('uninstall/next', 'Install::uninstallNext');
});

$routes->group('Member', ['namespace' => 'App\Controllers\Member'], static function (RouteCollection $routes): void {
    $routes->get('List', 'User::memberList');
    $routes->get('List/Search', 'User::searchMemberList');
});

$routes->group('Member/User', ['namespace' => 'App\Controllers\Member'], static function (RouteCollection $routes): void {
    $routes->get('MyProfile', 'User::profile');
    $routes->get('Profile/(:num)', 'User::viewUser/$1');
    $routes->get('Create', 'User::newUser');
    $routes->post('Create', 'User::createUser');
    $routes->post('Delete/(:num)', 'User::deleteUser/$1');
    $routes->get('Register', 'User::register');
    $routes->post('Register', 'User::create');
    $routes->get('Login', 'User::login');
    $routes->post('Login', 'User::authenticate');
    $routes->get('ForgotPassword', 'User::forgotPassword');
    $routes->post('ForgotPassword', 'User::sendForgotPassword');
    $routes->get('Activate/(:segment)', 'User::activate/$1');
    $routes->get('Edit/(:num)', 'User::edit/$1');
    $routes->post('Edit/(:num)', 'User::update/$1');
    $routes->get('Roles', 'User::roles');
    $routes->get('AssignRole', 'User::assignRole');
    $routes->get('AssignRole/Search', 'User::searchUsers');
    $routes->post('AssignRole', 'User::saveAssignedRole');
    $routes->get('Roles/New', 'User::newRole');
    $routes->post('Roles/New', 'User::createRole');
    $routes->get('Roles/View/(:num)', 'User::viewRole/$1');
    $routes->get('Roles/Edit/(:num)', 'User::editRole/$1');
    $routes->post('Roles/Edit/(:num)', 'User::updateRole/$1');
    $routes->post('Roles/Delete/(:num)', 'User::deleteRole/$1');
    $routes->get('DeActivate/(:segment)', 'User::deactivate/$1');
    $routes->post('Logout', 'User::logout');
});

$routes->get('/', 'Home::index');
