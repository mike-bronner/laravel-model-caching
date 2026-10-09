<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPublisher;
use Illuminate\Database\Eloquent\Collection;

test('add select', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook_books.*_(select \"name\" from \"publishers\" where \"id\" = \"books\".\"publisher_id\" order by \"published_at\" desc limit 1) as \"publisher_name\"-publisher_id_in_11_12_13_14_15");
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

    $books = Book::whereIn('publisher_id', $publisherIds)
        ->addSelect(['publisher_name' =>
            Publisher::select('name')
            ->whereColumn('id', 'books.publisher_id')
            ->orderBy('published_at', 'desc')
            ->limit(1)
        ])->get()->pluck('publisher_name')->filter()->toArray();

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    $liveResults = UncachedBook::whereIn('publisher_id', $publisherIds)
        ->addSelect(['publisher_name' =>
            UncachedPublisher::select('name')
            ->whereColumn('id', 'books.publisher_id')
            ->orderBy('published_at', 'desc')
            ->limit(1)
        ])->get()->pluck('publisher_name')->filter()->toArray();

    expect($books)->toHaveCount(10);
    expect($books)->toBe($liveResults);
    expect($cachedResults->pluck('publisher_name')->filter()->toArray())->toBe($liveResults);
});
