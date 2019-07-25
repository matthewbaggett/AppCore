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
use Predis\Response\Status;
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
                ->setConnection($masterHost)
                ->setReadOnly(false);
        }
        foreach ($this->redisSlaveHosts as $slaveHost) {
            $this->redisReadPools[] = (new Client())
                ->setConnection($slaveHost)
                ->setReadOnly(true);
        }

        $this->configureCluster();

        #!\Kint::dump(self::$clusterConfiguration, "Configuration is " . (self::$clusterConfigurationLastUpdated - time()) . " seconds old");
    }

    protected function clearClusterConfig() : void
    {
        self::$clusterConfiguration = null;
        self::$clusterConfigurationLastUpdated = null;
    }

    protected function configureCluster(): void
    {
        if (!(!self::$clusterConfiguration || self::$clusterConfigurationLastUpdated <= time() - self::CLUSTER_CONFIGURATION_MAX_AGE_SECONDS)) {
            return;
        }

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
            // Dirty hack to make the number of elements in each node irrelevent.
            $node = $node . " . . . . .";
            $explodedNode = explode(" ", $node);
            list($id, $connection, $flags, $slaveOfId, $pingSent, $pongRecv, $configEpoch, $linkState, $supportedHashRange) = $explodedNode;


            // Split out the flags
            $flags = explode(",", $flags);
            // And decode the type from it
            $type = in_array("master", $flags) ? 'master' : 'slave';

            // Split our supportedHashRsange if we've got one.
            if(isset($supportedHashRange) && stripos($supportedHashRange, "-") !== false) {
                list($hashMin, $hashMax) = explode("-", $supportedHashRange, 2);
            }

            self::$clusterConfiguration[$id] = [
                "id" => $id,
                "connection" => "tcp://" . explode("@", $connection, 2)[0],
                "type" => $type,
                "slaveOf" => $slaveOfId != '-' ? $slaveOfId : null,
                "configEpoch" => $configEpoch,
                "linkState" => $linkState,
                "hashMin" => $hashMin ?? null,
                "hashMax" => $hashMax ?? null,
            ];
        }

        usort(self::$clusterConfiguration, function ($a, $b) {
            return strcmp($a['connection'], $b['connection']);
        });

        self::$clusterConfigurationLastUpdated = time();
    }

    /**
     * @param int $hash
     * @return Client
     * @throws Exception
     */
    public function getServerByHash(int $hash) : Client
    {
        $this->clearClusterConfig();

        // Check for Update to Cluster information, if its time
        $this->configureCluster();

        // Loop over our cluster information, and determine if we're in between the hashmins and maxes.
        foreach(self::$clusterConfiguration as $clusterNodeConfiguration){
            if(isset($clusterNodeConfiguration['hashMin']) && isset($clusterNodeConfiguration['hashMax'])){
                if($hash >= $clusterNodeConfiguration['hashMin'] && $hash <= $clusterNodeConfiguration['hashMax']){
                    //\Kint::dump($clusterNodeConfiguration['connection']);
                    return $this->getClientByAddress($clusterNodeConfiguration['connection']);
                }
            }
        }

        // If we've gotten this far, something is chronically borked.
        throw new Exception("Cannot find a Redis Server that is accepting hash {$hash}...");
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
    }

    public function disconnect()
    {
        return true;
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

    public function keys($match = "*", $count = null)
    {
        $match = str_replace("{", "\\{", $match);
        $match = str_replace("}", "}\\", $match);
        $keys = [];
        foreach($this->getClients(self::CLIENTS_ALL) as $client) {
            //$keys[$client->getHumanId()] = [];
            foreach (new Iterator\Keyspace($client->getPredis(), $match, $count) as $key) {
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
        #\Kint::dump($method, $arguments);

        if(isset($arguments[0]) && is_array($arguments[0])) {
            if(isset($arguments[0][0])) {
                $affectedKeys = array_values($arguments[0]);
            }else{
                $affectedKeys = array_keys($arguments[0]);
                $affectedValues = $arguments[0];
            }
            #\Kint::dump($affectedKeys);
        }

        // If we have affected keys, lets work out what nodes they go to.
        if(isset($affectedKeys)){
            $mappedKeys = [];
            foreach($affectedKeys as $key){
                $hash = $this->clusterStrategy->getSlotByKey($key);
                $server = $this->getServerByHash($hash);
                $mappedKeys[$key] = array_filter([
                    'hash' => $hash,
                    'client' => $server,
                    'value' => $affectedValues[$key] ?? null,
                ]);
            }
            #\Kint::$max_depth = 3;
            #\Kint::dump($mappedKeys);
        }

        // Okay, so we mapped 'em. Lets create n-nodes iterations of this __call, one for each server affected
        if(isset($mappedKeys)){
            $mappedServers = [];
            $mappedServerQueues = [];
            $mappedServerConnections = [];
            foreach ($mappedKeys as $key => $mappedKey) {
                $mappedServers[$mappedKey['client']->getConnection()][$mappedKey['hash']][] = [
                    'key' => $key,
                    'value' => $mappedKey['value'] ?? null,
                    'hash' => $mappedKey['hash']
                ];
                if(isset($mappedKey['value'])) {
                    $mappedServerQueues[$mappedKey['client']->getConnection()][$mappedKey['hash']][$key] = $mappedKey['value'];
                }else{
                    $mappedServerQueues[$mappedKey['client']->getConnection()][$mappedKey['hash']][] = $key;
                }
                if(!isset($mappedServerConnections[$mappedKey['client']->getConnection()])){
                    $mappedServerConnections[$mappedKey['client']->getConnection()]
                        = $mappedKey['client'];
                }
            }

            #\Kint::$max_depth = 4;
            #\Kint::dump(
            #    $mappedServers,
            #    self::$clusterConfiguration,
            #    array_keys($mappedServers),
            #    $mappedServerQueues,
            #    $mappedServerConnections
            #);

            $responses = [];
            foreach($mappedServerConnections as $serverName => $client){
                /** @var $client Client */
                echo sprintf(
                    "Connecting to %s to call %s for %d sub-elements\n",
                    $client->getHumanId(),
                    strtoupper($method),
                    count($mappedServerQueues[$serverName])
                );
                #\Kint::dump($mappedServerQueues[$serverName]);exit;
                foreach($mappedServerQueues[$serverName] as $hash => $items) {
                    #\Kint::dump($serverName, $hash, $items);
                    $responses[] = $client->getPredis()->__call($method, [0 => $items]);
                }
            }

            #\Kint::dump($method, $responses);
            $mergedResponses = [];
            foreach($responses as $response){
                if(is_array($response)) {
                    $mergedResponses = array_merge($mergedResponses, $response);
                }else{
                    $mergedResponses[] = $response;
                }
                if($response instanceof Status && $response->getPayload() != 'OK'){
                    return $response;
                }
            }
            #\Kint::dump($mergedResponses);
            if(reset($mergedResponses) instanceof Status){
                return reset($mergedResponses);
            }
            sort($mergedResponses);
            return $mergedResponses;
        }

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

        // Stop the timer in perfLog.
        $this->perfLog[] = (
            $perfLog
                ->timerStop()
        );

        // Log our redis activity
        $this->logger->addInfo($perfLog->__toString());

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

        return $this->getClientByAddress($address);
    }

    protected function getClientByAddress($address) : ?Client
    {
        foreach ($this->getClients(self::CLIENTS_ALL) as $client) {
            if (in_array($address, $client->getConnectionDetails())) {
                return $client;
            }
        }
        return null;
    }


}