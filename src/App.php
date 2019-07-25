<?php
namespace Gone\AppCore;

use Cache\Adapter\Apc\ApcCachePool;
use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Adapter\Predis\PredisCachePool;
use DebugBar\Bridge\MonologCollector;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use Faker\Factory as FakerFactory;
use Faker\Provider;
use Gone\AppCore\Redis\Redis;
use Gone\AppCore\Router\Route;
use Gone\AppCore\Router\Router;
use Gone\AppCore\Services\EnvironmentService;
use Gone\AppCore\Services\EventLoggerService;
use Gone\AppCore\Twig\Extensions\ArrayUniqueTwigExtension;
use Gone\AppCore\Twig\Extensions\FilterAlphanumericOnlyTwigExtension;
use Gone\AppCore\Zend\Profiler;
use Gone\Session\Session;
use Gone\Twig\InflectionExtension;
use Gone\Twig\TransformExtension;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\SlackHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use SebastianBergmann\Diff\Differ;
use Slim;

class App
{
    const DEFAULT_TIMEZONE = 'Europe/London';

    public static $instance;

    /** @var \Slim\App */
    protected $app;
    /** @var \Interop\Container\ContainerInterface */
    protected $container;
    /** @var \Monolog\Logger */
    protected $monolog;

    protected $isSessionsEnabled = true;

    protected $containerAliases = [
        'view'             => Slim\Views\Twig::class,
        'DatabaseInstance' => DbConfig::class,
        'Differ'           => Differ::class,
        'HttpClient'       => \GuzzleHttp\Client::class,
        'Faker'            => \Faker\Generator::class,
        'Environment'      => EnvironmentService::class,
        'Redis'            => Redis::class,
        'Monolog'          => \Monolog\Logger::class,
        'Gone\AppCore\Logger' => \Monolog\Logger::class,
        'Cache'            => CachePoolChain::class,
    ];

    protected $routePaths = [
        APP_ROOT . "/src/Routes.php",
        APP_ROOT . "/src/RoutesExtra.php",
    ];

    protected $viewPaths = [];

    public function __construct()
    {
        $this->setup();
    }

    public function setup()
    {
        // Check defined config
        if (!defined("APP_START")) {
            define("APP_START", microtime(true));
        }
        if (!defined("APPCORE_ROOT")) {
            define("APPCORE_ROOT", realpath(__DIR__ . "/../"));
        }
        if (!defined("DEFAULT_ROUTE_ACCESS_MODE")) {
            define("DEFAULT_ROUTE_ACCESS_MODE", Route::ACCESS_PUBLIC);
        }

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        date_default_timezone_set("UTC");
        setlocale(LC_ALL, 'en_US.UTF-8');

        $this->addViewPath(APP_ROOT . "/views/");
        $this->addViewPath(APPCORE_ROOT . "/views");
        if (file_exists(APP_ROOT . "/src/Views")) {
            $this->addViewPath(APP_ROOT . "/src/Views");
        }

        // Create Slim app
        $this->app = new \Slim\App(
            new Container([
                'settings' => [
                    'debug'                             => true,
                    'displayErrorDetails'               => true,
                    'determineRouteBeforeAppMiddleware' => true,
                ]
            ])
        );

        // Fetch DI Container
        $this->container = $this->app->getContainer();

        $this->populateContainerAliases($this->container);

        $this->setupDependencies();

        $this->monolog = $this->getContainer()->get(\Monolog\Logger::class);

        if (file_exists(APP_ROOT . "/src/AppContainer.php")) {
            require(APP_ROOT . "/src/AppContainer.php");
        }
        if (file_exists(APP_ROOT . "/src/AppContainerExtra.php")) {
            require(APP_ROOT . "/src/AppContainerExtra.php");
        }

        $this->addRoutePathsRecursively(APP_ROOT . "/src/Routes");

        if (php_sapi_name() != 'cli' && $this->isSessionsEnabled) {
            $session = $this->getContainer()->get(Session::class);
        }

        $this->setupMiddlewares();

        return $this;
    }
    
    public function setupDependencies() : void
    {
        // add PSR-15 support shim
        $this->container['callableResolver'] = function ($container) {
            return new \Bnf\Slim3Psr15\CallableResolver($container);
        };

        // Register Twig View helper
        $this->container[Slim\Views\Twig::class] = function ($c) {
            foreach ($this->viewPaths as $i => $viewLocation) {
                if (!file_exists($viewLocation) || !is_dir($viewLocation)) {
                    unset($this->viewPaths[$i]);
                }
            }

            $view = new \Slim\Views\Twig(
                $this->viewPaths,
                [
                    'cache' => false,
                    'debug' => true
                ]
            );

            // Instantiate and add Slim specific extension
            $view->addExtension(
                new Slim\Views\TwigExtension(
                    $c['router'],
                    $c['request']->getUri()
                )
            );

            $view->addExtension(
                new ArrayUniqueTwigExtension()
            );

            $view->addExtension(
                new FilterAlphanumericOnlyTwigExtension()
            );

            // Added Twig_Extension_Debug to enable twig dump() etc.
            $view->addExtension(
                new \Twig_Extension_Debug()
            );

            // Add coding string transform filters (ie: camel_case to StudlyCaps)
            $view->addExtension(
                new TransformExtension()
            );

            // Add pluralisation/depluralisation support with singularize/pluralize filters
            $view->addExtension(
                new InflectionExtension()
            );

            $view->addExtension(new \Twig_Extensions_Extension_Text());

            $view->offsetSet("app_name", $this->getAppName());
            $view->offsetSet("year", date("Y"));

            return $view;
        };

        $this->container[DbConfig::class] = function (Slim\Container $c) {
            $dbConfig = new DbConfig();

            /** @var EnvironmentService $environment */
            $environment           = $c->get(EnvironmentService::class);
            // Lets connect to a database
            if ($environment->isSet('MYSQL_HOST')) {
                $databaseConfigurationHost = $environment->get('MYSQL_HOST');
                if (isset(parse_url($databaseConfigurationHost)['host'])) {
                    $databaseConfigurationHost = parse_url($databaseConfigurationHost);
                } else {
                    $databaseConfigurationHost = [
                        'host' => $databaseConfigurationHost,
                        'port' => 3306,
                    ];
                }

                $dbConfig->set('Default', [
                    'driver'   => 'Pdo_Mysql',
                    'hostname' => gethostbyname($databaseConfigurationHost['host']),
                    'port'     => isset($databaseConfigurationHost['port']) ? $databaseConfigurationHost['port'] : 3306,
                    'username' => $environment->get(['MYSQL_USERNAME', 'MYSQL_USER', 'MYSQL_ENV_MYSQL_USER']),
                    'password' => $environment->get(['MYSQL_PASSWORD', 'MYSQL_ENV_MYSQL_PASSWORD']),
                    'database' => $environment->get(['MYSQL_DATABASE', 'MYSQL_ENV_MYSQL_DATABASE']),
                    'charset'  => "UTF8"
                ]);

                return $dbConfig;
            }
            throw new Exceptions\DbConfigException("No Database configuration present, but DatabaseConfig object requested from DI");
        };

        $this->container[\Faker\Generator::class] = function (Slim\Container $c) {
            $faker = FakerFactory::create();
            $faker->addProvider(new Provider\Base($faker));
            $faker->addProvider(new Provider\DateTime($faker));
            $faker->addProvider(new Provider\Lorem($faker));
            $faker->addProvider(new Provider\Internet($faker));
            $faker->addProvider(new Provider\Payment($faker));
            $faker->addProvider(new Provider\en_US\Person($faker));
            $faker->addProvider(new Provider\en_US\Address($faker));
            $faker->addProvider(new Provider\en_US\PhoneNumber($faker));
            $faker->addProvider(new Provider\en_US\Company($faker));
            return $faker;
        };

        $this->container[\GuzzleHttp\Client::class] = function (Slim\Container $c) {
            $client = new \GuzzleHttp\Client([
                // You can set any number of default request options.
                'timeout'  => 2.0,
            ]);
            return $client;
        };

        $this->container[EnvironmentService::class] = function (Slim\Container $c) {
            $environment = new EnvironmentService();
            return $environment;
        };

        $this->container[CachePoolChain::class] = function (Slim\Container $c) {
            $caches = [];

            // If apc/apcu present, add it to the pool
            if (function_exists('apcu_add')) {
                $caches[] = new ApcuCachePool();
            } elseif (function_exists('apc_add')) {
                $caches[] = new ApcCachePool();
            }

            // If Redis is configured, add it to the pool.
            try {
                $c->get('RedisConfig');
                $caches[] = new PredisCachePool($c->get(Redis::class));
            } catch (RedisConfigException $rce) {
                // No redis.
            }

            $caches[] = new ArrayCachePool();

            return new CachePoolChain($caches);
        };

        $this->container["MonologFormatter"] = function (Slim\Container $c) {
            /** @var EnvironmentService $environment */
            $environment = $c->get(EnvironmentService::class);
            return (
                new LineFormatter(
                    // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%"
                    $environment->get("MONOLOG_FORMAT", "[%datetime%] %channel%.%level_name%: %message% %context% %extra%") . "\n",
                    "Y n j, g:i a"
                )
            );
        };

        $this->container["MonologStreamHandler"] = function (Slim\Container $c){
            return (new StreamHandler('php://stdout', Logger::DEBUG))
                ->setFormatter($c->get("MonologFormatter"));
        };

        $this->container["MonologSyslogHandler"] = function (Slim\Container $c){
            return (new SyslogHandler($this->getAppName(), LOG_USER, Logger::DEBUG))
                ->setFormatter($c->get("MonologFormatter"));
        };

        $this->container["MonologFilesystemLogHandler"] = function (Slim\Container $c){
            if (file_exists(APP_ROOT . "/logs") && is_writable(APP_ROOT . "/logs")) {
                return (new StreamHandler(APP_ROOT . "/logs/" . $this->getAppName() . "." . date("Y-m-d") . ".log", \Monolog\Logger::DEBUG))
                    ->setFormatter($c->get("MonologFormatter"));
            }
            return false;
        };

        $this->container["MonologRedisHandler"] = function (Slim\Container $c){
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            if ($environment->isSet('REDIS_LOGGING_ENABLED') && strtolower($environment->get('REDIS_LOGGING_ENABLED')) == 'yes' && ($environment->isSet('REDIS_PORT') || $environment->isSet('REDIS_HOST'))) {
                return (new RedisHandler($this->getContainer()->get(\Predis\Client::class), "Logs", \Monolog\Logger::DEBUG))
                    ->setFormatter($c->get("MonologFormatter"));
            }
            return false;
        };

        $this->container["MonologSlackHandler"] = function (Slim\Container $c){
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            if ($environment->isSet('SLACK_TOKEN') && $environment->isSet('SLACK_CHANNEL')) {
                return (
                    new SlackHandler(
                        $environment->get('SLACK_TOKEN'),
                        $environment->get('SLACK_CHANNEL'),
                        APP_NAME,
                        true,
                        null,
                        \Monolog\Logger::CRITICAL
                        )
                    )
                    ->setFormatter($c->get("MonologFormatter"));
            }
            return false;
        };

        $this->container[\Monolog\Logger::class] = function (Slim\Container $c) {
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            // Set up Monolog
            $monolog = new \Monolog\Logger($this->getAppName());
            $monolog->setHandlers([]);

            // If we're in PHPUnit, configure with a nullhandler & return early.
            if ($environment->isSet('PHP_SELF')) {
                if (stripos($environment->get('PHP_SELF'), 'phpunit') !== false) {
                    $monolog->pushHandler(new NullHandler());
                    return $monolog;
                }
            }

            $monologHandlers = array_filter($c->keys(), function($item){ return fnmatch("Monolog*Handler", $item); });

            foreach($monologHandlers as $handlerDiItemName){
                $handler = $c->get($handlerDiItemName);
                if($handler !== false){
                    $monolog->pushHandler($handler);
                    #!\Kint::dump($handlerDiItemName, $handler->getFormatter());
                }
            }

            return $monolog;
        };

        $this->container[DebugBar::class] = function (Slim\Container $container) {
            $debugBar = new StandardDebugBar();
            $debugBar->addCollector(new MonologCollector($container->get(\Monolog\Logger::class)));
            return $debugBar;
        };

        $this->container[\Middlewares\Debugbar::class] = function (Slim\Container $container) {
            $debugBar = $container->get(DebugBar::class);
            $middleware = new \Middlewares\Debugbar($debugBar);
            return $middleware;
        };

        $this->container[\TimeAgo::class] = function (Slim\Container $container) {
            return new \TimeAgo();
        };

        $this->container[Session::class] = function (Slim\Container $container) {
            return Session::start($container->get("Redis"));
        };

        $this->container[EventLoggerService::class] = function (Slim\Container $container) {
            return new EventLoggerService(
                $container->get("MonoLog"),
                $container->get("Redis"),
                $container->get(Session::class),
                $container->get("TimeAgo"),
                $container->get("Differ")
            );
        };

        $this->container[Differ::class] = function (Slim\Container $container) {
            return new Differ();
        };

        $this->container[Profiler::class] = function (Slim\Container $container) {
            return new Profiler($container->get(\Monolog\Logger::class));
        };

        /** @var EnvironmentService $environmentService */
        $environmentService = $this->getContainer()->get(EnvironmentService::class);
        if ($environmentService->isSet('TIMEZONE')) {
            date_default_timezone_set($environmentService->get('TIMEZONE'));
        } else {
            date_default_timezone_set(self::DEFAULT_TIMEZONE);
        }
    }

    public function setupMiddlewares() : void
    {
        // Middlewares
        $this->app->add(new Middleware\EnvironmentHeadersOnResponse());
        ##$this->app->add(new \Middlewares\ContentType(["text/html", "application/json"]));
        #$this->app->add(new \Middlewares\Debugbar());
        ##$this->app->add(new \Middlewares\Geolocation());
        $this->app->add(new \Middlewares\TrailingSlash());
        $this->app->add(new Middleware\JSONResponseLinter());
        #$this->app->add(new \Middlewares\Whoops());
        #$this->app->add(new \Middlewares\CssMinifier());
        #$this->app->add(new \Middlewares\JsMinifier());
        #$this->app->add(new \Middlewares\HtmlMinifier());
        $this->app->add(new \Middlewares\GzipEncoder());
    }

    /**
     * @return App
     */
    public static function Instance($doNotUseStaticInstance = false)
    {
        if (!self::$instance || $doNotUseStaticInstance === true) {
            $calledClass    = get_called_class();
            self::$instance = new $calledClass();
        }
        return self::$instance;
    }

    /**
     * @return \Interop\Container\ContainerInterface
     */
    public static function Container()
    {
        return self::Instance()->getContainer();
    }

    public static function Debug($message)
    {
        self::Log(Logger::DEBUG, $message);
    }

    /**
     * @return Container
     */
    public function getContainer() : Container
    {
        return $this->container;
    }

    public function getApp()
    {
        return $this->app;
    }

    public function addRoutePath($path)
    {
        if (file_exists($path)) {
            $this->routePaths[] = $path;
        }

        return $this;
    }

    /**
     * @param $directory
     *
     * @return int Number of Paths added.
     */
    public function addRoutePathsRecursively($directory)
    {
        $count = 0;
        if (file_exists($directory)) {
            foreach (new \DirectoryIterator($directory) as $file) {
                if (!$file->isDot()) {
                    if ($file->isFile() && $file->getExtension() == 'php') {
                        $this->addRoutePath($file->getRealPath());
                        $count++;
                    } elseif ($file->isDir()) {
                        $count += $this->addRoutePathsRecursively($file->getRealPath());
                    }
                }
            }
        }
        return $count;
    }

    public function addViewPath($path)
    {
        if (file_exists($path)) {
            $this->viewPaths[] = $path;
        }
        return $this;
    }

    public function getAppName()
    {
        return defined("APP_NAME") ? APP_NAME : null;
    }

    public function makeClean() : App
    {
        $this->setup();
        $this->loadAllRoutes();
        return $this;
    }

    public function populateContainerAliases(&$container)
    {
        foreach ($this->containerAliases as $alias => $class) {
            if ($alias != $class) {
                $container[$alias] = function (Slim\Container $c) use ($class) {
                    return $c->get($class);
                };
            }
        }
    }

    public static function Log(int $level = Logger::DEBUG, $message)
    {
        return self::Instance()
            ->getContainer()
            ->get(\Monolog\Logger::class)
            ->log($level, ($message instanceof \Exception) ? $message->__toString() : $message);
    }

    public function loadAllRoutes()
    {
        $app = $this->getApp();
        foreach ($this->routePaths as $path) {
            if (file_exists($path)) {
                include($path);
            }
        }
        Router::Instance()->populateRoutes($app);
        return $this;
    }

    public static function waitForMySQLToBeReady($connection = null)
    {
        if (!$connection) {
            /** @var DbConfig $configs */
            $dbConfig = App::Instance()->getContainer()->get(DbConfig::class);
            $configs = $dbConfig->__toArray();

            if (isset($configs['Default'])) {
                $connection = $configs['Default'];
            } else {
                foreach ($configs as $option => $connection) {
                    self::waitForMySQLToBeReady($connection);
                }
                return;
            }
        }

        $ready = false;
        echo "Waiting for MySQL ({$connection['hostname']}:{$connection['port']}) to come up...";
        while ($ready == false) {
            $conn = @fsockopen($connection['hostname'], $connection['port']);
            if (is_resource($conn)) {
                fclose($conn);
                $ready = true;
            } else {
                echo ".";
                usleep(500000);
            }
        }
        echo " [DONE]\n";

        /** @var EnvironmentService $environmentService */
        $environmentService = App::Container()->get(EnvironmentService::class);

        $environmentService->rebuildEnvironmentVariables();
    }
}
