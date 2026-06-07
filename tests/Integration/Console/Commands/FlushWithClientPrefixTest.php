<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Console\Commands;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

// Regression coverage for issue #598: `modelCache:clear` (no --model) cleared
// nothing when the Redis connection carried a client-level prefix (phpredis
// OPT_PREFIX / Predis KeyPrefixProcessor) layered on top of the cache-store
// prefix, because invalidateAll()'s SCAN/DEL only accounted for the store
// prefix. The "model-prefixed" store's connection adds a "tenant-{token}:"
// client prefix, reproducing the real-world layering the original test harness
// (empty client prefix) never exercised.
class FlushWithClientPrefixTest extends IntegrationTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        config(['laravel-model-caching.store' => 'model-prefixed']);
        app('redis')->connection('model-cache-prefixed')->flushdb();
    }

    protected function tearDown() : void
    {
        app('redis')->connection('model-cache-prefixed')->flushdb();

        parent::tearDown();
    }

    public function testEntireCacheIsClearedOnPrefixedConnection()
    {
        $connection = app('redis')->connection('model-cache-prefixed');
        // A key belonging to another tenant on the SAME connection (it shares the
        // client prefix) but living OUTSIDE the model-cache store prefix. A scoped
        // clear must leave it untouched.
        $connection->set('foreign:keep-me', 'should-survive');

        $cachedAuthors = Author::query()->get();
        $authorId = $cachedAuthors->first()->id;

        // Mutate the row behind the cache's back so a stale cache is observable.
        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'CLEARED_AUTHOR']);

        // The cache is still serving the pre-mutation value, proving it is populated.
        $this->assertNotSame(
            'CLEARED_AUTHOR',
            Author::query()->get()->firstWhere('id', $authorId)->name
        );

        $this->artisan('modelCache:clear')
            ->assertExitCode(0);

        // After a working clear the next read misses the cache and returns fresh
        // data. This assertion fails before the fix, because the clear deletes
        // nothing and the stale value is still served.
        $this->assertSame(
            'CLEARED_AUTHOR',
            Author::query()->get()->firstWhere('id', $authorId)->name
        );

        // Scoped clear: the foreign tenant key on the same connection survives.
        $this->assertSame('should-survive', $connection->get('foreign:keep-me'));
    }
}
