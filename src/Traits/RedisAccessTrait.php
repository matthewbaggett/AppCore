<?php
namespace Segura\AppCore\Traits;

use Predis\Client as Redis;
use Segura\AppCore\App;

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
