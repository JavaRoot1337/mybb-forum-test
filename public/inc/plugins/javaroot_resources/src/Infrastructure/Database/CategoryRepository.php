<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Database;

final class CategoryRepository
{
    public function all(): array
    {
        global $db;

        $items = [];
        $query = $db->simple_select('javaroot_resource_categories', '*', '', ['order_by' => 'disporder', 'order_dir' => 'ASC']);
        while ($item = $db->fetch_array($query)) {
            $items[] = $item;
        }

        return $items;
    }

    public function exists(int $cid): bool
    {
        global $db;

        if ($cid < 1) {
            return false;
        }

        $query = $db->simple_select('javaroot_resource_categories', 'cid', 'cid = ' . $cid, ['limit' => 1]);

        return $db->num_rows($query) > 0;
    }

    public function create(string $name, string $slug): bool
    {
        global $db;

        $query = $db->simple_select(
            'javaroot_resource_categories',
            'cid',
            "slug = '" . $db->escape_string($slug) . "'",
            ['limit' => 1],
        );
        if ($db->num_rows($query)) {
            return false;
        }

        $db->insert_query('javaroot_resource_categories', [
            'name' => $name,
            'slug' => $slug,
            'disporder' => 99,
        ]);

        return true;
    }
}
