<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Cache\RedisTagSet;
use Illuminate\Cache\Repository;

// A cache repository backed by the real Redis store, but whose tagged flush
// raises CROSSSLOT — wiring CrossSlotTaggedCache over the genuine store so tests
// reproduce the cluster failure without needing an actual multi-node cluster.
class CrossSlotFlushRepository extends Repository
{
    public function tags($names)
    {
        $names = is_array($names) ? $names : func_get_args();

        return new CrossSlotTaggedCache(
            $this->getStore(),
            new RedisTagSet($this->getStore(), $names),
        );
    }
}
