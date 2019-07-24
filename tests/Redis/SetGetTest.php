<?php
namespace Gone\AppCore\Tests\Redis;

class SetGetTest extends RedisTest
{
    public function testSet()
    {
        $k = $this->getFaker()->word;
        $v = $this->getFaker()->words(5, true);

        $this->redis->set($k,$v);
        $this->assertEquals($v, $this->redis->get($k));
    }
}