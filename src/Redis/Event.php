<?php

namespace Gone\AppCore\Redis;

use Faker\Generator;
use Faker\Provider\en_GB\Person;
use Gone\AppCore\App;

class Event
    implements \Serializable
{
    protected $channel;
    protected $type;
    protected $payload;

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return strtolower($this->channel);
    }

    /**
     * @param mixed $channel
     * @return Event
     */
    public function setChannel($channel)
    {
        $this->channel = strtolower($channel);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return strtoupper($this->type);
    }

    /**
     * @param mixed $type
     * @return Event
     */
    public function setType($type)
    {
        $this->type = strtoupper($type);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param mixed $payload
     * @return Event
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    public function serialize()
    {
        return json_encode([
            'CHANNEL' => $this->getChannel(),
            'TYPE' => $this->getType(),
            'PAYLOAD' => $this->getPayload(),
        ]);
    }

    public function unserialize($serialized)
    {
        foreach(json_decode($serialized, true) as $k => $v){
            if(property_exists($this, $k)) {
                $this->$k = $v;
            }else {
                throw new Exception(
                    sprintf(
                        "Unserialising Event Failed: Non-existent property %s",
                        $k
                    )
                );
            }
        }
    }

    public function __toString()
    {
        return $this->serialize();
    }
}