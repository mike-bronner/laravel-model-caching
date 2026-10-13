<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('reorder with no arguments clears order and produces different cache key', function () {
    $orderedAuthors = (new Author)
        ->orderBy('name')
        ->get();

    $orderedKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:"
        . "authors:genealabslaravelmodelcachingtestsfixturesauthor"
        . "-authors.deleted_at_null_orderBy_name_asc"
    );

    $reorderedAuthors = (new Author)
        ->orderBy('name')
        ->reorder()
        ->get();

    $reorderedKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:"
        . "authors:genealabslaravelmodelcachingtestsfixturesauthor"
        . "-authors.deleted_at_null"
    );

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedOrderedResults = $this->cache()->tags($tags)->get($orderedKey)['value'];
    $cachedReorderedResults = $this->cache()->tags($tags)->get($reorderedKey)['value'];

    $liveResults = (new UncachedAuthor)->orderBy('name')->reorder()->get();

    expect($reorderedKey)->not->toEqual($orderedKey);
    expect($reorderedAuthors->diffKeys($cachedReorderedResults))->toBeEmpty();
    expect($liveResults->diffKeys($reorderedAuthors))->toBeEmpty();
});

test('reorder with column produces different cache key from order by', function () {
    $orderedAuthors = (new Author)
        ->orderBy('name', 'asc')
        ->get();

    $orderedKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:"
        . "authors:genealabslaravelmodelcachingtestsfixturesauthor"
        . "-authors.deleted_at_null_orderBy_name_asc"
    );

    $reorderedAuthors = (new Author)
        ->orderBy('id', 'desc')
        ->reorder('name', 'asc')
        ->get();

    $reorderedKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:"
        . "authors:genealabslaravelmodelcachingtestsfixturesauthor"
        . "-authors.deleted_at_null_orderBy_name_asc"
    );

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()->tags($tags)->get($reorderedKey)['value'];
    $liveResults = (new UncachedAuthor)->orderBy('id', 'desc')->reorder('name', 'asc')->get();

    expect($reorderedKey)->toEqual($orderedKey);
    expect($reorderedAuthors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($reorderedAuthors))->toBeEmpty();
});

test('reorder does not return stale cached results', function () {
    $orderedAuthors = (new Author)
        ->orderBy('name', 'desc')
        ->get();

    $reorderedAuthors = (new Author)
        ->orderBy('name', 'desc')
        ->reorder()
        ->get();

    $liveOrderedResults = (new UncachedAuthor)->orderBy('name', 'desc')->get();
    $liveReorderedResults = (new UncachedAuthor)->orderBy('name', 'desc')->reorder()->get();

    expect($orderedAuthors->pluck('id')->toArray())->toEqual(
        $liveOrderedResults->pluck('id')->toArray(),
    );

    expect($reorderedAuthors->pluck('id')->toArray())->toEqual(
        $liveReorderedResults->pluck('id')->toArray(),
    );
});

test('multiple reorder calls produce correct results', function () {
    $authors = (new Author)
        ->orderBy('id', 'desc')
        ->reorder('name', 'asc')
        ->reorder('name', 'desc')
        ->get();

    $liveResults = (new UncachedAuthor)
        ->orderBy('id', 'desc')
        ->reorder('name', 'asc')
        ->reorder('name', 'desc')
        ->get();

    $expectedKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:"
        . "authors:genealabslaravelmodelcachingtestsfixturesauthor"
        . "-authors.deleted_at_null_orderBy_name_desc"
    );

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()->tags($tags)->get($expectedKey)['value'];

    expect($authors->pluck('id')->toArray())->toEqual($liveResults->pluck('id')->toArray());
    expect($authors->diffKeys($cachedResults))->toBeEmpty();
});
