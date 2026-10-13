<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CacheTagsWithOverriddenConnectionName;

test('an overridden connection name reaches the querying models tags', function () {
    $model = new Author;

    expect(tagsFor($model, $model->newQuery()))->toEqual([
            connectionPrefix("overridden-connection")
                . "genealabslaravelmodelcachingtestsfixturesauthor",
            connectionPrefix("overridden-connection") . "authors",
        ]);
});

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
