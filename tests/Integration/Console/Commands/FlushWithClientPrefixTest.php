<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

// Regression coverage for issue #598: `modelCache:clear` (no --model) cleared
// nothing when the Redis connection carried a client-level prefix (phpredis
// OPT_PREFIX / Predis KeyPrefixProcessor) layered on top of the cache-store
// prefix, because invalidateAll()'s SCAN/DEL only accounted for the store
// prefix. The "model-prefixed" store's connection adds a "tenant-{token}:"
// client prefix, reproducing the real-world layering the original test harness
// (empty client prefix) never exercised.

beforeEach(function () {
    config(['laravel-model-caching.store' => 'model-prefixed']);
    app('redis')->connection('model-cache-prefixed')->flushdb();
});

afterEach(function () {
    // Pest still runs afterEach() when setUp() skipped the test, and
    // setUp() skips exactly when Redis is unreachable. Flushing here
    // unguarded turned that clean skip back into a connection error.
    if ($this->redisIsAvailable) {
        app('redis')->connection('model-cache-prefixed')->flushdb();
    }
});

test('entire cache is cleared on prefixed connection', function () {
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
    expect(Author::query()->get()->firstWhere('id', $authorId)->name)->not->toBe('CLEARED_AUTHOR');

    $this->artisan('modelCache:clear')
        ->assertExitCode(0);

    // After a working clear the next read misses the cache and returns fresh
    // data. This assertion fails before the fix, because the clear deletes
    // nothing and the stale value is still served.
    expect(Author::query()->get()->firstWhere('id', $authorId)->name)->toBe('CLEARED_AUTHOR');

    // Scoped clear: the foreign tenant key on the same connection survives.
    expect($connection->get('foreign:keep-me'))->toBe('should-survive');
});
