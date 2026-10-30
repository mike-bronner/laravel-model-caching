<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithSwitchingRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithThrowingRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\EagerLoadCountingAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\EagerLoadCountingBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\EagerLoadCountingComment;

test('a cached read resolves a single eager load once per model instance', function () {
    $book = new EagerLoadCountingBook;
    $calls = relationCallsOnCacheHit(fn () => $book->newQuery()->with("author")->get());

    expect($calls[$book])->toBe(["author" => 1]);
    expect(mostRelationCallsOnOneInstance($calls))->toBe(1);
});

test('a cached read resolves sibling eager loads once per model instance', function () {
    $book = new EagerLoadCountingBook;
    $calls = relationCallsOnCacheHit(fn () => $book->newQuery()->with(["author", "publisher"])->get());

    expect($calls[$book])->toBe(["author" => 1, "publisher" => 1]);
    expect(mostRelationCallsOnOneInstance($calls))->toBe(1);
});

test('a cached read resolves each relation of a nested eager load once per model instance', function () {
    $book = new EagerLoadCountingBook;
    $calls = relationCallsOnCacheHit(fn () => $book->newQuery()->with("author.books")->get());

    expect($calls[$book])->toBe(["author" => 1]);
    expect(relationCallsOn($calls, EagerLoadCountingAuthor::class, "books"))->not->toBeEmpty();
    expect(mostRelationCallsOnOneInstance($calls))->toBe(1);
});

test('a cached read resolves a morph to eager load once per model instance', function () {
    $comment = new EagerLoadCountingComment;
    $calls = relationCallsOnCacheHit(fn () => $comment->newQuery()->with("commentable")->get());

    expect($calls[$comment])->toBe(["commentable" => 1]);
    expect(mostRelationCallsOnOneInstance($calls))->toBe(1);
});

test('a cached read resolves a constrained eager load once more only for its constraint key', function () {
    $book = new EagerLoadCountingBook;
    $calls = relationCallsOnCacheHit(fn () => $book->newQuery()
        ->with(["author" => fn ($query) => $query->where("name", "like", "A%")])
        ->get());

    expect($calls[$book])->toBe(["author" => 1]);
    expect(mostRelationCallsOnOneInstance($calls))->toBe(1);
});

test('a resolved relation does not carry over to the next read on the same model instance', function () {
    $book = BookWithSwitchingRelation::first();
    $book->newQuery()->with("switching")->get();
    $book->setAttribute("switch_to_publisher", true);
    $this->cache()->flush();
    $book->newQuery()->with("switching")->get();
    $read = fn () => $book->newQuery()->with("switching")->get();

    expect(databaseQueriesRunBy($read))->toBe(0);

    $this->cache()->tags([tag("genealabslaravelmodelcachingtestsfixturespublisher")])->flush();

    expect(databaseQueriesRunBy($read))->toBeGreaterThan(0);
});

test('a resolved relation does not carry over to the next read on the same builder', function () {
    $book = BookWithSwitchingRelation::first();
    $query = $book->newQuery()->with("switching");
    $query->get();
    $book->setAttribute("switch_to_publisher", true);
    $this->cache()->flush();
    $query->get();
    $read = fn () => $book->newQuery()->with("switching")->get();

    expect(databaseQueriesRunBy($read))->toBe(0);

    $this->cache()->tags([tag("genealabslaravelmodelcachingtestsfixturespublisher")])->flush();

    expect(databaseQueriesRunBy($read))->toBeGreaterThan(0);
});

test('a cached read keeps no model alive after the caller drops it', function () {
    $book = Book::first();
    $reference = WeakReference::create($book);
    $book->newQuery()->with("author")->get();

    unset($book);
    gc_collect_cycles();

    expect($reference->get())->toBeNull();
});

test('a constraint closure does not leak into the tags or ttl of its query', function () {
    $constrained = cacheKeyTagsAndTtl(
        Book::with(["author" => fn ($query) => $query->where("name", "like", "A%")]),
    );
    $plain = cacheKeyTagsAndTtl(Book::with("author"));

    expect($constrained["tags"])->toBe($plain["tags"]);
    expect($constrained["ttl"])->toBe($plain["ttl"]);
    expect($constrained["key"])->not->toBe($plain["key"]);
});

test('a relation method that throws throws again on the next read of the same instance', function () {
    $book = BookWithThrowingRelation::first();
    BookWithThrowingRelation::$calls = 0;

    expect(fn () => $book->newQuery()->with("throwing")->get())
        ->toThrow(RuntimeException::class, "The throwing relation failed.");
    $callsAfterFirstRead = BookWithThrowingRelation::$calls;

    expect(fn () => $book->newQuery()->with("throwing")->get())
        ->toThrow(RuntimeException::class, "The throwing relation failed.");
    expect($callsAfterFirstRead)->toBeGreaterThan(0);
    expect(BookWithThrowingRelation::$calls)->toBe($callsAfterFirstRead * 2);
});
