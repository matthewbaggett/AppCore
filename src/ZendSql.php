<?php
namespace Segura\AppCore;

use Zend\Db\Adapter\AdapterInterface;

class ZendSql extends \Zend\Db\Sql\Sql
{
    public function __construct(AdapterInterface $adapter, $table = null, $sqlPlatform = null)
    {
        parent::__construct($adapter, $table, $sqlPlatform);
    }
}
