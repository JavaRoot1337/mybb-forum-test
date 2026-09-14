<?php

declare(strict_types=1);

namespace JavaRoot\Resources;

final class Bootstrap
{
    private static ?Application $application = null;

    public static function application(): Application
    {
        Autoloader::register();

        return self::$application ??= new Application();
    }
}
