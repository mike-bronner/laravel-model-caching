<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('select with raw columns', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook_author_id_AVG(id) AS averageIds-groupBy_author_id_orderBy_author_id_asc");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $selectArray = [
        app("db")->raw("author_id"),
        app("db")->raw("AVG(id) AS averageIds"),
    ];

    $books = (new Book)
        ->select($selectArray)
        ->groupBy("author_id")
        ->orderBy("author_id")
        ->get()
        ->toArray();
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ->toArray();
    $liveResults = (new UncachedBook)
        ->select($selectArray)
        ->groupBy("author_id")
        ->orderBy("author_id")
        ->get()
        ->toArray();

    expect($books)->toEqual($liveResults);
    expect($cachedResults)->toEqual($liveResults);
});

test('select fields are cached', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_id_name-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authorFields = (new Author)
        ->select("id", "name")
        ->first()
        ->getAttributes();
    $uncachedFields = (new UncachedAuthor)
        ->select("id", "name")
        ->first()
        ->getAttributes();
    $cachedFields = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ->getAttributes();

    expect($authorFields)->toEqual($cachedFields);
    expect($uncachedFields)->toEqual($cachedFields);
});

test('add select method on model', function () {
    $column = "(SELECT id FROM authors WHERE id = 1)";
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_(SELECT id FROM authors WHERE id = 1)-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $result = (new Author)
        ->addSelect(app("db")->raw($column))
        ->first();
    $uncachedResult = (new UncachedAuthor)
        ->addSelect(app("db")->raw($column))
        ->first();
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($cachedResult)->toEqual($result);
    expect($cachedResult->getAttributes())->toEqual($uncachedResult?->getAttributes());
});

test('add select method on builder', function () {
    $column = "(SELECT id FROM authors WHERE id = 1)";
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_(SELECT id FROM authors WHERE id = 1)_(SELECT id FROM authors WHERE id = 1)-id_=_1-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $result = (new Author)
        ->where("id", 1)
        ->addSelect(app("db")->raw($column))
        ->addSelect(app("db")->raw($column))
        ->first();
    $uncachedResult = (new UncachedAuthor)
        ->where("id", 1)
        ->addSelect(app("db")->raw($column))
        ->addSelect(app("db")->raw($column))
        ->first();
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($cachedResult)->toEqual($result);
    expect($cachedResult->getAttributes())->toEqual($uncachedResult?->getAttributes());
});
