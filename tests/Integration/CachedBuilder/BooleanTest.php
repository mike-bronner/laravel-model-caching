<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('boolean where true creates correct cache key', function () {
    Author::factory()
        ->create(['is_famous' => true]);

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-is_famous_=_1-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->where("is_famous", true)
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->where("is_famous", true)
        ->get();

    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($authors)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('boolean where false creates correct cache key', function () {
    Author::factory()
        ->create(['is_famous' => false]);

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-is_famous_=_-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->where("is_famous", false)
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->where("is_famous", false)
        ->get();

    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($authors)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('boolean where has relation with false condition and additional parent raw condition', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-exists-books.author_id_=_authors.id-is_famous_=_-authors.deleted_at_null-title_=_Mixed_Clause");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $expectedAuthor = Author::factory()->create(['is_famous' => false]);
    Book::factory()->create(['author_id' => $expectedAuthor->getKey(), 'title' => 'Mixed Clause']);

    $books = (new Book)
        ->whereHas('author', function ($query) {
            return $query->where('is_famous', false);
        })
        ->whereRaw("title = ?", ['Mixed Clause']) // Test ensures this binding is included in the key
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedBook)
        ->whereHas('author', function ($query) {
            return $query->where('is_famous', false);
        })
        ->whereRaw("title = ?", ['Mixed Clause'])
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});
