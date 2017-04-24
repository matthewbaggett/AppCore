<?php
namespace Segura\AppCore;

use Zend\Db\Sql\PreparableSqlInterface;

class Sql
{
    private static $instance;

    private $database;

    /**
     * Sql constructor.
     *
     * @param $database
     */
    public function __construct($database)
    {
        $this->database = Db::getInstance()->getDatabase($database);
    }

    /**
     * @return Sql
     */
    public static function getInstance(string $database = "Default")
    {
        if (!self::$instance) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }

    public function getSql()
    {
        $sql = new \Zend\Db\Sql\Sql($this->database);
        return $sql;
    }

    /**
     * @param PreparableSqlInterface $query
     *
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    public function execute(PreparableSqlInterface $query)
    {
        $statement = $this->getSql()->prepareStatementForSqlObject($query);
        $results   = $statement->execute();
        return $results;
    }

    public function executeArray(PreparableSqlInterface $query)
    {
        $output  = [];
        $results = $this->execute($query);
        foreach ($results as $result) {
            $output[] = $result;
        }
        return $output;
    }
}
