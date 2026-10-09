<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CacheTagsWithOverriddenConnectionName;

// getConnectionName() and getDatabaseName() are protected on a published trait,
// so overriding them is a supported way to change the connection and database
// segments of every prefix a tag builder writes. These pin that the override is
// still consulted, and that it reaches only the model it belongs to.

// The querying model's own tags take the override. Reading the connection
// off the model instead would spell them "testing:", and the override would
// be dead code that still looks live.
test('an overridden connection name reaches the querying models tags', function () {
    $model = new Author;

    expect(tagsFor($model, $model->newQuery()))->toEqual([
            connectionPrefix("overridden-connection")
                . "genealabslaravelmodelcachingtestsfixturesauthor",
            connectionPrefix("overridden-connection") . "authors",
        ]);
});

// A related model is a different object, so it keeps its own prefix rather
// than inheriting the override. Its write flushes "testing:books", and a
// tag reading "overridden-connection:books" is one nothing ever flushes.
test('an overridden connection name does not reach a related models tag', function () {
    $model = new Author;

    expect(tagsFor($model, $model->has("books", ">", 1)))->toEqual([
            connectionPrefix("overridden-connection")
                . "genealabslaravelmodelcachingtestsfixturesauthor",
            connectionPrefix("overridden-connection") . "authors",
            connectionPrefix("testing") . "books",
        ]);
});

function tagsFor($model, $query): array
{
    return (new CacheTagsWithOverriddenConnectionName([], $model, $query))
        ->make();
}

function connectionPrefix(string $connection): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:{$connection}:"
        . "{$testingSqlitePath}testing.sqlite:";
}
