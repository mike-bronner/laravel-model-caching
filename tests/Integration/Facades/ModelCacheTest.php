<?php

use GeneaLabs\LaravelModelCaching\Facades\ModelCache;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('invalidate clears queries for target model', function () {
    $authors = (new Author)->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedBefore = $this->cache()->tags($tags)->get($key);
    expect($cachedBefore)->not->toBeNull();

    ModelCache::invalidate(Author::class);

    $cachedAfter = $this->cache()->tags($tags)->get($key);
    expect($cachedAfter)->toBeNull();
});

test('invalidating one model does not affect other models cache', function () {
    (new Author)->get();
    (new Book)->get();

    $bookTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $bookKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook");

    ModelCache::invalidate(Author::class);

    $bookCache = $this->cache()->tags($bookTags)->get($bookKey);
    expect($bookCache)->not->toBeNull();
});

test('multiple models can be invalidated in single call', function () {
    (new Author)->get();
    (new Book)->get();

    $authorTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $authorKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $bookTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $bookKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook");

    ModelCache::invalidate([Author::class, Book::class]);

    expect($this->cache()->tags($authorTags)->get($authorKey))->toBeNull();
    expect($this->cache()->tags($bookTags)->get($bookKey))->toBeNull();
});

test('mass update followed by invalidate returns fresh data', function () {
    $initialAuthors = (new Author)->get();

    (new UncachedAuthor)->where('id', '>', 0)->update(['name' => 'UPDATED']);

    $cachedAuthors = (new Author)->get();
    expect($cachedAuthors->first()->name)->not->toEqual('UPDATED');

    ModelCache::invalidate(Author::class);

    $freshAuthors = (new Author)->get();
    expect($freshAuthors->first()->name)->toEqual('UPDATED');
});

test('invalidation works after query update', function () {
    $authors = (new Author)->get();
    $firstName = $authors->first()->name;

    (new UncachedAuthor)->query()->where('id', $authors->first()->id)
        ->update(['name' => 'QUERY_UPDATED']);

    $staleAuthors = (new Author)->get();
    expect($staleAuthors->first()->name)->toEqual($firstName);

    ModelCache::invalidate(Author::class);

    $freshAuthors = (new Author)->get();
    expect($freshAuthors->first()->name)->toEqual('QUERY_UPDATED');
});

test('artisan clear command continues to work', function () {
    (new Author)->get();

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");

    expect($this->cache()->tags($tags)->get($key))->not->toBeNull();

    $this->artisan('modelCache:clear', ['--model' => Author::class])
        ->assertExitCode(0);

    expect($this->cache()->tags($tags)->get($key))->toBeNull();
});

test('invalidate throws for non cachable model', function () {
    expect(fn () => ModelCache::invalidate(UncachedAuthor::class))
        ->toThrow(InvalidArgumentException::class);
});
