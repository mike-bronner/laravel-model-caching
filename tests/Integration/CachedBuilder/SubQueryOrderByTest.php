<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPublisher;
use Illuminate\Database\Eloquent\Collection;

test('order by desc', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-publisher_id_in_11_12_13_14_15_orderBy_(select \"name\" from \"publishers\" where \"id\" = \"books\".\"publisher_id\" limit 1)_desc");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    /** @var Collection $publishers */
    $publishers = UncachedPublisher::factory()->count(5)->create();

    $publishers->each(function (UncachedPublisher $publisher) {
        UncachedBook::factory()->count(2)->create(['publisher_id' => $publisher->id]);
    });

    $publisherIds = $publishers->pluck('id')->toArray();

    $books = Book::whereIn('publisher_id', $publisherIds)->orderByDesc(
        Publisher::select('name')
        ->whereColumn('id', 'books.publisher_id')
        ->limit(1)
    ) ->get()->pluck('id')->filter()->toArray();

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    $liveResults = UncachedBook::whereIn('publisher_id', $publisherIds)->orderByDesc(
        UncachedPublisher::select('name')
        ->whereColumn('id', 'books.publisher_id')
        ->limit(1)
    )->get()->pluck('id')->filter()->toArray();

    expect($books)->toHaveCount(10);
    expect($books)->toBe($liveResults);
    expect($cachedResults->pluck('id')->filter()->toArray())->toBe($liveResults);
});
