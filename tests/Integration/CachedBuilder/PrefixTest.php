<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;

test('cache prefix is added for prefixed model', function () {
    $prefixKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors:genealabslaravelmodelcachingtestsfixturesprefixedauthor-authors.deleted_at_null-first");
    $prefixTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors",
    ];
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $prefixAuthor = (new PrefixedAuthor)
        ->first();
    $author = (new Author)
        ->first();
    $prefixCachedResults = $this
        ->cache()
        ->tags($prefixTags)
        ->get($prefixKey)['value'];
    $nonPrefixCachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($prefixAuthor)->toEqual($prefixCachedResults);
    expect($author)->toEqual($nonPrefixCachedResults);
    expect($author)->not->toBeNull();
});
