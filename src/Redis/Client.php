<?php

namespace Gone\AppCore\Redis;

use Faker\Generator;
use Faker\Provider\en_GB\Person;
use Gone\AppCore\App;

class Client {
    /** @var string */
    private $id;
    /** @var string */
    private $connection;
    /** @var PredisClient */
    private $predis;
    /** @var boolean */
    private $readOnly;

    private $connectionDetails = [];

    static private $nameGenerator;

    static private $seedIndex = 0;

    public function __construct()
    {
        if(self::$seedIndex === 0){
            self::$seedIndex = crc32(gethostname());
        }
        $this->generateId();
        self::$seedIndex++;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
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
        if(!self::$nameGenerator){
            /** @var Generator $generator */
            self::$nameGenerator = new Generator();
            self::$nameGenerator->addProvider(new Person(self::$nameGenerator));
            self::$nameGenerator->seed(self::$seedIndex);
        }

        $this->setId(str_replace(" ", "-", (new Person(self::$nameGenerator))->name()));
    }

    public function getHumanId() : string
    {
        return sprintf(
            '%s-%s (%s)',
            $this->isReadOnly() ? 'R' : 'W',
            $this->getId(),
            $this->getConnectionDetails()[0]
        );
    }

    /**
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * @param string $connection
     * @return Client
     */
    public function setConnection(string $connection): Client
    {
        $this->connection = $connection;
        $this->configureConnectionDetailsFromPredis(new PredisClient($this->connection));
        return $this;
    }

    /**
     * @return PredisClient
     */
    public function getPredis(): PredisClient
    {
        return new PredisClient($this->connection);
    }

    /**
     * @param PredisClient $redis
     * @return Client
     */
    public function configureConnectionDetailsFromPredis(PredisClient $predis): Client
    {
        $connectionString = parse_url($predis->getConnection()->__toString());

        $this->connectionDetails = [
            sprintf("tcp://%s:%d", $connectionString['host'], $connectionString['port']),
            sprintf("tcp://%s:%d", gethostbyname($connectionString['host']), $connectionString['port']),
        ];

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

    /**
     * @return array
     */
    public function getConnectionDetails(): array
    {
        return $this->connectionDetails;
    }

    /**
     * @param array $connectionDetails
     * @return Client
     */
    public function setConnectionDetails(array $connectionDetails): Client
    {
        $this->connectionDetails = $connectionDetails;
        return $this;
    }

    public function __call($name, $arguments)
    {
        // We could just do this:
        // return $this->getPredis()->__call($name, $arguments);
        // But then we wouldn't have the chance to override functions with named functions inside PredisClient.
        return call_user_func_array([$this->getPredis(), $name], $arguments);
    }
}