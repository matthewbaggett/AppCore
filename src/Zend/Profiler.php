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

    public function getQueries()
    {
        $stats = [];
        foreach ($this->queries as $uuid => list($query, $callStack)) {
            $stat = new QueryStatistic();
            $stat->setSql($query);
            $stat->setTime($this->queryTimes[$uuid] * 1000);
            $callPoints = [];
            foreach($callStack as $call){
                if(isset($call['file']) && stripos($call['file'], "vendor/") !== false){
                    $callPoints[] = '...';
                }else {
                    $callPoints[] = (isset($call['file']) ? $call['file'] : '') . ":" . (isset($call['line']) ? $call['line'] : '') . " " . (isset($call['class']) ? $call['class'] . "::" . $call['function'] : "");
                }
            }
            $lastCallPoints = [];
            while($callPoints != $lastCallPoints) {
                $lastCallPoints = $callPoints;
                foreach ($callPoints as $i => $call) {
                    if (isset($callPoints[$i - 1]) && $callPoints[$i] == '...' && $callPoints[$i - 1] == '...') {
                        unset($callPoints[$i]);
                    }
                }
                $callPoints = array_values($callPoints);
            }
            $stat->setCallPoints($callPoints);
            $stats[] = $stat;
        }
        return $stats;
    }
}
