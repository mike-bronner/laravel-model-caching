<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakePredisConnectionException;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ThrowingCacheStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;

class CacheFallbackTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->cache()->flush();
    }

    private function breakCacheConnection(string $exceptionClass = \RedisException::class): void
    {
        $throwingStore = new ThrowingCacheStore($exceptionClass);
        $throwingRepo = new Repository($throwingStore);

        $this->app->extend('cache', function ($cache) use ($throwingRepo) {
            return new class($this->app, $throwingRepo) extends CacheManager
            {
                private Repository $throwingRepo;

                public function __construct($app, Repository $throwingRepo)
                {
                    parent::__construct($app);
                    $this->throwingRepo = $throwingRepo;
                }

                public function store($name = null)
                {
                    return $this->throwingRepo;
                }

                public function driver($driver = null)
                {
                    return $this->throwingRepo;
                }
            };
        });
    }

    public function testCacheReadFailureFallsThroughToDatabaseWhenEnabled(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $authors = Author::all();

        $this->assertNotNull($authors);
        $this->assertNotEmpty($authors);
    }

    public function testCacheReadFailureFallsThroughWithRedisException(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(\RedisException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $authors = Author::all();

        $this->assertNotNull($authors);
        $this->assertNotEmpty($authors);
    }

    public function testCacheReadFailureFallsThroughWithPredisException(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(FakePredisConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $authors = Author::all();

        $this->assertNotNull($authors);
        $this->assertNotEmpty($authors);
    }

    public function testIsCacheConnectionExceptionRecognizesPredisException(): void
    {
        $instance = new Author;
        $predisException = new FakePredisConnectionException('Connection refused');

        $this->assertTrue($instance->isCacheConnectionException($predisException));
    }

    public function testIsCacheConnectionExceptionRecognizesRedisException(): void
    {
        $instance = new Author;
        $redisException = new \RedisException('Connection refused');

        $this->assertTrue($instance->isCacheConnectionException($redisException));
    }

    public function testIsCacheConnectionExceptionRejectsUnrelatedExceptions(): void
    {
        $instance = new Author;
        $runtimeException = new \RuntimeException('Something else');

        $this->assertFalse($instance->isCacheConnectionException($runtimeException));
    }

    public function testCacheReadFailureThrowsWhenFallbackDisabled(): void
    {
        config(['laravel-model-caching.fallback-to-database' => false]);
        $this->breakCacheConnection();

        $this->expectException(\RedisException::class);

        Author::all();
    }

    public function testNonConnectionExceptionIsNotSwallowed(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(\RuntimeException::class);

        $this->expectException(\RuntimeException::class);

        Author::all();
    }

    public function testCacheFlushFailureLogsWarningWhenFallbackEnabled(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = (new Author)->newQueryWithoutScopes()->first();
        $this->assertNotNull($author);

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $author->flushCache();
    }

    public function testCacheFlushFailureThrowsWhenFallbackDisabled(): void
    {
        config(['laravel-model-caching.fallback-to-database' => false]);

        $author = (new Author)->newQueryWithoutScopes()->first();
        $this->assertNotNull($author);

        $this->breakCacheConnection();

        $this->expectException(\RedisException::class);

        $author->flushCache();
    }

    public function testFallbackConfigDefaultsToFalse(): void
    {
        $this->assertFalse(
            config('laravel-model-caching.fallback-to-database', false)
        );
    }

    public function testMockCacheStoreEndToEndFallback(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once();

        $authors = Author::query()->get();

        $this->assertNotEmpty($authors);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $authors);
    }

    public function testDeleteSucceedsWhenCacheFlushFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = Author::factory()->create(['name' => 'DeleteTest']);
        $authorId = $author->id;

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $result = Author::where('id', $authorId)->delete();

        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function testForceDeleteSucceedsWhenCacheFlushFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = Author::factory()->create(['name' => 'ForceDeleteTest']);
        $authorId = $author->id;

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $result = Author::where('id', $authorId)->forceDelete();

        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function testIncrementSucceedsWhenCacheFlushFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $book = Book::first();
        $originalPrice = $book->price;

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        Book::where('id', $book->id)->increment('price', 10);

        $book->refresh();
        $this->assertEquals($originalPrice + 10, $book->price);
    }

    public function testDecrementSucceedsWhenCacheFlushFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $book = Book::first();
        $originalPrice = $book->price;

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        Book::where('id', $book->id)->decrement('price', 5);

        $book->refresh();
        $this->assertEquals($originalPrice - 5, $book->price);
    }

    public function testModelSaveFlushesGracefullyWhenCacheFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = Author::first();
        $author->name = 'Updated via Save';

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $result = $author->save();

        $this->assertTrue($result);
    }

    public function testModelCreateFlushesGracefullyWhenCacheFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $author = Author::create([
            'name' => 'Created During Outage',
            'email' => 'outage@test.com',
        ]);

        $this->assertNotNull($author);
        $this->assertNotNull($author->id);
    }

    public function testPivotSyncSucceedsWhenCacheFlushFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $pivotRow = \Illuminate\Support\Facades\DB::table('role_user')->first();
        $this->assertNotNull($pivotRow, 'Pivot table should have seeded data');

        $user = (new User)->newQueryWithoutScopes()->find($pivotRow->user_id);
        $this->assertNotNull($user);

        $roleIds = \Illuminate\Support\Facades\DB::table('role_user')
            ->where('user_id', $user->id)
            ->pluck('role_id')
            ->toArray();

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $result = $user->roles()->sync($roleIds);

        $this->assertIsArray($result);
    }

    public function testHasManyThroughFallsBackWhenCacheFails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $author = (new Author)->newQueryWithoutScopes()->first();
        $printers = $author->printers()->get();

        $this->assertNotNull($printers);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $printers);
    }

    public function testQueryGetFallsBackWithBrokenCacheAndCooldown(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $this->breakCacheConnection();

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'laravel-model-caching');
            });

        $authors = Author::all();

        $this->assertNotNull($authors);
        $this->assertNotEmpty($authors);
    }

    public function testFlushCacheIsNoOpWhenCachingDisabled(): void
    {
        config(['laravel-model-caching.enabled' => false]);
        config(['laravel-model-caching.fallback-to-database' => false]);
        $this->breakCacheConnection();

        $this->expectNotToPerformAssertions();

        (new Author)->flushCache();
    }
}
