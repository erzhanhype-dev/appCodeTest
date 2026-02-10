<?php
declare(strict_types=1);

ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

use App\Services\Log\EventLogService;
use Phalcon\Mvc\Application;

define('APP_PATH', realpath('..'));

setlocale(LC_ALL, 'ru_RU', 'ru_RU.UTF-8', 'ru', 'russian');
define('HTTP_ADDRESS', 'https://' . $_SERVER['HTTP_HOST']);
define('BASE_PATH', realpath('..'));

require BASE_PATH . '/app/config/constants.php';

require_once APP_PATH . '/vendor/autoload.php';
require APP_PATH . '/app/helpers/dotenv.php';
loadEnv(APP_PATH . '/.env');

global $di;

try {
    $initFile = APP_PATH . '/storage/logs/init_done';
    if (!file_exists($initFile)) {
        $file = APP_PATH . '/storage/logs/system/files.log';
        if (!file_exists($file)) {
            mkdir(dirname($file), 0755, true);
            file_put_contents($file, '');
        }
        file_put_contents($initFile, 'Initialization completed');
    }

    /**
     * Read the configuration
     */
    $config = include APP_PATH . "/app/config/config.php";

    /**
     * Read auto-loader
     */
    include APP_PATH . "/app/config/loader.php";

    /**
     * Read services
     */
    include APP_PATH . "/app/config/services.php";

    /**
     * Handle the request - ИСПРАВЛЕННАЯ ЧАСТЬ
     */
    $application = new Application($di);

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $response = $application->handle($uri);
    echo $response->getContent();

} catch (\Exception $e) {

    $startTime = microtime(true);
    $startDt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $startTime));

    $trace = $e->getTrace();
    $controller = 'UnknownController';
    $action = 'UnknownAction';
    $logService = new EventLogService();

    $endTime = microtime(true);
    $endDt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $endTime));

    $start = $startDt ? $startDt->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s');
    $end = $endDt ? $endDt->format('Y-m-d H:i:s.u') : date('Y-m-d H:i:s');

    $userId = (string)($_SESSION['auth']['id'] ?? 'unauthorized');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    $url = (string)($_SERVER['REQUEST_URI'] ?? '');
    $params = (string)($_SERVER['QUERY_STRING'] ?? '');

    $source = $controller . '::' . $action;

    $is404 = false;
    if ($e instanceof \Phalcon\Mvc\Dispatcher\Exception) {
        switch ($e->getCode()) {
            case \Phalcon\Mvc\Dispatcher\Exception::EXCEPTION_HANDLER_NOT_FOUND:
            case \Phalcon\Mvc\Dispatcher\Exception::EXCEPTION_ACTION_NOT_FOUND:
                $is404 = true;
                break;
        }
    }

    $startTime = microtime(true);

    $logService = new EventLogService();

    if ($is404) {
        $logService->store(
            '404_Error', // Можно пометить в логе, что это 404
            $userId,
            $ip,
            $start,
            $end,
            "{$e->getMessage()}",
            $url,
            $params,
            'action',
            'WARNING'
        );
        header('HTTP/1.1 404 Not Found');
        // Можно подключить красивый файл: include '404.html';
        echo "<h1>404 Страница не найдена</h1>";
        echo "Запрошенный адрес " . htmlspecialchars($url) . " не существует.";
    } else {
        $logService->store(
            '500_Error', // Можно пометить в логе, что это 404
            $userId,
            $ip,
            $start,
            $end,
            "{$e->getTraceAsString()}",
            $url,
            $params,
            'action',
            'ERROR'
        );
        echo $e->getMessage();
        header('HTTP/1.1 500 Internal Server Error');
        echo "Ошибка приложения, обратитесь к администратору (" . date('d.m.Y H:i:s') . ")!";
    }
}
