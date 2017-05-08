<?php

namespace Segura\AppCore\Zend;

use Segura\AppCore\Interfaces\QueryStatisticInterface;
use Thru\UUID\UUID;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\Adapter\Profiler\ProfilerInterface;

class Profiler implements ProfilerInterface
{
    private $timer      = null;
    private $sql        = null;
    private $queries    = [];
    private $queryTimes = [];

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
        $this->queryTimes[$uuid] = microtime(true) - $this->timer;
        $this->queries[$uuid]    = [$this->sql, debug_backtrace()];
        $this->sql               = null;
        $this->timer             = null;
    }

    public function getQueries(QueryStatisticInterface $queryStatisticClass = null)
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
            $stat->setTime($this->queryTimes[$uuid] * 1000);
            $stat->setCallPoints($backTrace);
            $stats[] = $stat;
        }
        return $stats;
    }
}
