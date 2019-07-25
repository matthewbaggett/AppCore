<?php
namespace Gone\AppCore\Tests\Redis;

use Predis;

class MSetMGetTest extends RedisTest
{
    const ITERATIONS_MIN = 20;
    const ITERATIONS_MAX = 80;

    public function testMSet_Ungrouped()
    {
        $numberToGenerate = $this->getFaker()->numberBetween(self::ITERATIONS_MIN, self::ITERATIONS_MAX);

        $prefix = "k-" . $this->getFaker()->word;
        $suffixes = [];
        for($i = 0; $i < $numberToGenerate; $i++){
            $words = $this->getFaker()->words(5);
            shuffle($words);
            $suffix = "s-" . implode("-", $words);
            $suffixes[] = $suffix;
            $data["{$prefix}:{$suffix}"] = "v-" . $this->getFaker()->numberBetween(10000,99999);
        }

        ksort($data);

        #\Kint::dump($data);
        /** @var Predis\Response\Status $status */
        $status = $this->redis->mset($data);

        #\Kint::dump($status);
        $this->assertInstanceOf(Predis\Response\Status::class, $status);
        $this->assertEquals("OK", $status->getPayload());

        $this->assertArraysEquitable(array_values($data), $this->redis->mget(array_keys($data)));

        $this->assertCount(count($data), $this->redis->keys("*"));
        $this->assertArraysEquitable(array_keys($data), array_values($this->redis->keys("*")));

        $this->assertCount(1, $this->redis->keys("*:{$suffixes[0]}"));
        $this->assertCount(count($data),  $this->redis->keys("{$prefix}:*"));
    }

    public function testMSet_Grouped()
    {
        $numberToGenerate = $this->getFaker()->numberBetween(self::ITERATIONS_MIN, self::ITERATIONS_MAX);

        $prefix = "k-" . $this->getFaker()->word;

        for($i = 0; $i < $numberToGenerate; $i++){
            $words = $this->getFaker()->words(5);
            shuffle($words);
            $suffix = "s-" . implode("-", $words);
            $suffixes[] = $suffix;
            $data["{{$prefix}}:{$suffix}"] = "v-" . $this->getFaker()->numberBetween(10000,99999);
        }

        ksort($data);

        /** @var Predis\Response\Status $status */
        $status = $this->redis->mset($data);

        $this->assertInstanceOf(Predis\Response\Status::class, $status);
        $this->assertEquals("OK", $status->getPayload());

        $this->assertArraysEquitable(array_values($data), $this->redis->mget(array_keys($data)));

        $this->assertCount(count($data), $this->redis->keys("*"));
        $this->assertArraysEquitable(array_keys($data), array_values($this->redis->keys("*")));

        $this->assertCount(1, $this->redis->keys("*:{$suffixes[0]}"));
        $this->assertCount(count($data),  $this->redis->keys("{{$prefix}}:*"));
    }

}