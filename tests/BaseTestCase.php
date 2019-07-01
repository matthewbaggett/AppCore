<?php
namespace Gone\AppCore\Test;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Faker\Provider;
use Gone\AppCore\App;
use Gone\AppCore\Db;
use Gone\AppCore\Services\EnvironmentService;
use PHPUnit\Framework\TestCase;
use Slim\Container;

abstract class BaseTestCase extends TestCase
{

    // Set this to true if you want to see whats going on inside some unit tests..
    const DEBUG_MODE = false;
    /**
     * @see https://github.com/fzaninotto/Faker
     *
     * @var Generator $faker
     */
    private static $faker;

    private $app;

    private $container;

    private static $startTime;

    private $singleTestTime;

    private $waypoint_count;
    private $waypoint_last_time;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        /**
         * @var \Slim\App $app
         */
        if (!defined("APP_CORE_NAME")) {
            throw new \Exception("You must define APP_CORE_NAME in bootstrap.php. This must be the same as the core app container in /src");
        }
        $app                                = self::getAppObject();
        $this->container                    = self::getAppContainer();
        $this->container['TestAppInstance'] = function (\Slim\Container $c) use ($app) {
            return $app;
        };

        $this->app = $app;
    }

    public static function setUpBeforeClass()
    {
        self::$startTime = microtime(true);

        // If MySQL has been configured, enable a transaction that we can rollback later.
        if (self::isTestDatabaseEnabled()) {
            App::Instance()->getContainer()->get(Db::class);

            // If MySQL has been configured, begin transaction.
            if (Db::isMySQLConfigured()) {
                foreach (Db::getInstance()->getDatabases() as $name => $database) {
                    $database->driver->getConnection()->beginTransaction();
                }
            }
        }

        // Continue setup.
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        // If MySQL has been configured, roll back transaction.
        if (self::isTestDatabaseEnabled()) {
            if (Db::isMySQLConfigured()) {
                foreach (Db::getInstance()->getDatabases() as $name => $database) {
                    $database->driver->getConnection()->rollback();
                }
            }
        }

        // Continue Teardown.
        parent::tearDownAfterClass();

        // If we're in debug mode, show execution time.
        if (self::DEBUG_MODE) {
            $time = microtime(true) - self::$startTime;
            echo "\n" . get_called_class() . ": Took " . number_format($time, 3) . " seconds\n";
        }
    }


    public function setUp()
    {
        parent::setUp();
        $this->singleTestTime     = microtime(true);
        $this->waypoint_count     = 0;
        $this->waypoint_last_time = $this->singleTestTime;
    }

    public function tearDown()
    {
        parent::tearDown();
        if (self::DEBUG_MODE) {
            $time = microtime(true) - $this->singleTestTime;
            echo "" . get_called_class() . ":" . $this->getName() . ": Took " . number_format($time, 3) . " seconds\n\n";
        }
    }

    public function waypoint($message = "")
    {
        if (self::DEBUG_MODE) {
            $time_since_last_waypoint = number_format((microtime(true) - $this->waypoint_last_time) * 1000, 2, '.', '');
            $time_since_begin         = number_format((microtime(true) - $this->singleTestTime) * 1000, 2, '.', '');
            $this->waypoint_count++;
            if ($this->waypoint_count == 1) {
                echo "\n";
            }
            echo " > Waypoint {$this->waypoint_count} - {$time_since_last_waypoint}ms / {$time_since_begin}ms {$message}\n";
            $this->waypoint_last_time = microtime(true);
        }
    }

    /**
     * @return App
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * @return \Slim\Container
     */
    public function getDIContainer()
    {
        return $this->container;
    }

    /**
     * @return Generator
     */
    public static function getFaker()
    {
        if (!self::$faker) {
            self::$faker = FakerFactory::create();
            self::$faker->addProvider(new Provider\Base(self::$faker));
            self::$faker->addProvider(new Provider\DateTime(self::$faker));
            self::$faker->addProvider(new Provider\Lorem(self::$faker));
            self::$faker->addProvider(new Provider\Internet(self::$faker));
            self::$faker->addProvider(new Provider\Payment(self::$faker));
            self::$faker->addProvider(new Provider\en_US\Person(self::$faker));
            self::$faker->addProvider(new Provider\en_US\Address(self::$faker));
            self::$faker->addProvider(new Provider\en_US\PhoneNumber(self::$faker));
            self::$faker->addProvider(new Provider\en_US\Company(self::$faker));
        }
        return self::$faker;
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function setProtectedProperty(&$object, $property, $value)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->setValue($object, $value);
    }

    public function getProtectedProperty(&$object, $property)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    private static function isTestDatabaseEnabled() : bool
    {
        /** @var EnvironmentService $environment */
        $environment = self::getAppContainer()->get(EnvironmentService::class);
        return $environment->isSet('MYSQL_HOST') || $environment->isSet('MYSQL_PORT');
    }

    /**
     * @return App
     */
    private static function getAppObject()
    {
        $coreAppName = APP_CORE_NAME;
        return $coreAppName::Instance(false);
    }

    /**
     * @return Container
     */
    private static function getAppContainer()
    {
        return self::getAppObject()->getContainer();
    }
}
