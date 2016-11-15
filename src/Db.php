<?php

namespace Segura\AppCore;

use Segura\AppCore\Exceptions\DbException;
use Zend\Db\Adapter\Adapter;

class Db
{
    private static $instance;

    private $pool = null;

    public function __construct()
    {
        if ($this->pool == null) {
            $dbConfigs = include APP_ROOT . "/config/mysql.php";
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
    public static function getInstance()
    {
        if (!self::$instance instanceof Db) {
            self::$instance = new Db();
        }
        return self::$instance;
    }

    public static function isMySQLConfigured()
    {
        return file_exists(APP_ROOT . "/config/mysql.php");
    }
}
