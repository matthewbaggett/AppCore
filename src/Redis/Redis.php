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
use Predis\Connection\ConnectionException;
use Predis\Connection\ConnectionInterface;
use Predis\Profile\ProfileInterface;
use Predis\Profile\RedisVersion320;
use Predis\Response\ServerException;
use Predis\Response\Status;
use Traversable;
use Predis\Collection\Iterator;

/**
 * @method int    publishEvent(Event $event)
 */
class Redis implements ClientInterface
{
    protected const CLUSTER_CONFIGURATION_MAX_AGE_SECONDS = 60;
    public const CLIENTS_ALL = 0;
    public const CLIENTS_READONLY = 1;
    public const CLIENTS_WRITEONLY = 2;

    protected const SINGLE_ARG_COMMANDS = [
        "get", "set",
        "hget", "hgetall", "hset",
        "exists",
    ];

    protected const SPECIAL_COMMANDS = [
        "hmset", //"hmget",
        "publish",
    ];

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
    /** @var PredisClient[] */
    protected $redisWritePools = [];
    /** @var PredisClient[] */
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
        foreach ($nodes as $nodeIndex => $node) {
            // Dirty hack to make the number of elements in each node irrelevent.
            $explodedNode = explode(" ", $node . " . . . . .");
            if($explodedNode[0] == 'ERR'){
                if($node == 'ERR This instance has cluster support disabled') {
                    self::$clusterConfiguration[$nodeIndex] = [
                        "id" => $nodeIndex,
                        "connection" => $client->getConnection(),
                        "type" => "solo",
                    ];
                    continue;
                }
            }

            // Write the exploded node into nice vars
            list($id, $connection, $flags, $slaveOfId, $pingSent, $pongRecv, $configEpoch, $linkState, $supportedHashRange) = $explodedNode;

            // Split out the flags
            $flags = explode(",", $flags);
            // And decode the type from it
            $type = in_array("master", $flags) ? 'master' : 'slave';

            // Split our supportedHashRsange if we've got one.
            if (isset($supportedHashRange) && stripos($supportedHashRange, "-") !== false) {
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

        #\Kint::dump(self::$clusterConfiguration);exit;
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

    public function getServerByKey(string $key): Client
    {
        return $this->getServerByHash(
            $this->clusterStrategy->getSlotByKey($key)
        );
    }

    /**
     * @param int $hash
     * @return Client
     * @throws Exception
     */
    public function getServerByHash(int $hash): Client
    {
        $this->clearClusterConfig();

        // Check for Update to Cluster information, if its time
        $this->configureCluster();

        // Loop over our cluster information, and determine if we're in between the hashmins and maxes.
        foreach (self::$clusterConfiguration as $clusterNodeConfiguration) {
            if (isset($clusterNodeConfiguration['hashMin']) && isset($clusterNodeConfiguration['hashMax'])) {
                if ($hash >= $clusterNodeConfiguration['hashMin'] && $hash <= $clusterNodeConfiguration['hashMax']) {
                    //\Kint::dump($clusterNodeConfiguration['connection']);
                    return $this->getClientByAddress($clusterNodeConfiguration['connection']);
                }
            }
            if (isset($clusterNodeConfiguration['type']) && $clusterNodeConfiguration['type'] == 'solo'){
                return $this->getClientByAddress($clusterNodeConfiguration['connection']);
            }
        }

        // If we've gotten this far, something is chronically borked.
        throw new Exception("Cannot find a Redis Server that is accepting hash {$hash}...");
    }

    protected function clearClusterConfig(): void
    {
        self::$clusterConfiguration = null;
        self::$clusterConfigurationLastUpdated = null;
    }

    protected function getClientByAddress($address): ?Client
    {
        foreach ($this->getClients(self::CLIENTS_ALL) as $client) {
            if (in_array($address, $client->getConnectionDetails())) {
                return $client;
            }
        }
        return null;
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

    protected const SEARCH_USING_SCAN='scan';
    protected const SEARCH_USING_KEYS='keys';
    protected const SEARCH_METHOD=self::SEARCH_USING_KEYS;

    public function keys($match = "*", $count = null)
    {
        $match = str_replace("{", "\\{", $match);
        $match = str_replace("}", "}\\", $match);
        $keys = [];

        switch (self::SEARCH_METHOD) {
            case self::SEARCH_USING_KEYS:
                foreach ($this->getClients(self::CLIENTS_WRITEONLY) as $client) {
                    foreach($client->getPredis()->keys($match) as $key){
                        $keys[] = $key;
                    }
                }
                break;

            case self::SEARCH_USING_SCAN:
                foreach ($this->getClients(self::CLIENTS_WRITEONLY) as $client) {
                    //$keys[$client->getHumanId()] = [];
                    foreach (new Iterator\Keyspace($client->getPredis(), $match, $count) as $key) {
                        //$keys[$client->getHumanId()][] = $key;
                        $keys[] = $key;
                    }
                }
                break;

            default:
                throw new Exception(sprintf(
                    'Unknown SEARCH_METHOD: "%s"',
                    self::SEARCH_METHOD
                ));
        }
        sort($keys);
        return array_unique($keys);
    }

    public function flushall()
    {
        foreach ($this->getClients(self::CLIENTS_WRITEONLY) as $client) {
            $client->flushall();
        }
    }

    public function __call($method, $arguments)
    {
        #\Kint::dump($method, $arguments);

        if (isset($arguments[0])) {
            if (isset($arguments[0][0])) {
                if (is_array($arguments[0])) {
                    $affectedKeys = array_values($arguments[0]);
                } else {
                    $affectedKeys = [$arguments[0]];
                }
            } else {
                $affectedKeys = array_keys($arguments[0]);
                $affectedValues = $arguments[0];
            }
            #\Kint::dump($affectedKeys);
        }

        // If we have affected keys, lets work out what nodes they go to.
        if (isset($affectedKeys)) {
            $mappedKeys = [];
            foreach ($affectedKeys as $key) {
                $hash = $this->clusterStrategy->getSlotByKey($key);
                $server = $this->getServerByHash($hash);
                $mappedKeys[$key] = [
                    'hash' => $hash,
                    'client' => $server,
                ];
                if(isset($affectedValues[$key])){
                    $mappedKeys[$key]['value'] = $affectedValues[$key];
                }
            }
            #\Kint::$max_depth = 3;
            #\Kint::dump($mappedKeys);
        }

        if (!isset($mappedKeys)) {
            throw new Exception("Mapping keys failed!");
        }

        // Okay, so we mapped 'em. Lets create n-nodes iterations of this __call, one for each server affected
        $mappedServers = [];
        $mappedServerQueues = [];
        $mappedServerConnections = [];
        foreach ($mappedKeys as $key => $mappedKey) {
            if(!isset($mappedKey['hash'])) {
                \Kint::dump($mappedKey);
            }
            $mappedServers[$mappedKey['client']->getConnection()][$mappedKey['hash']][] = [
                'key' => $key,
                'value' => $mappedKey['value'] ?? null,
                'hash' => $mappedKey['hash']
            ];
            if (isset($mappedKey['value'])) {
                $mappedServerQueues[$mappedKey['client']->getConnection()][$mappedKey['hash']][$key] = $mappedKey['value'];
            } else {
                $mappedServerQueues[$mappedKey['client']->getConnection()][$mappedKey['hash']][] = $key;
            }
            if (!isset($mappedServerConnections[$mappedKey['client']->getConnection()])) {
                $mappedServerConnections[$mappedKey['client']->getConnection()]
                    = $mappedKey['client'];
            }
        }

        $responses = [];
        foreach ($mappedServerConnections as $serverName => $client) {
            /** @var $client Client */

            #echo sprintf(
            #    "Connecting to %s to call %s for %d sub-elements\n",
            #    $client->getHumanId(),
            #    strtoupper($method),
            #    count($mappedServerQueues[$serverName])
            #);
            #\Kint::dump($mappedServerQueues[$serverName]);

            foreach ($mappedServerQueues[$serverName] as $hash => $items) {
                // if its a single argument command, just send the arguments as-is,
                // else send our processed items
                $redirectedArguments = in_array($method, array_merge(self::SINGLE_ARG_COMMANDS, self::SPECIAL_COMMANDS))
                    ? $arguments
                    : [0 => $items];

                #\Kint::dump($method, $hash, $items, $arguments, $redirectedArguments);

                #\Kint::dump($method, $arguments, $redirectedArguments);

                try {
                    $response = $client->getPredis()
                        ->__call(
                            $method,
                            $redirectedArguments
                        );
                }catch(ConnectionException $connectionException){
                    \Kint::dump($method, $hash, $items, $arguments, $redirectedArguments);
                    throw $connectionException;
                }

                // If its a single arg command, return fast with the response.
                if (in_array($method, self::SINGLE_ARG_COMMANDS)) {
                    return $response;
                }

                // If its not, add it to our list of responses.
                $responses[] = $response;
            }
        }

        #\Kint::dump($method, $responses);
        $mergedResponses = [];
        foreach ($responses as $response) {
            if (is_array($response)) {
                $mergedResponses = array_merge($mergedResponses, $response);
            } else {
                $mergedResponses[] = $response;
            }
            if ($response instanceof Status && $response->getPayload() != 'OK') {
                return $response;
            }
        }
        #\Kint::dump($mergedResponses);
        if (reset($mergedResponses) instanceof Status) {
            return reset($mergedResponses);
        }
        sort($mergedResponses);
        return $mergedResponses;

    }

    public function __clearPerfLog(): void
    {
        $this->perfLog = [];
    }

    public function __getPerfLogAsString(): string
    {
        $string = '';
        foreach ($this->__getPerfLog() as $item) {
            $string .= $item->__toString();
        }

        return $string;
    }

    /**
     * @return PerfLogItem[]
     */
    public function __getPerfLog(): array
    {
        return $this->perfLog;
    }

    protected function getClientByMoved($movedStatement): ?Client
    {
        $movedStatement = explode(" ", $movedStatement);
        $address = "tcp://{$movedStatement[2]}";

        return $this->getClientByAddress($address);
    }

    public function publishEvent(Event $event)
    {
        return $this->getClient(self::CLIENTS_WRITEONLY)->getPredis()->publishEvent($event);
    }


}