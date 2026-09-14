<?php

declare(strict_types=1);

namespace JavaRoot\Resources;

final class HookHandler
{
    public function globalStart(): void
    {
        global $lang;

        $lang->load('javaroot_resources');
        \MyBB\View\set(['javaroot_theme' => Bootstrap::application()->themes()->current()]);
    }

    public function indexEnd(): void
    {
        \MyBB\View\set(['javaroot_latest_resources' => Bootstrap::application()->resources()->latest()]);
    }

    public function adminMenu(array $menu): array
    {
        global $lang;

        $lang->load('javaroot_resources', false, true);
        $menu['190'] = [
            'id' => 'javaroot_resources',
            'title' => $lang->javaroot_resources,
            'link' => 'index.php?module=config-javaroot_resources',
        ];

        return $menu;
    }

    public function adminAction(array $actions): array
    {
        $actions['javaroot_resources'] = [
            'active' => 'javaroot_resources',
            'file' => 'javaroot_resources.php',
        ];

        return $actions;
    }

    public function adminPermissions(array $permissions): array
    {
        global $lang;

        $lang->load('javaroot_resources', false, true);
        $permissions['javaroot_resources'] = $lang->javaroot_resource_moderation;

        return $permissions;
    }
}
