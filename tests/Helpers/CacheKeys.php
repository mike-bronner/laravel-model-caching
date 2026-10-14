<?php

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use Illuminate\Database\Eloquent\Model;

function cacheKey($query) : string
{
    return (new ReflectionMethod($query, "makeCacheKey"))
        ->invoke($query);
}

function keyPrefix() : string
{
    return "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite"
        . ":books:genealabslaravelmodelcachingtestsfixturesbook";
}

function authorKeyPrefix(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite"
        . ":authors:genealabslaravelmodelcachingtestsfixturesauthor";
}

function postgresAuthorKeyPrefix(): string
{
    return "genealabs:laravel-model-caching:pgsql:testing"
        . ":authors:genealabslaravelmodelcachingtestsfixturesauthor";
}

function connectionPrefix(string $connection): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:{$connection}:"
        . "{$testingSqlitePath}testing.sqlite:";
}

function valuesFromWhere(array $where): string
{
    $query = (new Book)->newQuery();
    $cacheKey = new CacheKey([], new Book, $query->getQuery(), "", [], false);

    return (new ReflectionMethod($cacheKey, "getValuesFromWhere"))
        ->invoke($cacheKey, $where);
}

function rawCacheKeyFor(object $value): string
{
    $query = (new Book)
        ->newQuery()
        ->where("published_at", ">", $value);

    return (new CacheKey([], new Book, $query->getQuery(), "", [], false))
        ->make();
}

function getCacheTagsAndKey(CachedBelongsToMany $relation): array
{
    $reflTags = new ReflectionMethod($relation, 'makeCacheTags');
    $tags = $reflTags->invoke($relation);

    $reflKey = new ReflectionMethod($relation, 'makeCacheKey');
    $rawKey = $reflKey->invoke($relation);

    return [$tags, sha1($rawKey)];
}

function whereOnlyEagerLoadKeySuffix(Model $model, string $relation, Closure $constraint): ?string
{
    $eagerLoad = (new ReflectionClass($model))->newInstanceWithoutConstructor()->{$relation}();
    $query = $eagerLoad->getBaseQuery();
    $whereCount = count($query->wheres);
    $bindingCount = count($query->bindings["where"]);

    try {
        $constraint($eagerLoad);
    } catch (Throwable) {
        return null;
    }

    return "=" . sha1(
        encodedForEagerLoadKey(array_slice($query->wheres, $whereCount))
        . encodedForEagerLoadKey(array_slice($query->bindings["where"], $bindingCount)),
    );
}

function encodedForEagerLoadKey(array $value): string
{
    $encoded = json_encode($value);

    return $encoded === false ? serialize($value) : $encoded;
}

