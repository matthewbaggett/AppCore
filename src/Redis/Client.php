<?php

namespace Gone\AppCore\Redis;

use Faker\Generator;
use Faker\Provider\en_GB\Person;
use Gone\AppCore\App;

class Client {
    /** @var string */
    private $id;
    /** @var \Predis\Client */
    private $predis;
    /** @var boolean */
    private $readOnly;

    /**
     * @return string
     */
    public function getId(): string
    {
        if(!$this->id){
            $this->generateId();
        }
        return $this->id;
    }

    /**
     * @param string $id
     * @return Client
     */
    public function setId(string $id): Client
    {
        $this->id = $id;
        return $this;
    }

    public function generateId() : void
    {
        $this->setId((new Person(App::Container()->get(Generator::class)))->name());
    }

    /**
     * @return \Predis\Client
     */
    public function getPredis(): \Predis\Client
    {
        return $this->predis;
    }

    /**
     * @param \Predis\Client $redis
     * @return Client
     */
    public function setPredis(\Predis\Client $predis): Client
    {
        $this->predis = $predis;
        return $this;
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * @param bool $readOnly
     * @return Client
     */
    public function setReadOnly(bool $readOnly): Client
    {
        $this->readOnly = $readOnly;
        return $this;
    }
}