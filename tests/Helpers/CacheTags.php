<?php

use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CacheTagsWithOverriddenConnectionName;

function bookTags() : array
{
    return [
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:books",
    ];
}

function authorTags() : array
{
    return [
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:authors",
    ];
}

function postgresAuthorTags(): array
{
    return [
        'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabs:laravel-model-caching:pgsql:testing:authors',
    ];
}

function tag(string $name): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:{$name}";
}

function tagPrefix(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:";
}

function tagsFor($model, $query): array
{
    return (new CacheTagsWithOverriddenConnectionName([], $model, $query))
        ->make();
}

function joinSubTags($query): array
{
    return (new CacheTags(
        $query->getEagerLoads(),
        $query->getModel(),
        $query
    ))->make();
}

function assertCachedUnderTags($query, array $tags) : void
{
    $key = sha1(cacheKey($query));
    $query->get();

    expect(test()->cache()->tags($tags)->get($key))
        ->not->toBeNull("The query was not cached under the expected tags.");
}
