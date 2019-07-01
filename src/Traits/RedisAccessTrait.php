<?php
namespace Gone\AppCore\Traits;

use Gone\AppCore\App;
use Predis\Client as Redis;

trait RedisAccessTrait
{
    /** @var Redis */
    protected $redis;

    public function getRedis() : Redis
    {
        if (!$this->redis) {
            $this->redis = App::Instance()->getContainer()->get(Redis::class);
        }
        return $this->redis;
    }
}
