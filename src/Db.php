<?php

namespace Segura\AppCore;

use Segura\AppCore\Exceptions\DbException;

class Db
{
    private static $instance;

    private $pool = null;

    public function __construct(array $dbConfigs)
    {
        if ($this->pool == null) {
            foreach ($dbConfigs as $name => $dbConfig) {
                $this->pool[$name] = new Adapter($dbConfig);
            }
        }
    }

    /**
     * @param $name
     *
     * @throws DbException
     *
     * @return Adapter
     */
    public function getDatabase($name)
    {
        if (isset($this->pool[$name])) {
            return $this->pool[$name];
        }
        throw new DbException("No Database connected called {$name}.");
    }

    /**
     * @return Adapter[]
     */
    public function getDatabases()
    {
        return $this->pool;
    }

    /**
     * @return Db
     */
    public static function getInstance(array $dbConfigs = null)
    {
        if (!self::$instance instanceof Db) {
            self::$instance = new Db($dbConfigs);
        }
        return self::$instance;
    }

    public static function clean()
    {
        self::$instance = null;
    }

    public static function isMySQLConfigured()
    {
        return file_exists(APP_ROOT . "/config/mysql.php");
    }
}
