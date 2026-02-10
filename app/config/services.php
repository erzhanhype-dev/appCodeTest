<?php

use App\Plugins\NotFoundPlugin;
use App\Repositories\CarRepository;
use App\Repositories\OffsetFundRepository;
use App\Repositories\OrderRepository;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordService;
use App\Services\Auth\SessionService;
use App\Services\Auth\VerificationService;
use App\Services\Car\CarCalculationService;
use App\Services\Car\CarSessionService;
use App\Services\Car\CarIntegrationDataService;
use App\Services\Car\CarService;
use App\Services\Car\CarValidator;
use App\Services\Car\VinService;
use App\Services\Cms\CmsService;
use App\Services\Epts\EptsService;
use App\Services\Fund\FundGoodsDocumentService;
use App\Services\Fund\FundService;
use App\Services\Goods\GoodsService;
use App\Services\Halyk\HalykPaymentService;
use App\Services\Kap\Client\KapApiClient;
use App\Services\Kap\KapOldService;
use App\Services\Kap\KapRegInfoService;
use App\Services\Kap\Logging\KapLogger;
use App\Services\Kap\Parser\KapResponseParser;
use App\Services\Kap\Processing\KapRecordSelector;
use App\Services\Kap\Transformer\VehicleDataTransformer;
use App\Services\Kap\Util\DataHelper;
use App\Services\Log\EventLogService;
use App\Services\Log\TaskLogService;
use App\Services\Mail\MailService;
use App\Services\OffsetFund\OffsetFundCarService;
use App\Services\OffsetFund\OffsetFundGoodsService;
use App\Services\OffsetFund\OffsetFundService;
use App\Services\Order\OrderService;
use App\Services\Order\OrderTransactionService;
use App\Services\Pdf\PdfService;
use App\Services\User\UserService;
use Phalcon\Cache\AdapterFactory;
use Phalcon\Cache\Cache;
use Phalcon\Db\Adapter\Pdo\Mysql as DbMysql;
use Phalcon\Di\FactoryDefault;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Encryption\Security;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Html\Escaper;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Mvc\Model\MetaData\Redis as MetaDataRedis;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;
use Phalcon\Mvc\Url;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Session\Adapter\Stream as SessionStream;
use Phalcon\Session\Manager as SessionManager;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Translate\Adapter\NativeArray;
use Phalcon\Translate\InterpolatorFactory;

/** @var array|\ArrayAccess $config */
$di = new FactoryDefault();

/* -------------------------------------------------- */
/* config as a service                                 */
/* -------------------------------------------------- */
$di->setShared('config', fn() => $config);

/* -------------------------------------------------- */
/* url                                                */
/* -------------------------------------------------- */
$di->setShared('url', function () use ($config) {
    $url = new Url();
    $url->setBaseUri($config->application->baseUri ?? '/');
    return $url;
});

/* -------------------------------------------------- */
/* view + volt (compiled -> storage/views)            */
/* -------------------------------------------------- */

$di->setShared('cache', function () {
    $serializerFactory = new SerializerFactory();

    // ВАЖНО: Используем именно Phalcon\Cache\AdapterFactory
    $adapterFactory = new AdapterFactory($serializerFactory);

    $options = [
        'host' => 'redis',
        'port' => 6379,
        'index' => 1,
        'persistent' => false,
        'defaultSerializer' => 'Php',
        'lifetime' => 3600,
    ];

    // Фабрика создаст адаптер нужного типа (Phalcon\Cache\Adapter\Redis)
    $adapter = $adapterFactory->newInstance('redis', $options);

    return new Cache($adapter);
});

$di->setShared('modelsMetadata', function () {
    $serializerFactory = new SerializerFactory();
    $adapterFactory = new AdapterFactory($serializerFactory);

    return new MetaDataRedis($adapterFactory, [
        'host' => '127.0.0.1',
        'port' => 6379,
        'lifetime' => 86400, // Кэшируем структуру на сутки
        'prefix' => 'metadata_',
    ]);
});

$di->setShared('view', function () use ($di, $config) {
    $view = new View();
    $view->setViewsDir($config->application->viewsDir);

    $compiledPath = rtrim($config->application->cacheDir ?? (APP_PATH . '/storage/views'), '/') . '/';
    if (!is_dir($compiledPath)) {
        @mkdir($compiledPath, 0775, true);
    }

    $debug = (bool)($_ENV['APP_DEBUG'] ?? true);

    $view->registerEngines([
        '.volt' => function (View $v) use ($di, $compiledPath, $debug) {
            $volt = new Volt($v, $di);
            $volt->setOptions([
                'path' => $compiledPath,
                'separator' => '_',
                'always' => $debug,
            ]);

            // регистрируем PHP-функции для испteользования в .volt
            $compiler = $volt->getCompiler();
            $compiler->addFunction('file_exists', 'file_exists');
            $compiler->addFunction('filemtime', 'filemtime');
            $compiler->addFunction('date', 'date');

            $volt->getCompiler()->addFunction('__checkHOC', function ($resolvedArgs) {
                return '__checkHOC(' . $resolvedArgs . ')';
            });

            $compiler->addFilter('money_format', function ($resolvedArgs, $exprArgs) {
                return "number_format(" . $resolvedArgs . ", 0, '', ' ')";
            });

            $compiler->addFilter('strtotime', function ($resolvedArgs, $exprArgs) {
                return "strtotime($resolvedArgs)";
            });

            // Фильтр для определения кириллицы
            $compiler->addFilter('detect_cyrillic', function ($resolvedArgs, $exprArgs) {
                return "preg_match('/[А-Яа-я]/u', " . $resolvedArgs . ") ? " . $resolvedArgs . " : ''";
            });

            $compiler->addFilter('replace', function ($resolvedArgs, $exprArgs) {
                return "str_replace(" . $resolvedArgs . ")";
            });

            $compiler->addFunction('format_date', function ($resolvedArgs, $exprArgs) {
                return "date('d.m.Y', " . $resolvedArgs . ")";
            });

            $compiler->addFilter('htmlspecialchars', function ($resolvedArgs) {
                return "htmlspecialchars($resolvedArgs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')";
            });

            $compiler->addFilter('date_format', function ($resolvedArgs, $exprArgs) use ($compiler) {
                if (empty($exprArgs)) {
                    return "date('Y-m-d', " . $resolvedArgs . ")";
                }

                $format = $compiler->expression($exprArgs[0]['expr']);
                return "date(" . $format . ", " . $resolvedArgs . ")";
            });

            $compiler->addFilter('datetime_format', function ($resolvedArgs, $exprArgs) use ($compiler) {
                if (empty($exprArgs)) {
                    return "date('Y-m-d H:i:s', " . $resolvedArgs . ")";
                }

                $format = $compiler->expression($exprArgs[0]['expr']);
                return "date(" . $format . ", " . $resolvedArgs . ")";
            });

            $compiler->addFilter('dash_to_amp', function ($resolvedArgs) {
                return "(strpos($resolvedArgs, '&') !== false ? $resolvedArgs : implode('&', explode('-', $resolvedArgs, 2)))";
            });

            $compiler->addFilter('intval', function ($resolvedArgs, $exprArgs) {
                return "intval(" . $resolvedArgs . ")";
            });

            $compiler->addFilter('number_format', 'number_format');

            return $volt;
        },
        '.phtml' => \Phalcon\Mvc\View\Engine\Php::class,
    ]);

    $view->setVar('t', $di->getShared('translator'));

    return $view;
});

/* -------------------------------------------------- */
/* dispatcher + events                                 */
/* -------------------------------------------------- */
$di->setShared('dispatcher', function () {
    $events = new EventsManager();
    $dispatcher = new Dispatcher();

    $filePaths = [
        APP_PATH . '/app/config/config.php',
        APP_PATH . '/app/config/constants.php',
        APP_PATH . '/app/config/services.php',
        APP_PATH . '/app/config/loader.php',
    ];
    $checkTimeFile = APP_PATH . '/storage/logs/check_times.json';

    $events->attach('dispatch:beforeException', new NotFoundPlugin());
    $events->attach('dispatch:beforeExecuteRoute', new SecurityPlugin());
    $events->attach('dispatch:beforeDispatch', new FileChangeLoggerPlugin($filePaths, $checkTimeFile));

    $dispatcher->setDefaultNamespace('App\\Controllers');
    $dispatcher->setEventsManager($events);

    return $dispatcher;
});

/* -------------------------------------------------- */
/* router                                              */
/* -------------------------------------------------- */
$di->setShared('router', fn() => require APP_PATH . '/app/config/routes.php');

/* -------------------------------------------------- */
/* escaper, flash, session                              */
/* -------------------------------------------------- */
$di->setShared('escaper', fn() => new Escaper());

$di->setShared('session', function () {
    $manager = new SessionManager();
    $savePath = APP_PATH . '/storage/sessions';
    if (!is_dir($savePath)) @mkdir($savePath, 0775, true);
    $manager->setAdapter(new SessionStream(['savePath' => $savePath]));
    $manager->start();
    return $manager;
});

$di->setShared('flash', function () use ($di) {
    $flash = new \Phalcon\Flash\Session($di->getShared('escaper'), $di->getShared('session'));
    $flash->setCssClasses([
        'error' => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice' => 'alert alert-info',
        'warning' => 'alert alert-warning',
    ]);
    return $flash;
});

/* -------------------------------------------------- */
/* security, metadata                                   */
/* -------------------------------------------------- */
$di->setShared('security', function () {
    $s = new Security();
    $s->setWorkFactor(12);
    return $s;
});

$di->setShared('modelsMetadata', fn() => new MetaDataAdapter());

/* -------------------------------------------------- */
/* db                                                  */
/* -------------------------------------------------- */
$di->setShared('db', function () use ($config) {
    return new DbMysql([
        'host' => $config->database->host ?? getenv('DB_HOST'),
        'username' => $config->database->username ?? getenv('DB_USERNAME'),
        'password' => $config->database->password ?? getenv('DB_PASSWORD'),
        'dbname' => $config->database->dbname ?? getenv('DB_NAME'),
        'port' => $config->database->port ?? 3306,
        'charset' => $config->database->charset ?? 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ]);
});

/* -------------------------------------------------- */
/* amqp (optional)                                      */
/* -------------------------------------------------- */
$di->setShared('queue', function () use ($config) {
    try {
//        return new AMQPStreamConnection(
//            $config->rabbit->host,
//            (int)($config->rabbit->port ?? 5672),
//            $config->rabbit->username,
//            $config->rabbit->password,
//            $config->rabbit->vhost ?? '/',
//            (bool)($config->rabbit->insist ?? false),
//            $config->rabbit->login_method ?? 'AMQPLAIN',
//            $config->rabbit->login_response ?? null,
//            'en_US',
//            (float)($config->rabbit->connection_timeout ?? 3.0),
//            (float)($config->rabbit->read_write_timeout ?? 3.0),
//            null,
//            (bool)($config->rabbit->keepalive ?? true),
//            (int)($config->rabbit->heartbeat ?? 30)
//        );
    } catch (\Throwable $e) {
//        error_log('AMQP connection failed: ' . $e->getMessage());
//        return null;
    }
});

/* -------------------------------------------------- */
/* translator                                           */
/* -------------------------------------------------- */
$di->set('translator', function ($parameters = []) use ($di) {
    $lang = $parameters[0] ?? null;

    if (!$lang) {
        $session = $di->getShared('session');
        $lang = $session->get('lang');
    }

    $lang = in_array($lang, ['ru', 'kk']) ? $lang : 'ru';

    $file = APP_PATH . "/resources/lang/{$lang}.php";

    $messages = [];

    if (file_exists($file)) {
        require $file;
    }

    if (!isset($messages) || !is_array($messages)) {
        $messages = [];
    }

    $interp = new InterpolatorFactory();
    return new NativeArray($interp, [
        'content' => $messages
    ]);
});

$di->setShared('logger', function () {
    return new \Phalcon\Logger\Adapter\Stream(APP_PATH . '/storage/logs/app.log');
});

$di->setShared('transactions', function () {
    return new TxManager();
});

$di->setShared('requestStartDt', function () {
    return \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
});

$serviceMap = [
    AuthService::class,
    SessionService::class,
    PasswordService::class,
    VerificationService::class,
    MailService::class,
    CmsService::class,
    GoodsService::class,
    VinService::class,
    FundService::class,
    FundGoodsDocumentService::class,
    UserService::class,
    HalykPaymentService::class,
    EventLogService::class,
    PdfService::class,
    TaskLogService::class,
    OrderService::class,
    OrderTransactionService::class,
    OrderRepository::class,
    CarRepository::class,
    CarSessionService::class,
    CarService::class,
    CarCalculationService::class,
    CarIntegrationDataService::class,
    CmsService::class,
    CarValidator::class,
    KapRegInfoService::class,
    KapApiClient::class,
    KapResponseParser::class,
    KapRecordSelector::class,
    KapLogger::class,
    VehicleDataTransformer::class,
    DataHelper::class,
    KapOldService::class,
    EptsService::class,
    OffsetFundCarService::class,
];


foreach ($serviceMap as $class) {
    $di->setShared($class, function () use ($di, $class) {
        $service = new $class();
        if ($service instanceof InjectionAwareInterface) {
            $service->setDI($di);
            if (method_exists($service, 'onConstruct')) {
                $service->onConstruct(); // теперь вызовется корректно
            }
        }
        return $service;
    });
    $short = '';
    try {
        $short = lcfirst((new \ReflectionClass($class))->getShortName());
    } catch (ReflectionException $e) {
        echo $e->getMessage();
    }
    $di->setShared($short, fn() => $di->getShared($class));
}

$di->setShared(OffsetFundRepository::class, function () {
    return new OffsetFundRepository(
        $this->get('modelsManager')
    );
});

$di->setShared(OffsetFundService::class, function () use ($di) {
    return new OffsetFundService(
        $di->getShared(OffsetFundRepository::class)
    );
});

$di->setShared(OffsetFundCarService::class, function () use ($di) {
    return new OffsetFundCarService(
        $di->getShared(\App\Repositories\OffsetFundRepository::class),
        $di->getShared(\App\Services\Car\CarIntegrationDataService::class),
        $di->getShared(\App\Services\Car\CarCalculationService::class),
        $di->getShared(\App\Services\OffsetFund\OffsetFundService::class),
    );
});

$di->setShared(OffsetFundGoodsService::class, function () use ($di) {
    return new OffsetFundGoodsService(
        $di->getShared(\App\Repositories\OffsetFundRepository::class),
        $di->getShared(\App\Services\OffsetFund\OffsetFundService::class),
    );
});

return $di;
