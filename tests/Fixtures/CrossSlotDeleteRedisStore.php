<?php namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use Illuminate\Cache\RedisStore;

// A real Redis store whose connection raises CROSSSLOT on batched deletes, so the
// no-model clear path's slot-safe fallback can be verified end-to-end.
class CrossSlotDeleteRedisStore extends RedisStore
{
    public function connection()
    {
        return new CrossSlotDeleteConnection(parent::connection());
    }
}
