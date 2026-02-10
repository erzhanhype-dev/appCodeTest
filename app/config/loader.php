<?php

use Phalcon\Autoload\Loader;

global $config; // если $config создаётся в config.php и доступен глобально
// Убедись, что APP_PATH определён, например в index.volt или config.php

$loader = new Loader();

// Пространства имён (исправлены пути и регистр services/*)
$loader->setNamespaces([
    'App\\Controllers'     => APP_PATH . '/app/controllers',
    'App\\Models'          => APP_PATH . '/app/models',
    'App\\Repositories'    => APP_PATH . '/app/repositories',
    'App\\Resources'    => APP_PATH . '/app/resources',
    'App\\Services'        => APP_PATH . '/app/services',
    'App\\Plugins'         => APP_PATH . '/app/plugins',
    'App\\tasks'           => APP_PATH . '/app/tasks',
    'App\\Helpers'         => APP_PATH . '/app/helpers',
    'App\\Exceptions'         => APP_PATH . '/app/exceptions',
]);

// Директории из конфига
$loader->setDirectories([
    $config->application->controllersDir,
    $config->application->pluginsDir,
    $config->application->modelsDir,
    $config->application->tasksDir,
    $config->application->servicesDir,
]);

$loader->register();
