<?php

namespace Segura\AppCore\Zend;

use Segura\AppCore\Interfaces\QueryStatisticInterface;

class QueryStatistic implements QueryStatisticInterface
{
    /** @var  string */
    private $sql;
    /** @var  float */
    private $time;
    /** @var  array */
    private $callPoints;

    public function __toArray(): array
    {
        return [
            "Time" => $this->getTime(),
            "Query" => $this->getSql(),
        ];
    }

    /**
     * @return array
     */
    public function getCallPoints(): array
    {
        return $this->callPoints;
    }

    /**
     * @param array $callPoints
     *
     * @return QueryStatistic
     */
    public function setCallPoints(array $callPoints): QueryStatistic
    {
        $this->callPoints = $callPoints;
        return $this;
    }

    /**
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @param string $sql
     *
     * @return QueryStatistic
     */
    public function setSql(string $sql): QueryStatistic
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * @return float
     */
    public function getTime(): float
    {
        return $this->time;
    }

    /**
     * @param float $time
     *
     * @return QueryStatistic
     */
    public function setTime(float $time): QueryStatistic
    {
        $this->time = $time;
        return $this;
    }
}
