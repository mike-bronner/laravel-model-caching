<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Cache\RedisTagSet;
use Illuminate\Cache\Repository;

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
