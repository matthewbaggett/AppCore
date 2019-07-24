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
        $this->redis->multi();
        $data = $this->redis->get($key);
        $this->redis->del($key);
        $this->redis->exec();

        \Kint::dump($data);
        exit;

    }

    public function push(WorkItem $workItem) : WorkQueue
    {
        return $this->addToQueue($workItem);
    }

    public function pop() : ?WorkItem
    {
        return $this->takeFromQueue();
    }

    protected function getNamespacePrefix() : string
    {
        $namespace = preg_replace("/[^a-zA-Z0-9-_]/", '', strtolower($this->namespace));
        return "{$namespace}:";
    }

    /**
     * Returns whether or not committing the queue was successful.
     * @return bool
     */
    public function commitQueue() : bool
    {
        if(count($this->buffer) == 0){
            return true;
        }

        $dict = [];
        foreach($this->buffer as $bufferedWorkItem){
            $dict[$this->namespace . ":" . $bufferedWorkItem->uniqueKey()] = $bufferedWorkItem->serialize();
        }
        $this->buffer = [];
        /** @var Response\Status $status */
        $status = $this->redis->mset($dict);

        return $status->getPayload() == 'OK';
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