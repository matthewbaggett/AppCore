<?php

namespace Gone\AppCore\Redis;

use Predis\Response;

class WorkQueue
{
    /** @var Redis */
    protected $redis;

    /** @var string */
    protected $namespace = "work-queue";

    /** @var WorkItem[] */
    protected $buffer = [];

    protected const MAX_INDIVIDUAL_COMMIT_QUEUE_SIZE = 100;

    public function __construct(
        Redis $redis
    )
    {
        $this->redis = $redis;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     * @return WorkQueue
     */
    public function setNamespace(string $namespace): WorkQueue
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function addToQueue(WorkItem $workItem) : WorkQueue
    {
        $this->buffer[] = $workItem;
        return $this;
    }

    public function addMultipleToQueue(array $workItems) : WorkQueue
    {
        foreach($workItems as $workItem){
            $this->addToQueue($workItem);
        }
        return $this;
    }

    /**
     * Will return a work item from the configured queue.
     * Will return null if none are available
     *
     * @return WorkItem|null
     */
    public function takeFromQueue() : ?WorkItem
    {
        $keys = $this->redis->keys($this->getNamespacePrefix() . "*", 1);
        if(count($keys) == 0){
            return null;
        }
        $key = $keys[0];

        // Atomically get and delete the node in a transaction
        $instance = $this->redis->getServerByKey($key)->getPredis();
        $instance->multi();
        $instance->get($key);
        $instance->del($key);
        $instance->publishEvent(
            (new Event())
                ->setChannel("event:queue:{$this->getNamespacePrefix(false)}")
                ->setPayload($key)
                ->setType('QUEUE:SUB')
        );
        $result = $instance->exec();

        if($result instanceof Response\Status){
            \Kint::dump($result);
        }
        
        list($getData, $deletedKeys) = $result;

        $workItem = new WorkItem();
        $workItem->unserialize($getData);
        return $workItem;
    }

    public function push(WorkItem $workItem) : WorkQueue
    {
        return $this->addToQueue($workItem);
    }

    public function pop() : ?WorkItem
    {
        return $this->takeFromQueue();
    }

    protected function getNamespacePrefix(bool $trailingColon = true) : string
    {
        $namespace = preg_replace("/[^a-zA-Z0-9-_{}]/", '', strtolower($this->namespace));
        return $trailingColon ? "{$namespace}:" : $namespace;
    }

    /**
     * Returns whether or not committing the queue was successful.
     * @return bool
     */
    public function commitQueue() : bool
    {
        $addedToWorkQueue = 0;

        $keys = [];

        while(count($this->buffer) > 0) {
            $dict = [];

            while (
                count($this->buffer) > 0 &&
                count($dict) <= self::MAX_INDIVIDUAL_COMMIT_QUEUE_SIZE
            ) {
                $bufferedWorkItem = array_shift($this->buffer);
                $key = $this->getNamespacePrefix() . $bufferedWorkItem->uniqueKey();
                $dict[$key] = $bufferedWorkItem->serialize();
                $keys[] = $key;
                $addedToWorkQueue++;
            }
            /** @var Response\Status $status */
            $status = $this->redis->mset($dict);
            if($status->getPayload() != 'OK'){
                return false;
            }
        }

        $this->redis->publishEvent(
            (new Event())
                ->setChannel("event:queue:{$this->getNamespacePrefix(false)}")
                ->setPayload($keys)
                ->setType('QUEUE:ADD')
        );

        return true;
    }

    public function getLength() : int
    {
        return count($this->redis->keys($this->getNamespacePrefix() . "*"));
    }

    public function __destruct()
    {
        $this->commitQueue();
    }

}