<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Database;

final class PreferenceRepository
{
    public function theme(int $uid): ?string
    {
        global $db;

        $query = $db->simple_select('javaroot_user_preferences', 'theme', 'uid = ' . $uid, ['limit' => 1]);

        return $db->num_rows($query) ? (string)$db->fetch_field($query, 'theme') : null;
    }

    public function saveTheme(int $uid, string $theme): void
    {
        global $db;

        $query = $db->simple_select('javaroot_user_preferences', 'uid', 'uid = ' . $uid, ['limit' => 1]);
        if ($db->num_rows($query)) {
            $db->update_query('javaroot_user_preferences', ['theme' => $theme], 'uid = ' . $uid);
            return;
        }

        $db->insert_query('javaroot_user_preferences', ['uid' => $uid, 'theme' => $theme]);
    }
}
