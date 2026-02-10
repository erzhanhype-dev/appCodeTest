<?php

use Phalcon\Cli\Console;
use Phalcon\Di\FactoryDefault\Cli as CliFactoryDefault;
use Phalcon\Autoload\Loader;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di\Di; // <-- добавить

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH);
require APP_PATH . '/app/config/constants.php';

require APP_PATH . '/vendor/autoload.php';
require APP_PATH . '/app/helpers/dotenv.php';

loadEnv(APP_PATH . '/.env');

$loader = new Loader();

$loader->setNamespaces([
    'App\Tasks'     => APP_PATH . '/app/tasks/',
    'App\Services'  => APP_PATH . '/app/services/',
    'App\Exceptions'=> APP_PATH . '/app/exceptions/',
    'App\Helpers'=> APP_PATH . '/app/helpers/',
]);

$loader->setDirectories([
    APP_PATH . '/app/models/',
]);

$loader->register();

$di = new CliFactoryDefault();

// ВАЖНО: сделать DI дефолтным для всех Injectable
Di::setDefault($di);

// Загружаем config.php
$config = include APP_PATH . '/app/config/config.php';

// Регистрируем в DI
$di->setShared('config', function () use ($config) {
    return $config;
});

// Подключаем БД из config
$di->setShared('db', function () use ($config) {
    return new Mysql([
        'host'     => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname'   => $config->database->dbname,
        'charset'  => $config->database->charset,
    ]);
});

$di->setShared('requestStartDt', function () {
    return \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
});

$di->setShared('eventLogService', function () {
    return new \App\Services\Log\EventLogService();
});

// CLI Namespace
$di->getShared('dispatcher')->setDefaultNamespace('App\Tasks');

$console = new Console($di);

$arguments = [
    'task'   => strtolower($argv[1] ?? 'main'),
    'action' => strtolower($argv[2] ?? 'main'),
    'params' => array_slice($argv, 3),
];

$console->handle($arguments);
