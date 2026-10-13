<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;

test('force delete clears cache', function () {
    $author = (new Author)
        ->where("id", 1)
        ->get();

    $resultsBefore = $this
        ->cache()
        ->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_=_1-authors.deleted_at_null"
        ))["value"];

    (new Author)
        ->where("id", 1)
        ->forceDelete();
    $resultsAfter = $this
        ->cache()
        ->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_=_1"
        ))["value"]
        ?? null;

    expect(get_class($author))->toEqual(get_class($resultsBefore));
    expect($resultsBefore)->not->toBeNull();
    expect($resultsAfter)->toBeNull();
});
