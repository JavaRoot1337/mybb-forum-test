<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Storage;

final class ArchiveScanner
{
    public function inspect(string $path, string $extension, int $expectedSize): string|false
    {
        if (!is_file($path) || filesize($path) !== $expectedSize) {
            return false;
        }

        $handle = fopen($path, 'rb');
        $signature = $handle ? fread($handle, 8) : false;
        if ($handle) {
            fclose($handle);
        }
        if ($signature === false) {
            return false;
        }

        if ($extension === 'rar') {
            return str_starts_with($signature, "Rar!\x1A\x07") ? 'pending' : false;
        }
        if (!str_starts_with($signature, 'PK')) {
            return false;
        }
        if (!class_exists('ZipArchive')) {
            return 'pending';
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles > 10000) {
            if ($zip->status === \ZipArchive::ER_OK) {
                $zip->close();
            }
            return false;
        }

        $unpacked = 0;
        $blocked = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'ps1', 'dll', 'so', 'com', 'scr'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            $parts = explode('/', $name);
            $entryExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $attributes = (int)($stat['external_attributes'] ?? 0);
            $mode = ($attributes >> 16) & 0xF000;
            if (
                $name === ''
                || str_starts_with($name, '/')
                || str_contains($name, "\0")
                || in_array('..', $parts, true)
                || in_array($entryExtension, $blocked, true)
                || $mode === 0xA000
            ) {
                $zip->close();
                return false;
            }

            $unpacked += (int)($stat['size'] ?? 0);
            if ($unpacked > 2147483648 || $unpacked > max($expectedSize * 100, 104857600)) {
                $zip->close();
                return false;
            }
        }

        $zip->close();

        return 'ready';
    }
}
