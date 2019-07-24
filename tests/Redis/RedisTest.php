<?php
namespace Gone\AppCore\Tests\Redis;

use Gone\AppCore\Redis\Redis;
use Gone\AppCore\Test\BaseTestCase;

abstract class RedisTest extends BaseTestCase
{
    /** @var Redis */
    protected $redis;

    public function setUp()
    {
        parent::setUp();
        $this->redis = $this->getApp()->getContainer()->get(Redis::class);
        $this->redis->flushall();
        $this->redis->__clearPerfLog();
    }

    public function tearDown()
    {
        echo "\n" . $this->redis->__getPerfLogAsString();
        $this->redis->flushall();

        parent::tearDown();
    }
}