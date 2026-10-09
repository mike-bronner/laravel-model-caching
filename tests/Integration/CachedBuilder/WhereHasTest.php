<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithUncachedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('where has clause', function () {
    $authors = (new Author)
        ->whereHas("books")
        ->get();
    $uncachedAuthors = (new UncachedAuthor)
        ->whereHas("books")
        ->get();

    expect($uncachedAuthors->pluck("id"))->toEqual($authors->pluck("id"));
});

test('counted where has clause', function () {
    $authors = (new Author)
        ->whereHas("books", null, ">=", 2)
        ->get();
    $uncachedAuthors = (new UncachedAuthor)
        ->whereHas("books", null, ">=", 2)
        ->get();

    expect($uncachedAuthors->pluck("id"))->toEqual($authors->pluck("id"));
});

test('nested where has clauses', function () {
    $authors = (new Author)
        ->where("id", ">", 0)
        ->whereHas("books", function ($query) {
            $query->whereNull("description");
        })
        ->get();
    $uncachedAuthors = (new UncachedAuthor)
        ->where("id", ">", 0)
        ->whereHas("books", function ($query) {
            $query->whereNull("description");
        })
        ->get();

    expect($uncachedAuthors->pluck("id"))->toEqual($authors->pluck("id"));
});

test('non cached relationship prevents caching', function () {
    $book = (new BookWithUncachedStore)
        ->with("uncachedStores")
        ->whereHas("uncachedStores")
        ->get()
        ->first();
    $store = $book->uncachedStores->first();
    $store->name = "Waterstones";
    $store->save();
    $results = $this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesuncachedstore",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-exists-" .
            "and_books.id_=_book_store.book_id-testing:{$this->testingSqlitePath}testing.sqlite:uncachedStores"
        ));

    expect($results)->toBeNull();
});

test('where has cache is busted when related table is written', function () {
    $author = Author::factory()->create(["name" => "John"]);
    Book::factory()->create(["author_id" => $author->id]);

    assertCachedUnderTags(
        (new Book)->whereHas("author", function ($query) {
            $query->where("name", "John");
        }),
        [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("books"),
            tag("authors"),
        ],
    );

    $query = fn () => (new Book)
        ->whereHas("author", function ($query) {
            $query->where("name", "John");
        })
        ->get();

    expect($query())->not->toBeEmpty();

    $author->name = "Jane";
    $author->save();

    expect($query())->toBeEmpty();
});

test('nested where has cache is busted when deeply related table is written', function () {
    $author = Author::factory()->create(["name" => "John"]);
    $profile = Profile::factory()->create(["author_id" => $author->id, "first_name" => "Alpha"]);
    Book::factory()->create(["author_id" => $author->id]);

    // The `profiles` tag is the whole point: it is two relations away from
    // the queried model, and only the recursive walk reaches it.
    assertCachedUnderTags(
        (new Book)->whereHas("author", function ($query) {
            $query->whereHas("profile", function ($query) {
                $query->where("first_name", "Alpha");
            });
        }),
        [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("books"),
            tag("authors"),
            tag("profiles"),
        ],
    );

    $query = fn () => (new Book)
        ->whereHas("author", function ($query) {
            $query->whereHas("profile", function ($query) {
                $query->where("first_name", "Alpha");
            });
        })
        ->get();

    expect($query())->not->toBeEmpty();

    $profile->first_name = "Beta";
    $profile->save();

    expect($query())->toBeEmpty();
});

function tag(string $name): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:{$name}";
}
