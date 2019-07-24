<?php
namespace Gone\AppCore\Tests\Redis;

use Predis;

class MSetMGetTest extends RedisTest
{
    const ITERATIONS_MIN = 200;
    const ITERATIONS_MAX = 800;

    public function testMSet()
    {
        $numberToGenerate = $this->getFaker()->numberBetween(self::ITERATIONS_MIN, self::ITERATIONS_MAX);

        $numberToGenerate = 2000;
        $prefix = "k-" . $this->getFaker()->word;

        for($i = 0; $i < $numberToGenerate; $i++){
            $words = $this->getFaker()->words(5);
            shuffle($words);
            $suffix = "s-" . implode("-", $words);
            $data["{{$prefix}}:{$suffix}"] = "v-" . $this->getFaker()->numberBetween(10000,99999);
        }
        exit;

        ksort($data);

        /** @var Predis\Response\Status $status */
        $status = $this->redis->mset($data);
        exit;

        $this->assertInstanceOf(Predis\Response\Status::class, $status);
        $this->assertEquals("OK", $status->getPayload());

        $this->assertEquals(array_values($data), $this->redis->mget(array_keys($data)));

        $this->assertCount(count($data), $this->redis->keys("*"));
        $this->assertEquals(array_keys($data), array_values($this->redis->keys("*")));

        $this->assertCount(1, $this->redis->keys("*:{$suffixes[0]}"));
        $this->assertCount(count($data),  $this->redis->keys("{{$prefix}}:*"));
    }

}