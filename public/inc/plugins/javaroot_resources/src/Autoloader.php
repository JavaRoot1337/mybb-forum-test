<?php

declare(strict_types=1);

namespace JavaRoot\Resources;

final class Autoloader
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        $basePath = __DIR__ . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix = __NAMESPACE__ . '\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = $basePath . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
