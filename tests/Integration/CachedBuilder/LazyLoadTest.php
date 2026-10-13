<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedProfile;

test('belongs to relationship', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.id_=_1-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $result = (new Book)
        ->where("id", 1)
        ->first()
        ->author;
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $uncachedResult = (new UncachedBook)
        ->where("id", 1)
        ->first()
        ->author;

    expect($result->id)->toEqual($uncachedResult->id);
    expect($cachedResult->id)->toEqual($uncachedResult->id);
    expect(get_class($result))->toEqual(Author::class);
    expect(get_class($cachedResult))->toEqual(Author::class);
    expect(get_class($uncachedResult))->toEqual(UncachedAuthor::class);
    expect($result)->not->toBeNull();
    expect($cachedResult)->not->toBeNull();
    expect($uncachedResult)->not->toBeNull();
});

test('has many relationship', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $result = (new Author)
        ->find(1)
        ->books;
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $uncachedResult = (new UncachedAuthor)
        ->find(1)
        ->books;

    expect($result->pluck("id"))->toEqual($uncachedResult->pluck("id"));
    expect($cachedResult->pluck("id"))->toEqual($uncachedResult->pluck("id"));
    expect(get_class($result->first()))->toEqual(Book::class);
    expect(get_class($cachedResult->first()))->toEqual(Book::class);
    expect(get_class($uncachedResult->first()))->toEqual(UncachedBook::class);
    expect($result)->not->toBeEmpty();
    expect($cachedResult)->not->toBeEmpty();
    expect($uncachedResult)->not->toBeEmpty();
});

test('has one relationship', function () {
    $authorId = (new UncachedProfile)
        ->first()
        ->author_id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:profiles:genealabslaravelmodelcachingtestsfixturesprofile-profiles.author_id_=_{$authorId}-profiles.author_id_notnull-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:profiles",
    ];

    $result = (new Author)
        ->find($authorId)
        ->profile;
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $uncachedResult = (new UncachedAuthor)
        ->find($authorId)
        ->profile;

    expect($result->id)->toEqual($uncachedResult->id);
    expect($cachedResult->id)->toEqual($uncachedResult->id);
    expect(get_class($result->first()))->toEqual(Profile::class);
    expect(get_class($cachedResult->first()))->toEqual(Profile::class);
    expect(get_class($uncachedResult->first()))->toEqual(UncachedProfile::class);
    expect($result)->not->toBeEmpty();
    expect($cachedResult)->not->toBeEmpty();
    expect($uncachedResult)->not->toBeEmpty();
});
