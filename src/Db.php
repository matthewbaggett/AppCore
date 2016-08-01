<?php

namespace Segura\AppCore;

use Zend\Db\Adapter\Adapter;

class Db
{
    static private $instance;

    /**
     * @return Adapter
     */
    public static function getInstance()
    {
        if (!self::$instance instanceof Adapter) {
            $dbConfig = include APP_ROOT . "/config/mysql.php";
            if ($dbConfig) {
                self::$instance = new Adapter($dbConfig);
            } else {
                self::$instance = false;
            }
        }
        return self::$instance;
    }
}
