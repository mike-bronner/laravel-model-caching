<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verifies `modelCache:clear` (no --model) genuinely empties the model cache on
// every supported provider, not just Redis. Each provider is proven the same
// client-agnostic way: prime the cache, mutate the underlying row behind its
// back, then assert the post-clear read returns fresh data (a populated cache
// would keep serving the stale value). Redis (single + prefixed) is covered by
// FlushTest / FlushWithClientPrefixTest and DynamoDB by DynamoDbModelCachingTest.

beforeEach(function () {
    $token = (int) env('TEST_TOKEN', 1);
    $this->fileCachePath = sys_get_temp_dir() . "/lmc-file-cache-{$token}";

    config([
        'cache.stores.array-test' => ['driver' => 'array', 'serialize' => false],
        'cache.stores.file-test' => ['driver' => 'file', 'path' => $this->fileCachePath],
        'cache.stores.database-test' => [
            'driver' => 'database',
            'connection' => 'testing',
            'table' => 'cache',
        ],
    ]);

    if (! Schema::connection('testing')->hasTable('cache')) {
        Schema::connection('testing')->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
    }

    // The file store persists on disk between runs (temp dir), so clear it up
    // front to keep the stale-read check idempotent.
    $this->app['cache']->store('file-test')->flush();
});

afterEach(function () {
    Schema::connection('testing')->dropIfExists('cache');
});

test('clear empties array store', function () {
    assertClearEmptiesStore('array-test');
});

test('clear empties file store', function () {
    assertClearEmptiesStore('file-test');
});

test('clear empties database store', function () {
    assertClearEmptiesStore('database-test');
});

test('clear empties memcached store', function () {
    if (! extension_loaded('memcached')) {
        $this->markTestSkipped('The memcached extension is not installed.');
    }

    config(['cache.stores.memcached-test' => [
        'driver' => 'memcached',
        'servers' => [[
            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
            'port' => (int) env('MEMCACHED_PORT', 11211),
            'weight' => 100,
        ]],
    ]]);

    // The extension is present on CI runners even when no memcached server is
    // running, so probe for a reachable server (a round-trip) rather than just
    // a loaded extension. Without this, the store silently no-ops and the
    // stale-read assertion below fails instead of skipping.
    try {
        $store = $this->app['cache']->store('memcached-test');
        $store->forever('lmc-memcached-probe', 'ok');
        $reachable = $store->get('lmc-memcached-probe') === 'ok';
    } catch (\Throwable $exception) {
        $reachable = false;
    }

    if (! $reachable) {
        $this->markTestSkipped('No reachable memcached server.');
    }

    $store->forget('lmc-memcached-probe');

    assertClearEmptiesStore('memcached-test');
});

function assertClearEmptiesStore(string $storeName): void
{
    config(['laravel-model-caching.store' => $storeName]);

    $authorId = UncachedAuthor::query()->value('id');
    Author::query()->get();

    // Mutate the row behind the cache's back so a stale cache is observable.
    UncachedAuthor::query()
        ->where('id', $authorId)
        ->update(['name' => 'CLEARED_AUTHOR']);

    expect(Author::query()->get()->firstWhere('id', $authorId)->name)->not->toBe(
        'CLEARED_AUTHOR',
        "[{$storeName}] cache should still serve the pre-mutation value",
    );

    test()->artisan('modelCache:clear')
        ->assertExitCode(0);

    expect(Author::query()->get()->firstWhere('id', $authorId)->name)->toBe(
        'CLEARED_AUTHOR',
        "[{$storeName}] clear should empty the model cache so fresh data is read",
    );
}
