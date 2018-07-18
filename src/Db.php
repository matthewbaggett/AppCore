<?php

namespace Segura\AppCore;

use Segura\AppCore\Exceptions\DbException;

class Db
{
    private static $instance;

    /** @var Adapter[] */
    private $pool = [];

    public function __construct(DbConfig $config)
    {
        $this->pool = $config->getAdapterPool();
    }

    public static function isMySQLConfigured() : bool
    {
        return self::$instance instanceof Db && count(self::$instance->getPool()) > 0;
    }

    public static function clean()
    {
        self::$instance = null;
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
        throw new DbException("No Database connected called '{$name}'.");
    }

    /**
     * @return Adapter[]
     */
    public function getDatabases()
    {
        return $this->pool;
    }

    /**
     * @param DbConfig $dbConfig
     *
     * @return Db
     */
    public static function getInstance(DbConfig $dbConfig = null)
    {
        if (!self::$instance instanceof Db && $dbConfig instanceof DbConfig) {
            self::$instance = new Db($dbConfig);
        }
        return self::$instance;
    }

    /**
     * @return Adapter[]
     */
    public function getPool() : array
    {
        return $this->pool;
    }
}
