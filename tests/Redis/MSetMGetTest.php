<?php
namespace Gone\AppCore\Tests\Redis;

use Predis;

class MSetMGetTest extends RedisTest
{
    const ITERATIONS_MIN = 2;
    const ITERATIONS_MAX = 2;

    public function testMSet()
    {
        $prefix = "k-" . $this->getFaker()->word;
        $suffixes = $this->getFaker()->words($this->getFaker()->numberBetween(self::ITERATIONS_MIN, self::ITERATIONS_MAX));

        foreach($suffixes as $suffix){
            $data["{{$prefix}}:{$suffix}"] = "v-" . $this->getFaker()->numberBetween(10000,99999);
        }

        ksort($data);

        /** @var Predis\Response\Status $status */
        $status = $this->redis->mset($data);

        $this->assertInstanceOf(Predis\Response\Status::class, $status);
        $this->assertEquals("OK", $status->getPayload());

        $this->assertEquals(array_values($data), $this->redis->mget(array_keys($data)));

        $this->assertCount(count($data), $this->redis->keys("*"));
        $this->assertEquals(array_keys($data), array_values($this->redis->keys("*")));

        $this->assertCount(1, $this->redis->keys("*:{$suffixes[0]}"));
        $this->assertCount(count($data),  $this->redis->keys("{{$prefix}}:*"));
    }

}