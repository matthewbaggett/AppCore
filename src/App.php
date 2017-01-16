<?php
namespace Segura\AppCore;

use Faker\Factory as FakerFactory;
use Faker\Provider;
use GuzzleHttp\Client as HttpClient;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\SlackHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SebastianBergmann\Diff\Differ;
use Segura\AppCore\Exceptions\DbConfigException;
use Segura\AppCore\Middleware\EnvironmentHeadersOnResponse;
use Segura\AppCore\Monolog\LumberjackHandler;
use Segura\AppCore\Router\Router;
use Segura\AppCore\Services\AutoConfigurationService;
use Segura\AppCore\Services\EnvironmentService;
use Segura\AppCore\Services\EventLoggerService;
use Segura\AppCore\Twig\Extensions\ArrayUniqueTwigExtension;
use Segura\AppCore\Twig\Extensions\FilterAlphanumericOnlyTwigExtension;
use Segura\Session\Session;
use Slim;
use Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware;

class App
{
    public static $instance;

    /** @var \Slim\App */
    protected $app;
    /** @var \Interop\Container\ContainerInterface */
    protected $container;
    /** @var Logger */
    protected $monolog;

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

    /**
     * @return \Interop\Container\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    public function getApp()
    {
        return $this->app;
    }

    public function __construct()
    {
        // Check defined config
        if (!defined("APP_NAME")) {
            throw new \Exception("APP_NAME must be defined in /bootstrap.php");
        }

        if (!defined("APP_START")) {
            define("APP_START", microtime(true));
        }

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        date_default_timezone_set("UTC");

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
            $view = new \Slim\Views\Twig(
                APP_ROOT . '/views/',
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

            $view->offsetSet("app_name", APP_NAME);
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
                    $databaseConfigurationHost = parse_url($environment->get('MYSQL_PORT'));
                } else {
                    $databaseConfigurationHost = parse_url($environment->get('MYSQL_HOST'));
                }

                $databaseConfiguration['Default'] = [
                    'driver'   => 'Pdo_Mysql',
                    'hostname' => $databaseConfigurationHost['host'],
                    'port'     => $databaseConfigurationHost['port'],
                    'username' => $environment->isSet('MYSQL_USERNAME') ? $environment->get('MYSQL_USERNAME') : $environment->get('MYSQL_ENV_MYSQL_USER'),
                    'password' => $environment->isSet('MYSQL_PASSWORD') ? $environment->get('MYSQL_PASSWORD') : $environment->get('MYSQL_ENV_MYSQL_PASSWORD'),
                    'database' => $environment->isSet('MYSQL_DATABASE') ? $environment->get('MYSQL_DATABASE') : $environment->get('MYSQL_ENV_MYSQL_DATABASE'),
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

        $this->container['Redis'] = function (Slim\Container $c) {
            // Get environment variables.
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            // Determine where Redis is.
            if ($environment->isSet('REDIS_PORT')) {
                $redisConfig = parse_url($environment->get('REDIS_PORT'));
            } elseif ($environment->isSet('REDIS_HOST')) {
                $redisConfig = parse_url($environment->get('REDIS_HOST'));
            } else {
                throw new \Exception("No REDIS_PORT or REDIS_HOST defined in environment variables, cannot connect to Redis!");
            }

            // Create Redis options array.
            $redisOptions = [];
            if ($environment->isSet('REDIS_OVERRIDE_HOST')) {
                $redisConfig['host'] = $environment->get('REDIS_OVERRIDE_HOST');
            }
            if ($environment->isSet('REDIS_OVERRIDE_PORT')) {
                $redisConfig['port'] = $environment->get('REDIS_OVERRIDE_PORT');
            }
            if ($environment->isSet('REDIS_PREFIX')) {
                $redisOptions['prefix'] = $environment->get('REDIS_PREFIX') . ":";
            }
            return new \Predis\Client($redisConfig, $redisOptions);
        };

        $this->container['MonoLog'] = function (Slim\Container $c) {
            /** @var EnvironmentService $environment */
            $environment = $this->getContainer()->get(EnvironmentService::class);

            // Set up Monolog
            $monolog = new Logger(APP_NAME);
            $monolog->pushHandler(new StreamHandler(APP_ROOT . "/logs/" . APP_NAME . "." . date("Y-m-d") . ".log", Logger::WARNING));
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

        if (file_exists(APP_ROOT . "/src/AppContainer.php")) {
            require(APP_ROOT . "/src/AppContainer.php");
        }
        if (file_exists(APP_ROOT . "/src/AppContainerExtra.php")) {
            require(APP_ROOT . "/src/AppContainerExtra.php");
        }

        $this->monolog = $this->getContainer()->get('MonoLog');
    }

    public static function Log(int $level = Logger::DEBUG, $message)
    {
        return self::Instance()->monolog->log($level, $message);
    }

    public function loadAllRoutes()
    {
        $app = $this->getApp();
        if (file_exists(APP_ROOT . "/src/Routes.php")) {
            require(APP_ROOT . "/src/Routes.php");
        }
        if (file_exists(APP_ROOT . "/src/RoutesExtra.php")) {
            require(APP_ROOT . "/src/RoutesExtra.php");
        }
        Router::Instance()->populateRoutes($app);
        return $this;
    }
}
