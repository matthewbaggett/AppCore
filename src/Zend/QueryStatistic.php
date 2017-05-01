<?php

namespace Segura\AppCore\Zend;

class QueryStatistic
{
    /** @var  string */
    private $sql;
    /** @var  float */
    private $time;

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
