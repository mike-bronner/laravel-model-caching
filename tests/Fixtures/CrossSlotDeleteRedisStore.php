<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Cache\RedisStore;

class CrossSlotDeleteRedisStore extends RedisStore
{
    public function connection()
    {
        return new CrossSlotDeleteConnection(parent::connection());
    }
}
