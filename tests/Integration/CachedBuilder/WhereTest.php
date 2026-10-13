<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('with query', function () {
    $books = (new Book)
        ->where(function ($query) {
            $query->where("id", ">", "1")
                ->where("id", "<", "5");
        })
        ->get();
    $uncachedBooks = (new UncachedBook)
        ->where(function ($query) {
            $query->where("id", ">", "1")
                ->where("id", "<", "5");
        })
        ->get();

    expect($uncachedBooks->pluck("id"))->toEqual($books->pluck("id"));
});

test('columns relationship where clause parsing', function () {
    $author = (new Author)
        ->orderBy('name')
        ->first();
    $authors = (new Author)
        ->where('name', '=', $author->name)
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_=_{$author->name}-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->where('name', '=', $author->name)
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('where clause parsing of operators', function () {
    processWhereClauseTestWithOperator('=');
    processWhereClauseTestWithOperator('!=');
    processWhereClauseTestWithOperator('<>');
    processWhereClauseTestWithOperator('>');
    processWhereClauseTestWithOperator('<');
    processWhereClauseTestWithOperator('LIKE');
    processWhereClauseTestWithOperator('NOT LIKE');
});

test('two where clauses after each other', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_>_0-id_<_100-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->where("id", ">", 0)
        ->where("id", "<", 100)
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->where("id", ">", 0)
        ->where("id", "<", 100)
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('where uses correct binding', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-nested-name_like_B%25-or-name_like_G%25-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->where("name", "LIKE", "A%")
        ->orWhere("name", "LIKE", "D%")
        ->get();
    $authors = (new Author)
        ->where("name", "LIKE", "B%")
        ->orWhere("name", "LIKE", "G%")
        ->get();
    $cachedResults = collect($this->cache()
        ->tags($tags)
        ->get($key)['value']);
    $liveResults = (new UncachedAuthor)
        ->where("name", "LIKE", "B%")
        ->orWhere("name", "LIKE", "G%")
        ->get();

    expect($authors->toArray())->toEqual($liveResults->toArray());
    expect($cachedResults->toArray())->toEqual($liveResults->toArray());
});

function processWhereClauseTestWithOperator(string $operator)
{
    $testingSqlitePath = test()->testingSqlitePath;

    $author = (new Author)->first();
    $authors = (new Author)
        ->where('name', $operator, $author->name)
        ->get();
    $keyParts = [
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name",
        '_',
        str_replace(' ', '_', strtolower($operator)),
        '_',
        $author->name,
        "-authors.deleted_at_null"
    ];
    $key = sha1(implode('', $keyParts));
    $tags = [
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = test()->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->where('name', $operator, $author->name)
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
}
