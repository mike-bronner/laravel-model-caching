<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ThrowingCacheStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Redis\RedisManager;

function breakCacheConnection(string $exceptionClass = RedisException::class): void
{
    $throwingStore = new ThrowingCacheStore($exceptionClass);
    $throwingRepo = new Repository($throwingStore);

    app()->extend('cache', function ($cache) use ($throwingRepo) {
        return new class(app(), $throwingRepo) extends CacheManager
        {
            public function __construct($app, private Repository $throwingRepo)
            {
                parent::__construct($app);
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

function predisManager(string $clientPrefix): RedisManager
{
    $options = $clientPrefix === ''
        ? []
        : ['prefix' => $clientPrefix];

    return new RedisManager(app(), 'predis', [
        'options' => [],
        'model-cache-predis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 3,
            'options' => $options,
        ],
    ]);
}

function assertClearEmptiesStore(string $storeName): void
{
    config(['laravel-model-caching.store' => $storeName]);

    $authorId = UncachedAuthor::query()->value('id');
    Author::query()->get();

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

function assertStaleCacheIsCleared(): void
{
    $authorId = (new Author)->get()->first()->id;
    (new UncachedAuthor)->where("id", $authorId)->update(["name" => "CLEARED_AUTHOR"]);

    expect((new Author)->get()->firstWhere("id", $authorId)->name)->not->toBe("CLEARED_AUTHOR");

    test()->artisan("modelCache:clear")->assertExitCode(0);

    expect((new Author)->get()->firstWhere("id", $authorId)->name)->toBe("CLEARED_AUTHOR");
}
