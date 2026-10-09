<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('eagerloaded has many', function () {
    $authorId = (new Author)
        ->disableModelCaching()
        ->whereHas("books")
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_inraw_{$authorId}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $books = (new Author)
        ->with("books")
        ->find($authorId)
        ->books;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->with("books")
        ->find($authorId)
        ->books;

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('lazyloaded has many', function () {
    $authorId = (new Author)
        ->disableModelCaching()
        ->whereHas("books")
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_{$authorId}-books.author_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $books = (new Author)
        ->find($authorId)
        ->books;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->find($authorId)
        ->books;

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});
