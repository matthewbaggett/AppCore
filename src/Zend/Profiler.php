<?php

namespace Segura\AppCore\Zend;

use Gone\UUID\UUID;
use Monolog\Logger;
use Segura\AppCore\Interfaces\QueryStatisticInterface;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\Adapter\Profiler\ProfilerInterface;

class Profiler implements ProfilerInterface
{
    private $timer      = null;
    private $sql        = null;
    private $queries    = [];
    private $queryTimes = [];

    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function getQueryStats(QueryStatisticInterface $queryStatisticClass = null)
    {
        return [
            'TotalQueries' => count($this->queryTimes),
            'TotalTime'    => array_sum($this->queryTimes),
            'Diagnostic'   => $this->getQueries($queryStatisticClass),
        ];
    }

    public function profilerStart($target)
    {
        if (is_string($target)) {
            $this->sql = $target;
        } else {
            $this->sql = $target->getSql();
            /** @var ParameterContainer $parameterContainer */
            $parameterContainer = $target->getParameterContainer();
            foreach ($parameterContainer->getNamedArray() as $key => $value) {
                $this->sql = str_replace(":{$key}", "'{$value}'", $this->sql);
            }
        }

        $this->timer = microtime(true);
    }

    public function profilerFinish()
    {
        $uuid                    = UUID::v4();
        $executionTime           = microtime(true) - $this->timer;
        $this->logger->addDebug("Query \"{$this->sql}\" took {$executionTime} sec");
        $this->queryTimes[$uuid] = $executionTime;
        $this->queries[$uuid]    = [$this->sql, debug_backtrace()];
        $this->sql               = null;
        $this->timer             = null;
    }

    /**
     * @return QueryStatisticInterface[]
     */
    public function getQueries(QueryStatisticInterface $queryStatisticClass = null) : array
    {
        $stats = [];
        foreach ($this->queries as $uuid => list($query, $backTrace)) {
            if ($queryStatisticClass) {
                if (is_object($queryStatisticClass)) {
                    $queryStatisticClass = get_class($queryStatisticClass);
                }
                $stat = new $queryStatisticClass();
            } else {
                $stat = new QueryStatistic();
            }
            $stat->setSql($query);
            $stat->setTime($this->queryTimes[$uuid]);
            $stat->setCallPoints($backTrace);
            $stats[] = $stat;
        }
        return $stats;
    }

    public function getQueriesArray()
    {
        $out = [];
        foreach ($this->getQueries() as $query) {
            $out[] = $query->__toArray();
        }
        return $out;
    }
}
