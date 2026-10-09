<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('with limited query', function () {
    $authors = (new Author)
        ->where("id", 1)
        ->with([
            'books' => function ($query) {
                $query->where("id", "<", 100)
                    ->offset(5)
                    ->limit(1);
            }
        ])
        ->first();
    $uncachedAuthor = (new UncachedAuthor)->with([
            'books' => function ($query) {
                $query->where("id", "<", 100)
                    ->offset(5)
                    ->limit(1);
            }
        ])
        ->first();

    expect($authors->books()->pluck("id"))->toEqual($uncachedAuthor->books()->pluck("id"));
    expect($authors->id)->toEqual($uncachedAuthor->id);
});

test('with query', function () {
    $author = (new Author)
        ->where("id", 1)
        ->with([
            'books' => function ($query) {
                $query->where("id", "<", 100);
            }
        ])
        ->first();
    $uncachedAuthor = (new UncachedAuthor)->with([
            'books' => function ($query) {
                $query->where("id", "<", 100);
            },
        ])
        ->where("id", 1)
        ->first();

    expect($author->books()->count())->toEqual($uncachedAuthor->books()->count());
    expect($author->id)->toEqual($uncachedAuthor->id);
});

test('multi level with query', function () {
    $author = (new Author)
        ->where("id", 1)
        ->with([
            'books.publisher' => function ($query) {
                $query->where("id", "<", 100);
            }
        ])
        ->first();
    $uncachedAuthor = (new UncachedAuthor)->with([
            'books.publisher' => function ($query) {
                $query->where("id", "<", 100);
            },
        ])
        ->where("id", 1)
        ->first();

    expect($author->books()->count())->toEqual($uncachedAuthor->books()->count());
    expect($author->id)->toEqual($uncachedAuthor->id);
});

test('with belongs to many relationship query', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.id_=_3-testing:{$this->testingSqlitePath}testing.sqlite:stores-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $stores = (new Book)
        ->with("stores")
        ->find(3)
        ->stores;
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ->stores;
    $liveResults = (new UncachedBook)
        ->with("stores")
        ->find(3)
        ->stores;

    expect($stores->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});
