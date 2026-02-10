<?php

use Phalcon\Config\Config;

include_once APP_PATH.'/app/addons/functions/recycle.php';
include_once APP_PATH.'/app/addons/functions/auto_approve.php';
include_once APP_PATH.'/app/addons/functions/recycle_new.php';

return new Config(data: [
    'database' => [
        'adapter' => 'Mysql',
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'dbname' => getenv('DB_NAME'),
        'charset' => 'utf8',
    ],
    'emails' => [
        'reports' => [
            'employee' => getenv('REPORT_EMPLOYEE_EMAILS'),
            'order'    => getenv('REPORT_ORDER_EMAILS'),
            'halyk' =>  getenv('REPORT_HALYK_EMAILS'),
        ],
    ],
    'mail' => [
        'username' => getenv('MAIL_USERNAME'),
        'password' => getenv('MAIL_PASSWORD'),
        'port'     => getenv('MAIL_PORT'),
        'host' => getenv('MAIL_HOST'),
        'ip' =>  getenv('MAIL_IP'),
    ],
    'integration_service' => [
        'username' => getenv('INTEGRATION_SERVICE_AUTH_USERNAME'),
        'password' => getenv('INTEGRATION_SERVICE_AUTH_PASSWORD'),
        'base_url' => getenv('INTEGRATION_SERVICE_BASE_URL'),
    ],
    'core_service' => [
        'password' => getenv('CORE_SERVICE_PASSWORD'),
        'base_url' => getenv('CORE_SERVICE_BASE_URL'),
    ],
    'application' => [
        'controllersDir' => APP_PATH.'/app/controllers/',
        'modelsDir' => APP_PATH.'/app/models/',
        'migrationsDir' => APP_PATH.'/app/migrations/',
        'viewsDir' => APP_PATH.'/app/views/',
        'pluginsDir' => APP_PATH.'/app/plugins/',
        'libraryDir' => APP_PATH.'/app/library/',
        'cacheDir' => APP_PATH.'/storage/views/',
        'tasksDir' => APP_PATH.'/app/tasks/',
        'servicesDir' => APP_PATH.'/app/services/',
        'baseUri' => '/',
    ],
    'rabbit' => [
        'host' => getenv('RABBITMQ_HOST'),
        'port' => getenv('RABBITMQ_PORT'),
        'username' => getenv('RABBITMQ_USERNAME'),
        'password' => getenv('RABBITMQ_PASSWORD'),
        'name' => getenv('RABBITMQ_QUEUE_NAME'),
        'apiUrl' => getenv('RABBITMQ_API_URL'),
    ],
]);
