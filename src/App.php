<?php
namespace Segura\AppCore;

use Faker\Factory as FakerFactory;
use Faker\Provider;
use GuzzleHttp\Client as HttpClient;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\SlackHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as PredisClient;
use SebastianBergmann\Diff\Differ;
use Segura\AppCore\Exceptions\DbConfigException;
use Segura\AppCore\Middleware\EnvironmentHeadersOnResponse;
use Segura\AppCore\Monolog\LumberjackHandler;
use Segura\AppCore\Router\Router;
use Segura\AppCore\Services\AutoConfigurationService;
use Segura\AppCore\Services\AutoImporterService;
use Segura\AppCore\Services\EnvironmentService;
use Segura\AppCore\Services\EventLoggerService;
use Segura\AppCore\Services\UpdaterService;
use Segura\AppCore\TableGateways\UpdaterTableGateway;
use Segura\AppCore\Twig\Extensions\ArrayUniqueTwigExtension;
use Segura\AppCore\Twig\Extensions\FilterAlphanumericOnlyTwigExtension;
use Segura\Session\Session;
use Slim;
use Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware;

class App
{
    const DEFAULT_TIMEZONE = 'Europe/London';

    public static $instance;

    /** @var \Slim\App */
    protected $app;
    /** @var \Interop\Container\ContainerInterface */
    protected $container;
    /** @var Logger */
    protected $monolog;

    protected $routePaths = [
        APP_ROOT . "/src/Routes.php",
        APP_ROOT . "/src/RoutesExtra.php",
    ];

    protected $viewPaths = [];

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
        /** @var PredisClient $redis */
        $redis = self::Container()->get("Redis");
        if ($message instanceof \Exception) {
            $message = "EXCEPTION (" . get_class($message) . "): {$message->getMessage()}";
        }
        if (is_string($message)) {
            $redis->publish("debug", $message);
            $redis->hset("debug_log", microtime(true), $message);
        }
    }

    /**
     * @return Slim\Container
     */
    public function getContainer()
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

    public function __construct()
    {

        // Check defined config
        if (!defined("APP_START")) {
            define("APP_START", microtime(true));
        }
        if (!defined("APPCORE_ROOT")) {
            define("APPCORE_ROOT", realpath(__DIR__ . "/../"));
        }

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        date_default_timezone_set("UTC");
        setlocale(LC_ALL, 'en_US.UTF-8');

        $this->addViewPath(APP_ROOT . "/views/");
        $this->addViewPath(APPCORE_ROOT . "/views");

        // Create Slim app
        $this->app = new \Slim\App(
            [
                'settings' => [
                    'debug'                             => true,
                    'displayErrorDetails'               => true,
                    'determineRouteBeforeAppMiddleware' => true,
                ]
            ]
        );

        // Middlewares
        $this->app->add(new WhoopsMiddleware());
        $this->app->add(new EnvironmentHeadersOnResponse());

        // Fetch DI Container
        $this->container = $this->app->getContainer();

        // Register Twig View helper
        $this->container['view'] = function ($c) {
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

            $view->addExtension(new \Twig_Extensions_Extension_Text());

            $view->offsetSet("app_name", $this->getAppName());
            $view->offsetSet("year", date("Y"));

            return $view;
        };

        $this->container['DatabaseConfig'] = function (Slim\Container $c) {
            /** @var EnvironmentService $environment */
            $environment           = $c->get(EnvironmentService::class);
            $databaseConfiguration = [];
            // Lets connect to a database
            if ($environment->isSet('MYSQL_PORT') || $environment->isSet('MYSQL_HOST')) {
                if ($environment->isSet('MYSQL_PORT')) {
                    $databaseConfigurationHost = $environment->get('MYSQL_PORT');
                } else {
                    $databaseConfigurationHost = $environment->get('MYSQL_HOST');
                }
                if (isset(parse_url($databaseConfigurationHost)['host'])) {
                    $databaseConfigurationHost = parse_url($databaseConfigurationHost);
                } else {
                    $databaseConfigurationHost = [
                        'host' => $databaseConfigurationHost,
                        'port' => 3306,
                    ];
                }

                $databaseConfiguration['Default'] = [
                    'driver'   => 'Pdo_Mysql',
                    'hostname' => $databaseConfigurationHost['host'],
                    'port'     => isset($databaseConfigurationHost['port']) ? $databaseConfigurationHost['port'] : 3306,
                    'username' => $environment->get(['MYSQL_USERNAME', 'MYSQL_USER', 'MYSQL_ENV_MYSQL_USER']),
                    'password' => $environment->get(['MYSQL_PASSWORD', 'MYSQL_ENV_MYSQL_PASSWORD']),
                    'database' => $environment->get(['MYSQL_DATABASE', 'MYSQL_ENV_MYSQL_DATABASE']),
                    'charset'  => "UTF8"
                ];

                return $databaseConfiguration;
            }
            throw new DbConfigException("No Database configuration present, but DatabaseConfig object requested from DI");
        };

        $this->container['DatabaseInstance'] = function (Slim\Container $c) {
            return Db::getInstance(
                $c->get('DatabaseConfig')
            );
        };

        $this->container['Faker'] = function (Slim\Container $c) {
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

        $this->container['HttpClient'] = function (Slim\Container $c) {
            $client = new HttpClient([
                // You can set any number of default request options.
                'timeout'  => 2.0,
            ]);
            return $client;
        };

        $this->container[AutoConfigurationService::class] = function (Slim\Container $c) {
            return new AutoConfigurationService(
                $c->get('HttpClient')
            );
        };

        $this->container[EnvironmentService::class] = function (Slim\Container $c) {
            $environment = new EnvironmentService(
                $c->get(AutoConfigurationService::class)
            );
            return $environment;
        };

        $this->container['Environment'] = function (Slim\Container $c) {
            $environment = $c->get(EnvironmentService::class)->__toArray();
            trigger_error("Please don't use the \"Environment\" DI object any more. Ta.", E_USER_NOTICE);
            return $environment;
        };


        $this->container['RedisConfig'] = function (Slim\Container $c) {
            // Get environment variables.
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            // Determine where Redis is.
            if ($environment->isSet('REDIS_PORT')) {
                $redisConfig = parse_url($environment->get('REDIS_PORT'));
            } elseif ($environment->isSet('REDIS_HOST')) {
                $redisConfig = parse_url($environment->get('REDIS_HOST'));
            } else {
                $environment->clearCache();
                throw new \Exception("No REDIS_PORT or REDIS_HOST defined in environment variables, cannot connect to Redis!");
            }
            if ($environment->isSet('REDIS_OVERRIDE_HOST')) {
                $redisConfig['host'] = $environment->get('REDIS_OVERRIDE_HOST');
            }
            if ($environment->isSet('REDIS_OVERRIDE_PORT')) {
                $redisConfig['port'] = $environment->get('REDIS_OVERRIDE_PORT');
            }
            return $redisConfig;
        };

        $this->container['Redis'] = function (Slim\Container $c) {
            /** @var EnvironmentService $environment */
            $environment  = $this->getContainer()->get(EnvironmentService::class);
            $redisConfig  = $c->get("RedisConfig");
            $redisOptions = [];
            if ($environment->isSet('REDIS_PREFIX')) {
                $redisOptions['prefix'] = $environment->get('REDIS_PREFIX') . ":";
            }
            return new \Predis\Client($redisConfig, $redisOptions);
        };


        $this->container['MonoLog'] = function (Slim\Container $c) {
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            // Set up Monolog
            $monolog = new Logger($this->getAppName());
            $monolog->pushHandler(new StreamHandler(APP_ROOT . "/logs/" . $this->getAppName() . "." . date("Y-m-d") . ".log", Logger::WARNING));
            $monolog->pushHandler(new RedisHandler($this->getContainer()->get('Redis'), "Logs", Logger::DEBUG));
            if ($environment->isSet('LUMBERJACK_HOST')) {
                $monolog->pushHandler(new LumberjackHandler(rtrim($environment->get('LUMBERJACK_HOST'), "/") . "/v1/log", $environment->get('LUMBERJACK_API_KEY')));
            }
            if ($environment->isSet('SLACK_TOKEN') && $environment->isSet('SLACK_CHANNEL')) {
                $monolog->pushHandler(
                    new SlackHandler(
                        $environment->get('SLACK_TOKEN'),
                        $environment->get('SLACK_CHANNEL'),
                        APP_NAME,
                        true,
                        null,
                        Logger::CRITICAL
                    )
                );
            }
            return $monolog;
        };

        $this->container["TimeAgo"] = function (Slim\Container $container) {
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

        $this->container["Differ"] = function (Slim\Container $container) {
            return new Differ();
        };

        $this->container[AutoImporterService::class] = function (Slim\Container $container) {
            return new AutoImporterService($container->get(UpdaterService::class));
        };

        $this->container[UpdaterService::class] = function (Slim\Container $container) {
            return new UpdaterService(
                $container->get(UpdaterTableGateway::class)
            );
        };

        $this->container[UpdaterTableGateway::class] = function (Slim\Container $c) {
            return new UpdaterTableGateway(
                $c->get('Faker'),
                $c->get('DatabaseInstance')
            );
        };

        /** @var EnvironmentService $environmentService */
        $environmentService = $this->getContainer()->get(EnvironmentService::class);
        if ($environmentService->isSet('TIMEZONE')) {
            date_default_timezone_set($environmentService->get('TIMEZONE'));
        } else {
            date_default_timezone_set(self::DEFAULT_TIMEZONE);
        }

        if (file_exists(APP_ROOT . "/sql")) {
            $this->getContainer()->get(AutoImporterService::class)
                ->addSqlPath(APPCORE_ROOT . "/src/SQL")
                ->addSqlPath(APP_ROOT . "/sql");
        }

        if (file_exists(APP_ROOT . "/src/AppContainer.php")) {
            require(APP_ROOT . "/src/AppContainer.php");
        }
        if (file_exists(APP_ROOT . "/src/AppContainerExtra.php")) {
            require(APP_ROOT . "/src/AppContainerExtra.php");
        }

        $this->monolog = $this->getContainer()->get('MonoLog');

        $this->addRoutePathsRecursively(APP_ROOT . "/src/Routes");

        if (php_sapi_name() != 'cli') {
            $session = $this->getContainer()->get(Session::class);
        }
    }

    public static function Log(int $level = Logger::DEBUG, $message)
    {
        return self::Instance()->monolog->log($level, $message);
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
        if(!$connection) {
            $configs = App::Instance()->getContainer()->get("DatabaseConfig");
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
    }
}
