<?php
namespace Gone\AppCore\Tests\Redis;

use Gone\AppCore\Redis\WorkItem;
use Gone\AppCore\Redis\WorkQueue;

class WorkQueueTest extends RedisTest
{
    /** @var WorkQueue */
    protected $workQueue;

    public function setUp()
    {
        parent::setUp();

        $this->workQueue = new WorkQueue($this->redis);
    }

    public function testWorkQueueAdd()
    {
        $this->assertEquals(0, $this->workQueue->getLength());

        $data = [
            "firstName" => $this->getFaker()->firstName,
            "lastName" => $this->getFaker()->lastName,
        ];

        $this->workQueue->push(
            (new WorkItem())
                ->addPayload($data)
        )->commitQueue();

        $this->assertEquals(1, $this->workQueue->getLength());

        $dataFromQueue = $this->workQueue->pop();

        $this->assertEquals($data, $dataFromQueue->getPayload());
    }
}