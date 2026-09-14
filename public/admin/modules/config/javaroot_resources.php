<?php

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

require_once MYBB_ROOT . 'inc/plugins/javaroot_resources/src/Autoloader.php';
\JavaRoot\Resources\Autoloader::register();

global $lang;

$lang->load('javaroot_resources', false, true);
$page->add_breadcrumb_item($lang->javaroot_resources, 'index.php?module=config-javaroot_resources');

$application = \JavaRoot\Resources\Bootstrap::application();
(new \JavaRoot\Resources\Presentation\Admin\ResourceAdminController(
    $application->resources(),
))->handle();
