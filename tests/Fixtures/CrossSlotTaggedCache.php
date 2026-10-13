<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Cache\RedisTaggedCache;

class CrossSlotTaggedCache extends RedisTaggedCache
{
    public function flush()
    {
        throw new \RedisException("CROSSSLOT Keys in request don't hash to the same slot");
    }
}
