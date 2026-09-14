<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Config;

final class Settings
{
    public static function theme(): string
    {
        global $mybb;

        return (string)($mybb->settings['javaroot_default_theme'] ?? 'ordinary');
    }

    public static function chunkSize(): int
    {
        global $mybb;

        return min(8388608, max(262144, (int)($mybb->settings['javaroot_chunk_size'] ?? 8388608)));
    }

    public static function fileLimit(string $extension): int
    {
        global $mybb;

        $name = $extension === 'jar' ? 'javaroot_max_jar_size' : 'javaroot_max_archive_size';
        $default = $extension === 'jar' ? 52428800 : 1610612736;

        return max(1, (int)($mybb->settings[$name] ?? $default));
    }
}
