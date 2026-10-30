<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTtlRelations;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;

// Every expected value below was captured from the code before #661, not generated from the
// code it now checks. Only the sqlite path, which differs per checkout, is filled in at run time.

test('a query with no eager loads keeps its key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Book::query()))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook",
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('a single eager load keeps its key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Book::with("author")))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
            . relatedConnectionSegment("author"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("genealabslaravelmodelcachingtestsfixturesauthor"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('two sibling eager loads keep their key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Book::with(["author", "publisher"])))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
            . relatedConnectionSegment("author")
            . relatedConnectionSegment("publisher"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("genealabslaravelmodelcachingtestsfixturesauthor"),
            tag("genealabslaravelmodelcachingtestsfixturespublisher"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('a nested eager load keeps its key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Book::with("author.books")))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
            . relatedConnectionSegment("author")
            . "-author.books",
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("genealabslaravelmodelcachingtestsfixturesauthor"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('a morph to eager load keeps its key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Comment::with("commentable")))->toBe([
        "key" => tagPrefix() . "comments:genealabslaravelmodelcachingtestsfixturescomment"
            . relatedConnectionSegment("commentable"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturescomment"),
            tag("genealabslaravelmodelcachingtestsfixturespost"),
            tag("genealabslaravelmodelcachingtestsfixturesuncachedpost"),
            tag("comments"),
        ],
        "ttl" => null,
    ]);
});

test('a belongs to many eager load keeps its key, pivot tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Book::with("stores")))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
            . relatedConnectionSegment("stores"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("genealabslaravelmodelcachingtestsfixturesstore"),
            tag("books"),
            tag("book-store"),
        ],
        "ttl" => null,
    ]);
});

test('a has many through eager load keeps its key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Author::with("printers")))->toBe([
        "key" => tagPrefix() . "authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"
            . relatedConnectionSegment("printers"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesauthor"),
            tag("genealabslaravelmodelcachingtestsfixturesprinter"),
            tag("authors"),
        ],
        "ttl" => null,
    ]);
});

test('an eager load of a relation registered with resolve relation using keeps its key, tags and ttl', function () {
    Book::resolveRelationUsing(
        "contractResolvedPublisher",
        fn (Book $book) => $book->belongsTo(Publisher::class, "publisher_id"),
    );

    expect(cacheKeyTagsAndTtl(Book::with("contractResolvedPublisher")))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook-contractResolvedPublisher",
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("genealabslaravelmodelcachingtestsfixturespublisher"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('an eager load name that is not a relation method keeps its key, tags and ttl', function () {
    expect(cacheKeyTagsAndTtl(Book::with("notARelation")))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook-notARelation",
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('an eager load with a constraint closure keeps its key, tags and ttl', function () {
    $query = Book::with(["author" => fn ($query) => $query->where("name", "like", "A%")]);

    expect(cacheKeyTagsAndTtl($query))->toBe([
        "key" => tagPrefix() . "books:genealabslaravelmodelcachingtestsfixturesbook"
            . relatedConnectionSegment("author")
            . "=ffc0e975741af4eec79648d68139a75b216c14d9",
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("genealabslaravelmodelcachingtestsfixturesauthor"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('a model ttl taken from a nested eager load is kept, with its key and tags', function () {
    $query = (new AuthorWithTtlRelations)->with("booksWithPublisherWithTtl.publisher");

    expect(cacheKeyTagsAndTtl($query))->toBe([
        "key" => tagPrefix() . "authors:genealabslaravelmodelcachingtestsfixturesauthorwithttlrelations-authors.deleted_at_null"
            . relatedConnectionSegment("booksWithPublisherWithTtl")
            . "-booksWithPublisherWithTtl.publisher",
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesauthorwithttlrelations"),
            tag("genealabslaravelmodelcachingtestsfixturesbookwithpublisherwithttl"),
            tag("genealabslaravelmodelcachingtestsfixturespublisherwithttl"),
            tag("authors"),
        ],
        "ttl" => 20,
    ]);
});

test('a belongs to many relation query with an eager load keeps its key and tags', function () {
    $relation = (new Book)->forceFill(["id" => 1])->stores()->with("books");
    $eagerLoadsKey = tagPrefix() . "stores:genealabslaravelmodelcachingtestsfixturesstore"
        . relatedConnectionSegment("books");

    expect((new ReflectionMethod($relation, "cacheEagerLoads"))->invoke($relation))
        ->toHaveKey("books");
    expect(cacheKeyTagsAndTtl($relation))->toBe([
        "key" => tagPrefix() . "stores:genealabslaravelmodelcachingcachedbelongstomany"
            . "-join_inner_book_store_f6999d9f3303-book_store.book_id_=_1-with_" . sha1($eagerLoadsKey),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesstore"),
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("stores"),
            tag("book-store"),
        ],
        "ttl" => null,
    ]);
});

test('a has many through relation query with an eager load keeps its key and tags', function () {
    $relation = (new Author)->forceFill(["id" => 1])->printers()->with("book");

    expect((new ReflectionMethod($relation, "cacheEagerLoads"))->invoke($relation))
        ->toHaveKey("book");
    expect(cacheKeyTagsAndTtl($relation))->toBe([
        "key" => tagPrefix() . "printers:genealabslaravelmodelcachingtestsfixturesprinter"
            . "-join_inner_books_bca1a55e5c9c-books.author_id_=_1"
            . relatedConnectionSegment("book"),
        "tags" => [
            tag("genealabslaravelmodelcachingtestsfixturesprinter"),
            tag("genealabslaravelmodelcachingtestsfixturesbook"),
            tag("printers"),
            tag("books"),
        ],
        "ttl" => null,
    ]);
});

test('a cached builder still reports its eager loads', function () {
    $query = Book::with("author");

    expect((new ReflectionMethod($query, "cacheEagerLoads"))->invoke($query))
        ->toHaveKey("author");
});
