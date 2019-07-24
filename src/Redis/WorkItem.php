<?php

namespace Gone\AppCore\Redis;

use Gone\UUID\UUID;

class WorkItem
    implements \Serializable
{
    protected $payloads = [];

    public function uniqueKey(){
        return UUID::v4();
    }

    public function serialize()
    {
        return json_encode($this->payloads);
    }

    public function unserialize($serialized)
    {
        $this->payloads = json_decode($serialized);
    }

    /**
     * @param mixed $payload
     * @return WorkItem
     */
    public function addPayload($payload) : WorkItem
    {
        $this->payloads[] = $payload;
        return $this;
    }

    /**
     * @param string $index
     * @param mixed $payload
     * @return WorkItem
     */
    public function setPayload(string $index, $payload) : WorkItem
    {
        $this->payloads[$index] = $payload;
        return $this;
    }

    /**
     * @param int $index
     * @return mixed
     */
    public function getPayload($index = 0)
    {
        return $this->payloads[$index];
    }
}