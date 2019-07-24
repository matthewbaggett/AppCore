<?php

namespace Gone\AppCore\Redis;

class PerfLogItem{

    /** @var String */
    protected $query;
    /** @var double */
    protected $time;
    /** @var Client */
    protected $client;
    /** @var array */
    protected $flags = [];
    /** @var double */
    protected $timer;

    public const FLAG_MOVED='MOVED';
    public const FLAG_READONLY='READONLY';

    private const SECOND_IN_MICROSECONDS = 1000000;

    public function __construct()
    {
        $this->timerStart();
    }

    public function __toString()
    {
        $microSeconds = $this->getTime() * self::SECOND_IN_MICROSECONDS;

        return sprintf(
            "%s[%s in %s%s] %s\n",
            $this->hasFlags() ? "⚑ " . $this->__getShortFlags() . " " : null,
            $this->getClient()->getHumanId(),
            number_format($microSeconds, 0),
            "µs",
            strlen($this->getQuery()) > 100 ? substr($this->getQuery(), 0,100) . " ..." : $this->getQuery()
        );
    }

    private function __getShortFlags(){
        $sFlags = [];
        if($this->hasFlag(self::FLAG_READONLY)){
            $sFlags[] = "R";
        }
        if($this->hasFlag(self::FLAG_MOVED)){
            $sFlags[] = "M";
        }
        return implode(",", $sFlags);
    }

    public function timerStart(): PerfLogItem
    {
        $this->timer = microtime(true);
        return $this;
    }

    public function timerStop(): PerfLogItem
    {
        $this->setTime(microtime(true) - $this->timer);
        $this->timer = null;
        return $this;
    }
    /**
     * @return String
     */
    public function getQuery(): String
    {
        return $this->query;
    }

    /**
     * @param String $query
     * @return PerfLogItem
     */
    public function setQuery(String $query): PerfLogItem
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return float
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @param float $time
     * @return PerfLogItem
     */
    public function setTime(float $time): PerfLogItem
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     * @return PerfLogItem
     */
    public function setClient(Client $client): PerfLogItem
    {
        $this->client = $client;
        return $this;
    }

    public function addFlag(string $flagName, $value): PerfLogItem {
        $this->flags[$flagName] = $value;
        return $this;
    }

    public function setFlag(string $flagName) : PerfLogItem
    {
        return $this->addFlag($flagName, true);
    }

    public function unsetFlag(string $flagName) : PerfLogItem
    {
        unset ($this->flags[$flagName]);
        return $this;
    }

    public function hasFlag(string $flagName) : bool
    {
        return isset($this->flags[$flagName]);
    }

    public function hasFlags() : bool
    {
        return count($this->flags) > 0;
    }

    public function getFlag(string $flagName)
    {
        return $this->hasFlag($flagName) ? $this->flags[$flagName] : null;
    }


}