<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Database;

final class UploadRepository
{
    public function find(string $token): ?array
    {
        global $db;

        $query = $db->simple_select(
            'javaroot_resource_uploads',
            '*',
            "token = '" . $db->escape_string($token) . "'",
            ['limit' => 1],
        );

        return $db->fetch_array($query) ?: null;
    }

    public function create(array $data): void
    {
        global $db;

        $db->insert_query('javaroot_resource_uploads', $data);
    }

    public function updateReceived(string $token, int $received): void
    {
        global $db;

        $db->update_query(
            'javaroot_resource_uploads',
            ['received_size' => $received],
            "token = '" . $db->escape_string($token) . "'",
        );
    }

    public function addFile(array $data): void
    {
        global $db;

        $db->insert_query('javaroot_resource_files', $data);
    }

    public function delete(string $token): void
    {
        global $db;

        $db->delete_query('javaroot_resource_uploads', "token = '" . $db->escape_string($token) . "'");
    }
}
