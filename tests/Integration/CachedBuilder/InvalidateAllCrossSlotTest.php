<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotDeleteRedisStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CrossSlotFlushRepository;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\Repository;

// Cluster coverage for the entire-cache clear path (issue #598 follow-through).
// invalidateAll() must (a) survive a CROSSSLOT rejection on a batched DEL by
// falling back to per-key deletes, and (b) the slot-safe tag recovery must keep
// working when the connection carries a client-level prefix.
class InvalidateAllCrossSlotTest extends IntegrationTestCase
{
    private const TAG = 'cross-slot-tag';

    public function testInvalidateAllRecoversFromCrossSlotDeleteFailure()
    {
        $realStore = app('cache')->store('model')->getStore();

        (new Author)->all();
        (new Book)->all();

        $this->assertNotEmpty($this->modelCacheKeys());

        // A store whose batched DEL raises CROSSSLOT, forcing invalidateAll() onto
        // its per-key fallback. The cache must still be physically emptied.
        $store = new CrossSlotDeleteRedisStore(app('redis'), $realStore->getPrefix(), 'model-cache');
        $modelCacheRepository = new ModelCacheRepository(new Repository($store), false);

        $modelCacheRepository->invalidateAll();

        $this->assertSame([], $this->modelCacheKeys());
    }

    public function testInvalidateTagsRecoversFromCrossSlotFailureWithClientPrefix()
    {
        // The slot-safe tag recovery must also clear keys when the connection
        // carries a client-level prefix layered over the store prefix (issue #598).
        $repository = app('cache')->store('model-prefixed');
        $store = $repository->getStore();
        app('redis')->connection('model-cache-prefixed')->flushdb();

        $repository->tags([self::TAG])->forever('entry-a', 'value-a');
        $repository->tags([self::TAG])->forever('entry-b', 'value-b');

        $this->assertSame('value-a', $repository->tags([self::TAG])->get('entry-a'));

        $throwingRepository = new CrossSlotFlushRepository($store);
        $modelCacheRepository = new ModelCacheRepository($throwingRepository, false);

        $modelCacheRepository->invalidateTags([self::TAG]);

        $this->assertNull($repository->tags([self::TAG])->get('entry-a'));
        $this->assertNull($repository->tags([self::TAG])->get('entry-b'));

        app('redis')->connection('model-cache-prefixed')->flushdb();
    }

    private function modelCacheKeys() : array
    {
        $token = (int) env('TEST_TOKEN', 1);
        $pattern = "lmc-test-{$token}:*";
        $client = app('redis')->connection('model-cache')->client();
        $cursor = null;
        $keys = [];

        do {
            $found = $client->scan($cursor, $pattern, 1000);

            if (is_array($found)) {
                $keys = array_merge($keys, $found);
            }
        } while ($cursor > 0);

        return $keys;
    }
}
