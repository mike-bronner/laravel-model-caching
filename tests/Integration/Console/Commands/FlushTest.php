<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use Illuminate\Support\Str;

beforeEach(function () {
    if (Str::startsWith($this->app->version(), '5.7')) {
        $this->withoutMockingConsoleOutput();
    }
});

test('given model is flushed', function () {
    $authors = (new Author)->all();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this
        ->cache
        ->tags($tags)
        ->get($key)['value'];
    $result = $this
        ->artisan('modelCache:clear', ['--model' => Author::class])
        ->execute();
    $flushedResults = $this
        ->cache
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toEqual($authors);
    expect($flushedResults)->toBeEmpty();
    expect(0)->toEqual($result);
});

test('extended model is flushed', function () {
    $authors = (new PrefixedAuthor)
        ->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors:genealabslaravelmodelcachingtestsfixturesprefixedauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors",
    ];

    $cachedResults = $this
        ->cache
        ->tags($tags)
        ->get($key)['value'];
    $result = $this
        ->artisan('modelCache:clear', ['--model' => PrefixedAuthor::class])
        ->execute();
    $flushedResults = $this
        ->cache
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toEqual($authors);
    expect($flushedResults)->toBeEmpty();
    expect(0)->toEqual($result);
});

test('given model with relationship is flushed', function () {
    $authors = (new Author)->with('books')->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache
        ->tags($tags)
        ->get($key)['value'];
    $result = $this
        ->artisan(
            'modelCache:clear',
            ['--model' => Author::class]
        )
        ->execute();
    $flushedResults = $this->cache
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toEqual($authors);
    expect($flushedResults)->toBeEmpty();
    expect(0)->toEqual($result);
});

test('non cached models cannot be flushed', function () {
    $result = $this->artisan(
            'modelCache:clear',
            ['--model' => UncachedAuthor::class]
        )
        ->execute();

    expect(1)->toEqual($result);
});

test('all models are flushed', function () {
    (new Author)->all();
    (new Book)->all();
    (new Store)->all();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $cachedAuthors = $this->cache
        ->tags($tags)
        ->get($key)['value'];
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $cachedBooks = $this->cache
        ->tags($tags)
        ->get($key)['value'];
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $cachedStores = $this->cache
        ->tags($tags)
        ->get($key)['value'];

    expect($cachedAuthors)->not->toBeEmpty();
    expect($cachedBooks)->not->toBeEmpty();
    expect($cachedStores)->not->toBeEmpty();

    $this->artisan('modelCache:clear')
        ->execute();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $cachedAuthors = $this->cache
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $cachedBooks = $this->cache
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $cachedStores = $this->cache
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedAuthors)->toBeEmpty();
    expect($cachedBooks)->toBeEmpty();
    expect($cachedStores)->toBeEmpty();
});

test('invalidate all only clears model cache prefix', function () {
    $modelCacheStore = $this->app['cache']->store('model')->getStore();
    $prefix = $modelCacheStore->getPrefix();
    $connection = $modelCacheStore->connection();

    $foreignKey = 'foreign-tenant:keep-me:' . uniqid();
    $connection->set($foreignKey, 'should-survive');

    (new Author)->all();
    (new Book)->all();

    $this->artisan('modelCache:clear')->execute();

    expect($connection->get($foreignKey))->toBe(
        'should-survive',
        'invalidateAll() must not delete keys outside the model cache prefix',
    );

    $remainingKeys = $this->scanRedisKeys($connection, $prefix . '*');
    expect($remainingKeys)->toBeEmpty(
        'invalidateAll() must remove all keys with the model cache prefix',
    );

    $connection->del($foreignKey);
});
