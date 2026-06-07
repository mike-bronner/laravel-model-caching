<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Console\Commands;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verifies `modelCache:clear` (no --model) genuinely empties the model cache on
// every supported provider, not just Redis. Each provider is proven the same
// client-agnostic way: prime the cache, mutate the underlying row behind its
// back, then assert the post-clear read returns fresh data (a populated cache
// would keep serving the stale value). Redis (single + prefixed) is covered by
// FlushTest / FlushWithClientPrefixTest and DynamoDB by DynamoDbModelCachingTest.
class ClearAcrossProvidersTest extends IntegrationTestCase
{
    private $fileCachePath;

    protected function setUp() : void
    {
        parent::setUp();

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
    }

    protected function tearDown() : void
    {
        Schema::connection('testing')->dropIfExists('cache');

        parent::tearDown();
    }

    public function testClearEmptiesArrayStore()
    {
        $this->assertClearEmptiesStore('array-test');
    }

    public function testClearEmptiesFileStore()
    {
        $this->assertClearEmptiesStore('file-test');
    }

    public function testClearEmptiesDatabaseStore()
    {
        $this->assertClearEmptiesStore('database-test');
    }

    public function testClearEmptiesMemcachedStore()
    {
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

        $this->assertClearEmptiesStore('memcached-test');
    }

    private function assertClearEmptiesStore(string $storeName) : void
    {
        config(['laravel-model-caching.store' => $storeName]);

        $authorId = UncachedAuthor::query()->value('id');
        Author::query()->get();

        // Mutate the row behind the cache's back so a stale cache is observable.
        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'CLEARED_AUTHOR']);

        $this->assertNotSame(
            'CLEARED_AUTHOR',
            Author::query()->get()->firstWhere('id', $authorId)->name,
            "[{$storeName}] cache should still serve the pre-mutation value"
        );

        $this->artisan('modelCache:clear')
            ->assertExitCode(0);

        $this->assertSame(
            'CLEARED_AUTHOR',
            Author::query()->get()->firstWhere('id', $authorId)->name,
            "[{$storeName}] clear should empty the model cache so fresh data is read"
        );
    }
}
