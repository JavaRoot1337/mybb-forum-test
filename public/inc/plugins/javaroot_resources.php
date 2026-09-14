<?php

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once __DIR__ . '/javaroot_resources/src/Autoloader.php';
\JavaRoot\Resources\Autoloader::register();

global $plugins;

$plugins->add_hook('global_start', 'javaroot_resources_global_start');
$plugins->add_hook('index_end', 'javaroot_resources_index_end');
$plugins->add_hook('admin_config_menu', 'javaroot_resources_admin_menu');
$plugins->add_hook('admin_config_action_handler', 'javaroot_resources_admin_action');
$plugins->add_hook('admin_config_permissions', 'javaroot_resources_admin_permissions');

function javaroot_resources_info(): array
{
    return [
        'name' => 'JavaRoot Forum',
        'description' => 'Русский интерфейс, темы оформления и каталог ресурсов JavaRoot Forum.',
        'website' => 'https://javaroot.example',
        'author' => 'JavaRoot',
        'authorsite' => 'https://javaroot.example',
        'version' => '1.0.0',
        'compatibility' => '*',
    ];
}

function javaroot_resources_is_installed(): bool
{
    return \JavaRoot\Resources\Bootstrap::application()->lifecycle()->isInstalled();
}

function javaroot_resources_install(): void
{
    \JavaRoot\Resources\Bootstrap::application()->lifecycle()->install();
}

function javaroot_resources_activate(): void
{
    \JavaRoot\Resources\Bootstrap::application()->lifecycle()->activate();
}

function javaroot_resources_deactivate(): void
{
    \JavaRoot\Resources\Bootstrap::application()->lifecycle()->deactivate();
}

function javaroot_resources_uninstall(): void
{
    \JavaRoot\Resources\Bootstrap::application()->lifecycle()->uninstall();
}

function javaroot_resources_global_start(): void
{
    (new \JavaRoot\Resources\HookHandler())->globalStart();
}

function javaroot_resources_index_end(): void
{
    (new \JavaRoot\Resources\HookHandler())->indexEnd();
}

function javaroot_resources_admin_menu(array $menu): array
{
    return (new \JavaRoot\Resources\HookHandler())->adminMenu($menu);
}

function javaroot_resources_admin_action(array $actions): array
{
    return (new \JavaRoot\Resources\HookHandler())->adminAction($actions);
}

function javaroot_resources_admin_permissions(array $permissions): array
{
    return (new \JavaRoot\Resources\HookHandler())->adminPermissions($permissions);
}
