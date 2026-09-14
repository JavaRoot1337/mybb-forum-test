<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Lifecycle;

use JavaRoot\Resources\Infrastructure\Database\SchemaManager;

final class PluginLifecycle
{
    public function isInstalled(): bool
    {
        return (new SchemaManager())->isInstalled();
    }

    public function install(): void
    {
        (new SchemaManager())->install();
    }

    public function activate(): void
    {
        global $db, $cache;

        $query = $db->simple_select('themes', 'tid', "package = 'theme.1'", ['limit' => 1]);
        if ($db->num_rows($query)) {
            $tid = (int)$db->fetch_field($query, 'tid');
        } else {
            $tid = (int)$db->insert_query('themes', [
                'package' => 'theme.1',
                'name' => 'JavaRoot Forum',
                'def' => 0,
                'properties' => my_serialize(['templateset' => 0, 'editortheme' => 'mybb.css', 'disporder' => []]),
                'stylesheets' => my_serialize([]),
                'allowedgroups' => 'all',
            ]);
        }

        $db->update_query('themes', ['def' => 0]);
        $db->update_query('themes', ['def' => 1], 'tid = ' . $tid);
        $cache->update('default_theme', null);
    }

    public function deactivate(): void
    {
        global $db, $cache;

        $query = $db->simple_select('themes', 'tid', "package = 'core.base'", ['limit' => 1]);
        if ($db->num_rows($query)) {
            $tid = (int)$db->fetch_field($query, 'tid');
            $db->update_query('themes', ['def' => 0]);
            $db->update_query('themes', ['def' => 1], 'tid = ' . $tid);
            $cache->update('default_theme', null);
        }
    }

    public function uninstall(): void
    {
        global $db, $cache;

        foreach (['resources', 'resource_categories', 'resource_versions', 'resource_files', 'resource_reviews', 'resource_favorites', 'resource_reports', 'resource_downloads', 'resource_uploads', 'user_preferences', 'moderation_log'] as $table) {
            $db->drop_table('javaroot_' . $table, true);
        }

        $group = $db->simple_select('settinggroups', 'gid', "name = 'javaroot_resources'", ['limit' => 1]);
        if ($db->num_rows($group)) {
            $gid = (int)$db->fetch_field($group, 'gid');
            $db->delete_query('settings', 'gid = ' . $gid);
            $db->delete_query('settinggroups', 'gid = ' . $gid);
        }

        $db->delete_query('themes', "package = 'theme.1'");
        $cache->update('default_theme', null);
        rebuild_settings();
    }
}
