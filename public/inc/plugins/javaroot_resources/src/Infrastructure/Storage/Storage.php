<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Infrastructure\Storage;

final class Storage
{
    public function path(): string
    {
        global $mybb;

        $configured = (string)($mybb->settings['javaroot_storage_path'] ?? '../javaroot-storage/resources');
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $configured) || str_starts_with($configured, DIRECTORY_SEPARATOR)) {
            $resolved = rtrim($configured, '\\/');
        } else {
            $resolved = rtrim(MYBB_ROOT . $configured, '\\/');
        }

        $root = realpath(MYBB_ROOT);
        $parent = realpath(dirname($resolved));
        if ($root && $parent && ($parent === $root || str_starts_with($parent, $root . DIRECTORY_SEPARATOR))) {
            return '';
        }

        return $resolved;
    }

    public function tempDirectory(): string
    {
        $path = $this->path();

        return $path === '' ? '' : $path . DIRECTORY_SEPARATOR . '.tmp';
    }

    public function ensureDirectory(string $path): bool
    {
        return is_dir($path) || (mkdir($path, 0700, true) && is_dir($path));
    }

    public function writeChunk(string $path, int $offset, int $length): int|false
    {
        $handle = fopen($path, $offset === 0 ? 'wb' : 'c+b');
        if (!$handle) {
            return false;
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }
        $input = fopen('php://input', 'rb');
        $copied = $input ? stream_copy_to_stream($input, $handle) : false;
        if ($input) {
            fclose($input);
        }
        fclose($handle);

        return $copied === $length ? $copied : false;
    }

    public function moveToStorage(string $source, int $rid, int $vid, string $extension): string|false
    {
        $path = $this->path();
        if ($path === '') {
            return false;
        }

        $directory = $path . DIRECTORY_SEPARATOR . $rid . DIRECTORY_SEPARATOR . $vid;
        if (!$this->ensureDirectory($directory)) {
            return false;
        }

        $destination = $directory . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.' . $extension;

        return rename($source, $destination) ? $destination : false;
    }

    public function isStoredFile(string $path): bool
    {
        $stored = realpath($path);
        $storage = realpath($this->path());

        return $stored !== false
            && $storage !== false
            && is_file($stored)
            && str_starts_with($stored, rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    public function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
