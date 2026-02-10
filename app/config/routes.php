<?php
use Phalcon\Mvc\Router;

$router = new Router(true);          // ВКЛЮЧАЕМ дефолтные маршруты
$router->removeExtraSlashes(true);   // /foo/ -> /foo

// (необязательно) дефолты на случай корня или неполных URL
$router->setDefaults([
    'controller' => 'index',
    'action'     => 'index',
]);

// Явный роут для главной (можно и убрать — дефолты покроют)
$router->add(
    '/',
    [
        'controller' => 'index',
        'action'     => 'index',
    ]
);

$router->add(
    '/order',
    [
        'controller' => 'order',
        'action'     => 'index',
    ]
);

// 404
$router->notFound([
    'controller' => 'error',
    'action'     => 'show404',
]);

return $router;
