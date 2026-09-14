<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Database;

final class SchemaManager
{
    public function isInstalled(): bool
    {
        global $db;

        if ($db->type === 'sqlite') {
            $query = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '" . TABLE_PREFIX . "javaroot_resources'");
        } else {
            $query = $db->simple_select('javaroot_resources', 'rid', '', ['limit' => 1]);
        }

        return $db->num_rows($query) > 0;
    }

    public function install(): void
    {
        global $db;

        $prefix = TABLE_PREFIX;
        $autoId = $db->type === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $now = $db->type === 'sqlite' ? 'INTEGER NOT NULL DEFAULT 0' : 'INT UNSIGNED NOT NULL DEFAULT 0';
        $text = $db->type === 'sqlite' ? "TEXT NOT NULL DEFAULT ''" : 'TEXT NOT NULL';

        $tables = [
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_categories (cid {$autoId}, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, disporder INT NOT NULL DEFAULT 0, UNIQUE(slug))",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resources (rid {$autoId}, uid INT NOT NULL DEFAULT 0, cid INT NOT NULL DEFAULT 0, title VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, summary VARCHAR(500) NOT NULL DEFAULT '', description {$text}, status VARCHAR(20) NOT NULL DEFAULT 'draft', created_at {$now}, updated_at {$now}, downloads INT NOT NULL DEFAULT 0, rating_sum INT NOT NULL DEFAULT 0, rating_count INT NOT NULL DEFAULT 0, UNIQUE(slug))",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_versions (vid {$autoId}, rid INT NOT NULL, version VARCHAR(80) NOT NULL, minecraft_versions VARCHAR(255) NOT NULL DEFAULT '', changelog {$text}, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at {$now})",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_files (fid {$autoId}, vid INT NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'external', filename VARCHAR(255) NOT NULL DEFAULT '', path VARCHAR(500) NOT NULL DEFAULT '', external_url VARCHAR(500) NOT NULL DEFAULT '', size BIGINT NOT NULL DEFAULT 0, sha256 VARCHAR(64) NOT NULL DEFAULT '', security_status VARCHAR(20) NOT NULL DEFAULT 'pending', status VARCHAR(20) NOT NULL DEFAULT 'pending')",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_reviews (review_id {$autoId}, rid INT NOT NULL, uid INT NOT NULL, rating INT NOT NULL DEFAULT 5, message {$text}, author_reply {$text}, status VARCHAR(20) NOT NULL DEFAULT 'published', created_at {$now}, updated_at {$now})",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_favorites (rid INT NOT NULL, uid INT NOT NULL, created_at {$now}, PRIMARY KEY (rid, uid))",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_reports (report_id {$autoId}, rid INT NOT NULL, uid INT NOT NULL, reason {$text}, status VARCHAR(20) NOT NULL DEFAULT 'open', created_at {$now})",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_downloads (download_id {$autoId}, fid INT NOT NULL, uid INT NOT NULL DEFAULT 0, created_at {$now})",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_resource_uploads (token VARCHAR(64) PRIMARY KEY, uid INT NOT NULL, rid INT NOT NULL, vid INT NOT NULL, filename VARCHAR(255) NOT NULL, extension VARCHAR(10) NOT NULL, expected_size BIGINT NOT NULL, received_size BIGINT NOT NULL DEFAULT 0, temp_path VARCHAR(500) NOT NULL, created_at {$now})",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_user_preferences (uid INT PRIMARY KEY, theme VARCHAR(20) NOT NULL DEFAULT 'ordinary')",
            "CREATE TABLE IF NOT EXISTS {$prefix}javaroot_moderation_log (log_id {$autoId}, uid INT NOT NULL, rid INT NOT NULL DEFAULT 0, action VARCHAR(40) NOT NULL, details {$text}, created_at {$now})",
        ];

        foreach ($tables as $sql) {
            $db->write_query($sql);
        }

        $this->ensureColumn('javaroot_resource_files', 'security_status', "VARCHAR(20) NOT NULL DEFAULT 'pending'");
        $this->ensureColumn('javaroot_resource_uploads', 'rid', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('javaroot_resource_uploads', 'vid', 'INT NOT NULL DEFAULT 0');
        $this->seedCategories();
        $this->installSettings();
    }

    private function seedCategories(): void
    {
        global $db;

        $categories = [
            ['name' => 'Paper', 'slug' => 'paper', 'disporder' => 1],
            ['name' => 'BungeeCord', 'slug' => 'bungeecord', 'disporder' => 2],
            ['name' => 'Velocity', 'slug' => 'velocity', 'disporder' => 3],
            ['name' => 'Другие ядра', 'slug' => 'other-cores', 'disporder' => 4],
            ['name' => 'Управление сервером', 'slug' => 'server-management', 'disporder' => 5],
            ['name' => 'Основной раздел', 'slug' => 'main', 'disporder' => 6],
            ['name' => 'Коммерческий раздел', 'slug' => 'commercial', 'disporder' => 7],
        ];

        foreach ($categories as $category) {
            $slug = $db->escape_string($category['slug']);
            $query = $db->simple_select('javaroot_resource_categories', 'cid', "slug = '{$slug}'", ['limit' => 1]);
            if (!$db->num_rows($query)) {
                $db->insert_query('javaroot_resource_categories', $category);
            }
        }
    }

    private function installSettings(): void
    {
        global $db;

        $group = $db->simple_select('settinggroups', 'gid', "name = 'javaroot_resources'", ['limit' => 1]);
        $gid = $db->num_rows($group)
            ? (int)$db->fetch_field($group, 'gid')
            : (int)$db->insert_query('settinggroups', [
                'name' => 'javaroot_resources',
                'title' => 'JavaRoot Forum',
                'description' => 'Настройки каталога ресурсов JavaRoot Forum',
                'disporder' => 50,
                'isdefault' => 0,
            ]);

        $settings = [
            ['name' => 'javaroot_default_theme', 'title' => 'Тема по умолчанию', 'description' => 'Обычная, тёмная или ночная', 'optionscode' => "select\nordinary=Обычная\ndark=Тёмная\nnight=Ночная", 'value' => 'ordinary', 'disporder' => 1],
            ['name' => 'javaroot_max_jar_size', 'title' => 'Лимит JAR', 'description' => 'Лимит файла JAR в байтах', 'optionscode' => 'numeric', 'value' => '52428800', 'disporder' => 2],
            ['name' => 'javaroot_max_archive_size', 'title' => 'Лимит архивов', 'description' => 'Лимит ZIP и RAR в байтах', 'optionscode' => 'numeric', 'value' => '1610612736', 'disporder' => 3],
            ['name' => 'javaroot_storage_path', 'title' => 'Хранилище ресурсов', 'description' => 'Путь вне публичной директории', 'optionscode' => 'text', 'value' => '../javaroot-storage/resources', 'disporder' => 4],
            ['name' => 'javaroot_chunk_size', 'title' => 'Размер части загрузки', 'description' => 'Размер одной части resumable-загрузки в байтах, максимум 8388608', 'optionscode' => 'numeric', 'value' => '8388608', 'disporder' => 5],
        ];

        foreach ($settings as $setting) {
            $name = $db->escape_string($setting['name']);
            $exists = $db->simple_select('settings', 'sid', "name = '{$name}'", ['limit' => 1]);
            if (!$db->num_rows($exists)) {
                $setting['gid'] = $gid;
                $setting['isdefault'] = 1;
                $db->insert_query('settings', $setting);
            }
        }

        rebuild_settings();
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        global $db;

        $tableName = TABLE_PREFIX . $table;
        if ($db->type === 'sqlite') {
            $query = $db->query('PRAGMA table_info(' . $tableName . ')');
            while ($row = $db->fetch_array($query)) {
                if ($row['name'] === $column) {
                    return;
                }
            }
        } else {
            $query = $db->write_query("SHOW COLUMNS FROM {$tableName} LIKE '" . $db->escape_string($column) . "'");
            if ($db->num_rows($query)) {
                return;
            }
        }

        $db->write_query("ALTER TABLE {$tableName} ADD COLUMN {$column} {$definition}");
    }
}
