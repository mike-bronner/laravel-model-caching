<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

beforeEach(function () {
    (new Book)
        ->get()
        ->each(function ($book) {
            $book->image()
                ->create([
                    "path" => $this->faker->url(),
                ]);
        });
    $this->cache()->flush();
});

test('morph to', function () {
    $key1 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-author_id_=_1-testing:{$this->testingSqlitePath}testing.sqlite:image");
    $key2 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-author_id_=_4-testing:{$this->testingSqlitePath}testing.sqlite:image");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesimage",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $books1 = (new Book)
        ->with("image")
        ->where("author_id", 1)
        ->get();
    $cachedResults1 = $this->cache()
        ->tags($tags)
        ->get($key1)['value'];
    $books2 = (new Book)
        ->with("image")
        ->where("author_id", 4)
        ->get();
    $cachedResults2 = $this->cache()
        ->tags($tags)
        ->get($key2)['value'];

    expect($books1->pluck("image.id"))->toEqual($cachedResults1->pluck("image.id"));
    expect($books2->pluck("image.id"))->toEqual($cachedResults2->pluck("image.id"));
    expect($cachedResults2->pluck("image.id"))->not->toEqual($cachedResults1->pluck("image.id"));
    expect($books2->pluck("image.id"))->not->toEqual($books1->pluck("image.id"));
    expect($books1->first()->image)->not->toBeNull();
    expect($books2->first()->image)->not->toBeNull();
    expect($cachedResults1->first()->image)->not->toBeNull();
    expect($cachedResults2->first()->image)->not->toBeNull();
});
