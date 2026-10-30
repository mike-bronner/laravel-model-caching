<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookRecordingAttributeLookups;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithEagerLoadAccessor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithEagerLoadRelation;

beforeEach(function () {
    BookRecordingAttributeLookups::$lookups = [];
    BookRecordingAttributeLookups::$afterQueryCallbackReads = 0;
});

test('is cachable on a model never looks up an eager load attribute', function () {
    (new BookRecordingAttributeLookups)->isCachable();

    expect(BookRecordingAttributeLookups::$lookups)->not->toContain("eagerLoad");
});

test('building a query from a model never looks up an eager load attribute', function () {
    BookRecordingAttributeLookups::query();
    (new BookRecordingAttributeLookups)->newQuery();
    (new BookRecordingAttributeLookups)->forceFill(["id" => 1])->newQuery();

    expect(BookRecordingAttributeLookups::$lookups)->not->toContain("eagerLoad");
});

test('a model with a database attribute named eager load runs a cached query', function () {
    $book = Book::selectRaw("books.*, 'from the database' as eagerLoad")->first();

    expect($book->getAttributes())->toHaveKey("eagerLoad");
    expect($book->newQuery()->with("author")->get())->not->toBeEmpty();
});

test('a model with an accessor named eager load runs a cached query', function () {
    expect(BookWithEagerLoadAccessor::with("author")->get())->not->toBeEmpty();
    expect(BookWithEagerLoadAccessor::first()->newQuery()->get())->not->toBeEmpty();
});

test('a model with a relation method named eager load runs a cached query', function () {
    $book = BookWithEagerLoadRelation::first();

    expect($book->newQuery()->get())->not->toBeEmpty();
    expect(BookWithEagerLoadRelation::with("eagerLoad")->get()->first()->eagerLoad)
        ->not->toBeNull();
});

test('is cachable on a model with no query follows the enabled config and its own flag', function (bool $enabled, bool $flag) {
    config(["laravel-model-caching.enabled" => $enabled]);
    $model = new BookRecordingAttributeLookups;

    if (! $flag) {
        $model->disableModelCaching();
    }

    expect($model->isCachable())->toBe($enabled && $flag);
    expect(BookRecordingAttributeLookups::$afterQueryCallbackReads)->toBe(0);
})->with([
    "enabled, cachable" => [true, true],
    "enabled, not cachable" => [true, false],
    "disabled, cachable" => [false, true],
    "disabled, not cachable" => [false, false],
]);

test('an after query callback on a relation eloquent builder still makes it uncachable', function () {
    $relation = (new Book)->forceFill(["id" => 1])->stores();
    $relation->getQuery()->afterQuery(fn ($stores) => $stores);

    expect($relation->isCachable())->toBeFalse();
});

test('a base query after query callback still makes the builder uncachable', function () {
    $query = Book::query();
    $query->getQuery()->afterQuery(fn ($rows) => $rows);

    expect($query->isCachable())->toBeFalse();
});
