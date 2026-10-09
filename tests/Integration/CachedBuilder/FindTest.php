<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('find model results creates cache', function () {
    $author = collect()->push((new Author)->find(1));
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = collect()->push($this->cache()->tags($tags)
        ->get($key));
    $liveResults = collect()->push((new UncachedAuthor)->find(1));

    expect($author->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('find multiple model results creates cache', function () {
    $authors = (new Author)
        ->find([1, 2, 3]);
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-find_list_1_2_3");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)["value"];
    $liveResults = (new UncachedAuthor)->find([1, 2, 3]);

    expect($cachedResults->pluck("id"))->toEqual($authors->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('subsequent finds return different models', function () {
    $author1 = (new Author)->find(1);
    $author2 = (new Author)->find(2);

    expect($author2)->not->toEqual($author1);
    expect(1)->toEqual($author1->id);
    expect(2)->toEqual($author2->id);
});

test('find with array returns results', function () {
    $author = (new Author)->find([1, 2]);
    $uncachedAuthor = (new UncachedAuthor)->find([1, 2]);

    expect($author->count())->toEqual($uncachedAuthor->count());
    expect($author->pluck("id"))->toEqual($uncachedAuthor->pluck("id"));
});

test('find with single element array doesnt conflict with normal find', function () {
    $author1 = (new Author)
        ->find(1);
    $author2 = (new Author)
        ->find([1]);
    
    expect($author2)->not->toEqual($author1);
    expect($author2)->toBeIterable();
    expect(get_class($author1))->toEqual(Author::class);
});
