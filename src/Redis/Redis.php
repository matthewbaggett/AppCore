<?php

namespace Gone\AppCore\Redis;

use Gone\AppCore\Services\EnvironmentService;
use Monolog\Logger;
use Predis\Client as PredisClient;
use Predis\ClientInterface;
use Predis\Command\CommandInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\ConnectionInterface;
use Predis\Profile\ProfileInterface;
use Predis\Response\ServerException;
use Traversable;

class Redis implements ClientInterface {
    /** @var EnvironmentService */
    protected $environmentService;

    /** @var Logger */
    protected $logger;

    /** @var string[] */
    protected $redisMasterHosts = [];

    /** @var string[] */
    protected $redisSlaveHosts = [];

    /** @var \Predis\Client[] */
    protected $redisWritePools = [];

    /** @var \Predis\Client[] */
    protected $redisReadPools = [];

    public function __construct(
        EnvironmentService $environmentService,
        Logger $logger
    )
    {
        $this->environmentService = $environmentService;
        $this->logger = $logger;
        $this->configure();
    }

    private function configure()
    {
        if($this->environmentService->isSet('REDIS_HOST')) {
            $this->redisMasterHosts = explode(",", $this->environmentService->get('REDIS_HOST'));
        }
        if($this->environmentService->isSet('REDIS_HOST_MASTER')) {
            $this->redisMasterHosts = explode(",", $this->environmentService->get('REDIS_HOST_MASTER'));
        }
        if($this->environmentService->isSet('REDIS_HOST_SLAVE')) {
            $this->redisSlaveHosts = explode(",", $this->environmentService->get('REDIS_HOST_SLAVE'));
        }
        foreach ($this->redisMasterHosts as $masterHost){
            $this->redisWritePools[] = (new \Gone\AppCore\Redis\Client())
                ->setPredis(new \Predis\Client($masterHost))
                ->setReadOnly(false);
        }
        foreach ($this->redisSlaveHosts as $slaveHost){
            $this->redisReadPools[] = (new \Gone\AppCore\Redis\Client())
                ->setPredis(new \Predis\Client($slaveHost))
                ->setReadOnly(true);
        }
    }

    /**
     * @return Client[]
     */
    private function getAllClients() : array
    {
        return array_merge($this->redisWritePools, $this->redisReadPools);
    }

    /**
     * @return \Gone\AppCore\Redis\Client[]
     */
    private function getAllReadClients() : array
    {
        return $this->redisReadPools;
    }

    /**
     * @return \Gone\AppCore\Redis\Client[]
     */
    private function getAllWriteClients() : array
    {
        return $this->redisWritePools;
    }

    /**
     * @return \Gone\AppCore\Redis\Client
     */
    private function getWriteableClient() : \Gone\AppCore\Redis\Client
    {
        $index = array_rand($this->getAllWriteClients());

        $this->logger->info("Selected Redis W-{$index}...");

        return $this->redisWritePools[$index];
    }

    private function getReadableClient() : \Gone\AppCore\Redis\Client
    {
        $index = array_rand($this->getAllReadClients());

        $this->logger->info("Selected Redis R-{$index}...");

        return $this->redisReadPools[$index];
    }

    public function getProfile()
    {
        return $this->getReadableClient()->getProfile();
    }

    public function getOptions()
    {
        return $this->getReadableClient()->getOptions();
    }

    public function connect()
    {
        $success = ['write' => 0, 'read' => 0];
        foreach($this->getAllWriteClients() as $client) {
            $success['write'] += (int) $client->connect();
        }
        foreach($this->getAllReadClients() as $client) {
            $success['read'] += (int) $client->connect();
        }
        return ($success['write'] >= 1 && $success['read'] >= 1);
    }

    public function disconnect()
    {
        $success = ['write' => 0, 'read' => 0];
        foreach($this->getAllWriteClients() as $client) {
            $success['write'] += (int) $client->connect();
        }
        foreach($this->getAllReadClients() as $client) {
            $success['read'] += (int) $client->connect();
        }
        return ($success['write'] == count($this->getAllWriteClients()) && count($this->getAllReadClients()) >= 1);
    }

    public function getConnection()
    {
        return $this->getReadableClient()->getConnection();
    }

    public function createCommand($method, $arguments = array())
    {
        return $this->getReadableClient()->createCommand($method, $arguments);
    }

    public function executeCommand(CommandInterface $command)
    {
        return $this->getReadableClient()->executeCommand($command);
    }

    public function __call($method, $arguments)
    {
        $client = $this->getReadableClient();
        $this->logger->info("[REDIS {$client->getId()}] $method $arguments");
        try {
            return $client->getPredis()->__call($method, $arguments);
        }catch(ServerException $serverException){
            $this->logger->info("Predis - ServerException Message: " . str_replace("\n", "  ", $serverException->getMessage()));
        }
    }

}