<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('when query', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_<_5");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $books = (new Book)
        ->when(true, function ($query) {
            $query->where("id", "<", 5);
        })
        ->get();
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedBook)
        ->when(true, function ($query) {
            $query->where("id", "<", 5);
        })
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});
