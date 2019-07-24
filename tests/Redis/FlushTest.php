<?php
namespace Gone\AppCore\Tests\Redis;

class FlushTest extends RedisTest
{
    public function testFlushAll()
    {
        $k = $this->getFaker()->word;
        $v = $this->getFaker()->words(5, true);

        $this->redis->set($k,$v);
        $this->assertEquals($v, $this->redis->get($k));

        $this->redis->flushall();

        $this->assertNotEquals($v, $this->redis->get($k));
        $this->assertEquals("", $this->redis->get($k));
    }
}