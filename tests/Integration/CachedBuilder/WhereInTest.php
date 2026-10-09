<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Query\Expression;

test('where in using collection query', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-author_id_in_1_2_3_4");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $authors = (new UncachedAuthor)
        ->where("id", "<", 5)
        ->get(["id"]);

    $books = (new Book)
        ->whereIn("author_id", $authors)
        ->get();
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedBook)
        ->whereIn("author_id", $authors)
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where in when set is empty', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_in_-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $authors = (new Author)
        ->whereIn("id", [])
        ->get();
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereIn("id", [])
        ->get();

    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('bindings are correct with multiple where in clauses', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_in_John-id_in_-name_in_Mike-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $authors = (new Author)
        ->whereIn("name", ["John"])
        ->whereIn("id", [])
        ->whereIn("name", ["Mike"])
        ->get();
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereIn("name", ["Mike"])
        ->whereIn("id", [])
        ->whereIn("name", ["John"])
        ->get();

    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where in uses correct bindings', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_in_1_2_3_4_5-id_between_1_99999-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->whereIn('id', [1,2,3,4,5])
        ->whereBetween('id', [1, 99999])
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereIn('id', [1,2,3,4,5])
        ->whereBetween('id', [1, 99999])
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('where in with percent character in value does not throw', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_in_10%25_20%25-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->whereIn('name', ['10%', '20%'])
        ->get();
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereIn('name', ['10%', '20%'])
        ->get();

    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where in with subquery containing single where clause', function () {
    $books = (new Book)
        ->whereIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "John");
        })
        ->get();

    $liveResults = (new UncachedBook)
        ->whereIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "John");
        })
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where in with subquery containing multiple where clauses', function () {
    $books = (new Book)
        ->whereIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "John")
                ->where("id", ">", 0);
        })
        ->get();

    $liveResults = (new UncachedBook)
        ->whereIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "John")
                ->where("id", ">", 0);
        })
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('nested where not in with subquery does not crash with uuid exception', function () {
    $books = (new Book)
        ->whereNotIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "John");
        })
        ->whereNotIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "Mike");
        })
        ->get();

    $liveResults = (new UncachedBook)
        ->whereNotIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "John");
        })
        ->whereNotIn("author_id", function ($query) {
            $query->select("id")
                ->from("authors")
                ->where("name", "=", "Mike");
        })
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where in with non uuid string values skips from bytes', function () {
    $books = (new Book)
        ->whereIn("author_id", function ($query) {
            $query->selectRaw("distinct id")
                ->from("authors");
        })
        ->get();

    $liveResults = (new UncachedBook)
        ->whereIn("author_id", function ($query) {
            $query->selectRaw("distinct id")
                ->from("authors");
        })
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where in with expression value does not collide across different queries', function () {
    $author = Author::factory()->create(["name" => "Alice", "email" => "shared@example.com"]);
    Author::factory()->create(["name" => "Bob", "email" => "shared@example.com"]);

    $resultsForAlice = (new Author)
        ->whereIn("id", [$author->id, new Expression((string) $author->id)])
        ->where("name", "Alice")
        ->where("email", "shared@example.com")
        ->get();
    $resultsForBob = (new Author)
        ->whereIn("id", [$author->id, new Expression((string) $author->id)])
        ->where("name", "Bob")
        ->where("email", "shared@example.com")
        ->get();

    expect($resultsForAlice->pluck("name")->toArray())->toEqual(["Alice"]);
    expect($resultsForBob->pluck("name")->toArray())->toEqual([]);
});

test('where not in with no op subquery does not collide across different queries', function () {
    Author::factory()->create(["name" => "Alice", "email" => "shared@example.com"]);
    Author::factory()->create(["name" => "Bob", "email" => "shared@example.com"]);

    $noOpExclusion = function ($query) {
        $query->select("id")->from("authors")->whereRaw("1 = 0");
    };

    $resultsForAlice = (new Author)
        ->whereNotIn("id", $noOpExclusion)
        ->where("name", "Alice")
        ->where("email", "shared@example.com")
        ->get();
    $resultsForBob = (new Author)
        ->whereNotIn("id", $noOpExclusion)
        ->where("name", "Bob")
        ->where("email", "shared@example.com")
        ->get();

    expect($resultsForAlice->pluck("name")->toArray())->toEqual(["Alice"]);
    expect($resultsForBob->pluck("name")->toArray())->toEqual(["Bob"]);
});
