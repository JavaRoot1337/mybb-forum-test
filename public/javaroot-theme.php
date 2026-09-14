<?php

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'javaroot-theme.php');

require_once './global.php';
require_once __DIR__ . '/inc/plugins/javaroot_resources/src/Autoloader.php';

\JavaRoot\Resources\Autoloader::register();

$application = \JavaRoot\Resources\Bootstrap::application();
(new \JavaRoot\Resources\Presentation\Frontend\ThemeController(
    $application->themes(),
    new \JavaRoot\Resources\Presentation\Response\Responder(),
))->handle();
