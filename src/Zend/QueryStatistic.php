<?php

namespace Segura\AppCore\Zend;


class QueryStatistic{
    /** @var  string */
    private  $sql;
    /** @var  integer */
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
     * @return QueryStatistic
     */
    public function setSql(string $sql): QueryStatistic
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @param int $time
     * @return QueryStatistic
     */
    public function setTime(int $time): QueryStatistic
    {
        $this->time = $time;
        return $this;
    }


}