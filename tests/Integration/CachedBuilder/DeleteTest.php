<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('decrementing invalidates cache', function () {
    $book = (new Book)
        ->orderBy("id", "DESC")
        ->first();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook_orderBy_id_desc-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $beforeDeleteCachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $book->delete();
    $afterDeleteCachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($book->id)->toEqual($beforeDeleteCachedResults->id);
    expect($afterDeleteCachedResults)->not->toEqual($beforeDeleteCachedResults);
    expect($afterDeleteCachedResults)->toBeNull();
});
