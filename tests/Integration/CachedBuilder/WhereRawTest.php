<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('raw where clause parsing', function () {
    $authors = collect([(new Author)
        ->whereRaw('name <> \'\'')
        ->first()]);

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_<>_''-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = collect([$this->cache()->tags($tags)->get($key)['value']]);

    $liveResults = collect([(new UncachedAuthor)
        ->whereRaw('name <> \'\'')->first()]);

    expect($authors->diffKeys($cachedResults)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($cachedResults)->isEmpty())->toBeTrue();
});

test('where raw with query parameters', function () {
    $authorName = (new Author)->first()->name;
    $authors = (new Author)
        ->where("name", "!=", "test")
        ->whereRaw("name != 'test3'")
        ->whereRaw('name = ? AND name != ?', [$authorName, "test2"])
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_!=_test-name_!=_'test3'-name_=_" . str_replace(" ", "_", $authorName) . "_AND_name_!=_test2-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = collect([$this->cache()->tags($tags)->get($key)['value']]);
    $liveResults = (new UncachedAuthor)
        ->where("name", "!=", "test")
        ->whereRaw("name != 'test3'")
        ->whereRaw('name = ? AND name != ?', [$authorName, "test2"])
        ->get();

    expect($authors->diffKeys($cachedResults)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($cachedResults)->isEmpty())->toBeTrue();
});

test('multiple where raw cache uniquely', function () {
    $book1 = (new UncachedBook)->first();
    $book2 = (new UncachedBook)->orderBy("id", "DESC")->first();
    $cachedBook1 = (new Book)->whereRaw('title = ?', [$book1->title])->first();
    $cachedBook2 = (new Book)->whereRaw('title = ?', [$book2->title])->first();

    expect($book1->title)->toEqual($cachedBook1->title);
    expect($book2->title)->toEqual($cachedBook2->title);
});

test('nested where raw clauses', function () {
    $expectedIds = [
        1,
        2,
        3,
        5,
        6,
    ];

    $authors = (new Author)
        ->where(function ($query) {
            $query->orWhereRaw("id BETWEEN 1 AND 3")
                ->orWhereRaw("id BETWEEN 5 AND 6");
        })
        ->get();

    expect($authors->pluck("id")->toArray())->toEqual($expectedIds);
});

test('nested where raw with bindings', function () {
    $books = (new Book)
        ->where(function ($query) {
            $query->whereRaw("title like ? or description like ? or published_at like ? or price like ?", ['%larravel%', '%larravel%', '%larravel%', '%larravel%',]);
        })->get();

    $uncachedBooks = (new UncachedBook)
        ->where(function ($query) {
            $query->whereRaw("title like ? or description like ? or published_at like ? or price like ?", ['%larravel%', '%larravel%', '%larravel%', '%larravel%',]);
        })->get();

    expect($uncachedBooks->pluck("id"))->toEqual($books->pluck("id"));
});

test('where raw parameters cache uniquely', function () {
    $book1 = (new UncachedBook)->first();
    $book2 = (new UncachedBook)->orderBy("id", "DESC")->first();

    $result1 = (new Book)
        ->whereRaw("id = ?", [$book1->id])
        ->get();
    $result2 = (new Book)
        ->whereRaw("id = ?", [$book2->id])
        ->get();
    $key1 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_=_{$book1->id}");
    $key2 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_=_{$book2->id}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $cachedBook1 = $this->cache()->tags($tags)->get($key1)['value'];
    $cachedBook2 = $this->cache()->tags($tags)->get($key2)['value'];

    expect($result1->first()->title)->toEqual($cachedBook1->first()->title);
    expect($result2->first()->title)->toEqual($cachedBook2->first()->title);
});

test('where raw parameters after where clause', function () {
    $book1 = (new UncachedBook)->first();
    $book2 = (new UncachedBook)->orderBy("id", "DESC")->first();

    $result1 = (new Book)
        ->where("id", ">", 0)
        ->whereRaw("id = ?", [$book1->id])
        ->get();
    $result2 = (new Book)
        ->where("id", ">", 1)
        ->whereRaw("id = ?", [$book2->id])
        ->get();
    $key1 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_>_0-id_=_{$book1->id}");
    $key2 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_>_1-id_=_{$book2->id}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $cachedBook1 = $this->cache()->tags($tags)->get($key1)['value'];
    $cachedBook2 = $this->cache()->tags($tags)->get($key2)['value'];

    expect($result1->first()->title)->toEqual($cachedBook1->first()->title);
    expect($result2->first()->title)->toEqual($cachedBook2->first()->title);
});
