<?php

namespace Segura\AppCore\Zend;

use Thru\UUID\UUID;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\Adapter\Profiler\ProfilerInterface;

class Profiler implements ProfilerInterface
{
    private $timer      = null;
    private $sql        = null;
    private $queries    = [];
    private $queryTimes = [];

    public function getQueryStats()
    {
        return [
            'TotalQueries' => count($this->queryTimes),
            'TotalTime'    => array_sum($this->queryTimes),
            'Diagnostic'   => $this->getQueries(),
        ];
    }

    public function profilerStart($target)
    {
        $this->sql = $target->getSql();
        /** @var ParameterContainer $parameterContainer */
        $parameterContainer = $target->getParameterContainer();
        foreach ($parameterContainer->getNamedArray() as $key => $value) {
            $this->sql =  str_replace(":{$key}", "'{$value}'", $this->sql);
        }

        $this->timer = microtime(true);
    }

    public function profilerFinish()
    {
        $uuid                    = UUID::v4();
        $this->queryTimes[$uuid] = microtime(true) - $this->timer;
        $this->queries[$uuid]    = $this->sql;
        $this->sql               = null;
        $this->timer             = null;
    }

    public function getQueries()
    {
        $stats = [];
        foreach ($this->queries as $uuid => $query) {
            $stat = new QueryStatistic();
            $stat->setSql($query);
            $stat->setTime($this->queryTimes[$uuid] * 1000);
            $stats[] = $stat;
        }
        return $stats;
    }
}
