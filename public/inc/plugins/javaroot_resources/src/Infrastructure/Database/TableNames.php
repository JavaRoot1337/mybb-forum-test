<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Database;

final class TableNames
{
    public static function get(string $name): string
    {
        return TABLE_PREFIX . 'javaroot_' . $name;
    }
}
