<?php

namespace Gone\AppCore\Redis;

use Gone\AppCore\Services\EnvironmentService;
use Monolog\Logger;
use Predis\Client as PredisClient;
use Predis\ClientInterface;
use Predis\Cluster\ClusterStrategy;
use Predis\Cluster\RedisStrategy;
use Predis\Command\CommandInterface;
use Predis\Command\RawCommand;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\ConnectionInterface;
use Predis\Profile\ProfileInterface;
use Predis\Profile\RedisVersion320;
use Predis\Response\ServerException;
use Traversable;
use Predis\Collection\Iterator;

class Redis implements ClientInterface
{
    protected const CLUSTER_CONFIGURATION_MAX_AGE_SECONDS = 60;
    protected const CLIENTS_ALL = 0;
    protected const CLIENTS_READONLY = 1;
    protected const CLIENTS_WRITEONLY = 2;
    protected static $clusterConfiguration;
    protected static $clusterConfigurationLastUpdated;
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
    /** @var ClusterStrategy */
    protected $clusterStrategy;
    /** @var array */
    protected $perfLog = [];

    public function __construct(
        EnvironmentService $environmentService,
        Logger $logger
    )
    {
        $this->environmentService = $environmentService;
        $this->logger = $logger;
        $this->configure();
        $this->clusterStrategy = new RedisStrategy();
    }

    protected function configure(): void
    {
        if ($this->environmentService->isSet('REDIS_HOST')) {
            $this->redisMasterHosts = explode(",", $this->environmentService->get('REDIS_HOST'));
        }
        if ($this->environmentService->isSet('REDIS_HOST_MASTER')) {
            $this->redisMasterHosts = explode(",", $this->environmentService->get('REDIS_HOST_MASTER'));
        }
        if ($this->environmentService->isSet('REDIS_HOST_SLAVE')) {
            $this->redisSlaveHosts = explode(",", $this->environmentService->get('REDIS_HOST_SLAVE'));
        }
        foreach ($this->redisMasterHosts as $masterHost) {
            $this->redisWritePools[] = (new Client())
                ->setPredis(new \Predis\Client($masterHost))
                ->setReadOnly(false);
        }
        foreach ($this->redisSlaveHosts as $slaveHost) {
            $this->redisReadPools[] = (new Client())
                ->setPredis(new \Predis\Client($slaveHost))
                ->setReadOnly(true);
        }

        if (!self::$clusterConfiguration || self::$clusterConfigurationLastUpdated <= time() - self::CLUSTER_CONFIGURATION_MAX_AGE_SECONDS) {
            $this->configureCluster();
        }

        #!\Kint::dump(self::$clusterConfiguration, "Configuration is " . (self::$clusterConfigurationLastUpdated - time()) . " seconds old");
    }

    protected function configureCluster(): void
    {
        $client = $this->getClient(self::CLIENTS_WRITEONLY);
        $nodes = array_filter(
            explode(
                "\n",
                $client
                    ->getPredis()
                    ->getConnection()
                    ->executeCommand(
                        RawCommand::create('CLUSTER', 'NODES')
                    )
            )
        );
        self::$clusterConfiguration = [];
        foreach ($nodes as $node) {
            list($id, $connection, $flags, $slaveOfId, $pingSent, $pongRecv, $configEpoch, $linkState) = explode(" ", $node);
            $flags = explode(",", $flags);
            $type = in_array("master", $flags) ? 'master' : 'slave';
            self::$clusterConfiguration[$id] = [
                "id" => $id,
                "connection" => explode("@", $connection, 2)[0],
                "type" => $type,
                "slaveOf" => $slaveOfId != '-' ? $slaveOfId : null,
                "configEpoch" => $configEpoch,
                "linkState" => $linkState,
            ];
        }

        usort(self::$clusterConfiguration, function ($a, $b) {
            return strcmp($a['connection'], $b['connection']);
        });

        self::$clusterConfigurationLastUpdated = time();
    }

    protected function getClient($mode = self::CLIENTS_ALL): Client
    {
        $clients = $this->getClients($mode);

        return $clients[array_rand($clients)];
    }

    /**
     * @param $mode
     * @return Client[]
     */
    protected function getClients($mode = self::CLIENTS_ALL): array
    {
        $clients = [];
        if ($mode == self::CLIENTS_WRITEONLY || $mode == self::CLIENTS_ALL) {
            $clients = array_merge($clients, $this->redisWritePools);
        }
        if ($mode == self::CLIENTS_READONLY || $mode == self::CLIENTS_ALL) {
            $clients = array_merge($clients, $this->redisReadPools);
        }

        return $clients;
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
        return true;

        $success = ['write' => 0, 'read' => 0];
        foreach ($this->getAllWriteClients() as $client) {
            $success['write'] += (int)$client->connect();
        }
        foreach ($this->getAllReadClients() as $client) {
            $success['read'] += (int)$client->connect();
        }
        return ($success['write'] >= 1 && $success['read'] >= 1);
    }

    public function disconnect()
    {
        return true;

        $success = ['write' => 0, 'read' => 0];
        foreach ($this->getClients(self::CLIENTS_WRITEONLY) as $client) {
            $success['write'] += (int)$client->connect();
        }
        foreach ($this->getClients(self::CLIENTS_READONLY) as $client) {
            $success['read'] += (int)$client->connect();
        }
        return ($success['write'] == count($this->getClients(self::CLIENTS_WRITEONLY)) && count($this->getClients(self::CLIENTS_READONLY)) >= 1);
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

    public function keys($match = "*")
    {
        $match = str_replace("{", "\\{", $match);
        $match = str_replace("}", "}\\", $match);
        $keys = [];
        foreach($this->getClients(self::CLIENTS_ALL) as $client) {
            //$keys[$client->getHumanId()] = [];
            foreach (new Iterator\Keyspace($client->getPredis(), $match) as $key) {
                //$keys[$client->getHumanId()][] = $key;
                $keys[] = $key;
            }
        }
        sort($keys);
        return array_unique($keys);
    }

    public function flushall()
    {
        foreach($this->getClients(self::CLIENTS_WRITEONLY) as $client) {
            $client->flushall();
        }
    }

    public function __call($method, $arguments)
    {
        $response = false;

        // Log the time call started
        $perfLog = (new PerfLogItem());

        // Get an appropriate client
        $client = $this->getClient($this->isMethodWriting($method) ? self::CLIENTS_WRITEONLY : self::CLIENTS_READONLY);
        $perfLog->setClient($client);

        // Rebuild the Redis command, for now
        $command = $this->getClient()->getPredis()->createCommand($method, $arguments);
        $commandElements = [
            $command->getId(),
        ];
        foreach($command->getArguments() as $argument){
            $commandElements[] = "\"{$argument}\"";
        }
        $requestAsString = implode(" ", $commandElements);

        $perfLog->setQuery($requestAsString);

        // Log our redis activity
        $this->logger->addInfo(sprintf(
            "[REDIS %s] %s",
            $client->getHumanId(),
            $requestAsString
        ));

        // Try the call, then handle MOVED and READONLY replies.
        try {
            $response = $client->getPredis()->__call($method, $arguments);
        } catch (ServerException $serverException) {
            $responseKeyword = (explode(" ", $serverException->getMessage()))[0];

            #$this->logger->addCritical("[ServerException] Request: {$requestAsString}, Response: \"{$responseKeyword}\": " . str_replace("\n", "  ", $serverException->getMessage()) . " ");

            if ($responseKeyword == 'READONLY') {
                $writeClient = $this->getClient(self::CLIENTS_WRITEONLY);
                $perfLog
                    ->setFlag(PerfLogItem::FLAG_READONLY)
                    ->setClient($writeClient);
                if ($writeClient) {
                    $response = $writeClient->getPredis()->__call($method, $arguments);
                }else{
                    throw new Exception("Sent a READONLY command, but did not find a suitable client to move to.");
                }
            }

            if ($responseKeyword == 'MOVED') {
                $movedClient = $this->getClientByMoved($serverException->getMessage());
                $perfLog
                    ->setFlag(PerfLogItem::FLAG_MOVED)
                    ->setClient($movedClient);
                if ($movedClient) {
                    $this->logger->addCritical("[MOVED] Rerunning on {$movedClient->getHumanId()}: {$requestAsString}");
                    $response = $movedClient->getPredis()->__call($method, $arguments);
                }else{
                    throw new Exception("Sent a MOVED command, but did not find a suitable client to move to.");
                }
            }
            
            if ($responseKeyword == 'ERR') {
                throw new Exception(
                    sprintf(
                        "Redis Error: %s. Query: %s",
                        $serverException->getMessage(),
                        $requestAsString
                    )
                );
            }

            if($response === false){
                \Kint::dump(
                    $responseKeyword,
                    $serverException->getMessage(),
                    $method,
                    $arguments
                ); exit;
                throw $serverException;
            }
        }

        $this->perfLog[] = (
            $perfLog
                ->timerStop()

        );

        return $response;
    }

    public function __clearPerfLog() : void
    {
        $this->perfLog = [];
    }
    /**
     * @return PerfLogItem[]
     */
    public function __getPerfLog(): array
    {
        return $this->perfLog;
    }

    public function __getPerfLogAsString() : string
    {
        $string = '';
        foreach($this->__getPerfLog() as $item){
            $string .= $item->__toString();
        }

        return $string;
    }

    protected function isMethodWriting($method): bool
    {
        // Okay, so given that we can -default- to writing, we're just gonna single out the things that are NOT
        // a write method, and return false for them, otherwise return true.

        #$methods = $this->getClient()->getPredis()->getProfile()->getSupportedCommands();
        #ksort($methods);
        #!\Kint::dump( $methods);

        switch ($method) {
            case 'GET':
            case 'GETBIT':
            case 'GETRANGE':
            case 'HGET':
            case 'HGETALL':
            case 'HKEYS':
            case 'HMGET':
            case 'HSCAN':
            case 'MGET':
            case 'LLEN':
            case 'LRANGE':
            case 'SCAN':
            case 'SSCAN':
                return false;
            default:
                return true;
        }
    }

    protected function getClientByMoved($movedStatement): ?Client
    {
        $movedStatement = explode(" ", $movedStatement);
        $address = "tcp://{$movedStatement[2]}";

        foreach ($this->getClients(self::CLIENTS_ALL) as $client) {
            if (in_array($address, $client->getConnectionDetails())) {
                return $client;
            }
        }
        return null;
    }


}