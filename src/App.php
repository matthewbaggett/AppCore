<?php
namespace Segura\AppCore;

use Faker\Factory as FakerFactory;
use Faker\Provider;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\SlackHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SebastianBergmann\Diff\Differ;
use Segura\AppCore\Router\Router;
use Segura\AppCore\Services\EventLoggerService;
use Segura\AppCore\Twig\Extensions\ArrayUniqueTwigExtension;
use Segura\AppCore\Twig\Extensions\FilterAlphanumericOnlyTwigExtension;
use Segura\Session\Session;
use Slim;
use Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware;

class App
{

    static $instance;

    /** @var \Slim\App  */
    protected $app;
    /** @var \Interop\Container\ContainerInterface */
    protected $container;
    /** @var Logger*/
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

        // Add whoops to slim because its helps debuggin' and is pretty.
        $this->app->add(new WhoopsMiddleware());

        // Fetch DI Container
        $this->container = $this->app->getContainer();

        // Register Twig View helper
        $this->container['view'] = function ($c) {
            $view = new \Slim\Views\Twig(
                '../views/',
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

            return $view;
        };

        $this->container['DatabaseInstance'] = function (Slim\Container $c) {
            return Db::getInstance();
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

        $this->container['Environment'] = function (Slim\Container $c) {
            $environment = array_merge($_ENV, $_SERVER);
            ksort($environment);
            return $environment;
        };

        $this->container['Redis'] = function (Slim\Container $c) {
            // Get environment variables.
            $environment = $this->getContainer()->get('Environment');

            // Set up Redis.
            if (!isset($environment['REDIS_PORT'])) {
                throw new \Exception("No REDIS_PORT defined in environment variables, cannot connect to Redis!");
            }
            $redisConfig  = parse_url($environment['REDIS_PORT']);
            $redisOptions = [];
            if (isset($environment['REDIS_OVERRIDE_HOST'])) {
                $redisConfig['host'] = $environment['REDIS_OVERRIDE_HOST'];
            }
            if (isset($environment['REDIS_OVERRIDE_PORT'])) {
                $redisConfig['port'] = $environment['REDIS_OVERRIDE_PORT'];
            }
            if (isset($environment['REDIS_PREFIX'])) {
                $redisOptions['prefix'] = $environment['REDIS_PREFIX'];
            }
            return new \Predis\Client($redisConfig, $redisOptions);
        };

        $this->container['MonoLog'] = function (Slim\Container $c) {
            $environment = $this->getContainer()->get('Environment');
            // Set up Monolog
            $monolog = new Logger(APP_NAME);
            $monolog->pushHandler(new StreamHandler(APP_ROOT . "/logs/" . APP_NAME . "." . date("Y-m-d") . ".log", Logger::WARNING));
            $monolog->pushHandler(new RedisHandler($this->getContainer()->get('Redis'), "Logs", Logger::DEBUG));
            if (isset($environment['SLACK_TOKEN']) && isset($environment['SLACK_CHANNEL'])) {
                $monolog->pushHandler(
                    new SlackHandler(
                        $environment['SLACK_TOKEN'],
                        $environment['SLACK_CHANNEL'],
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
