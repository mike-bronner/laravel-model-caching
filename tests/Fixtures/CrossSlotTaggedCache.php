<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Cache\RedisTaggedCache;

// Simulates a Redis Cluster reached through a single, non-cluster connection:
// the multi-key Lua flush Laravel runs touches keys across hash slots and the
// server rejects it. Everything else (entries(), del(), forget()) still routes
// to the real store, so the slot-safe recovery path can be exercised end-to-end.
class CrossSlotTaggedCache extends RedisTaggedCache
{
    public function flush()
    {
        throw new \RedisException("CROSSSLOT Keys in request don't hash to the same slot");
    }
}
