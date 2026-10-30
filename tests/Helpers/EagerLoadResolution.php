<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\RelationCallLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function relatedConnectionSegment(string $relation): string
{
    return "-" . Str::after(tagPrefix(), "genealabs:laravel-model-caching:") . $relation;
}

function cacheKeyTagsAndTtl($query): array
{
    return [
        "key" => (new ReflectionMethod($query, "makeCacheKey"))->invoke($query),
        "tags" => (new ReflectionMethod($query, "makeCacheTags"))->invoke($query),
        "ttl" => (new ReflectionMethod($query, "modelCacheTtlSeconds"))->invoke($query),
    ];
}

function relationCallsOnCacheHit(callable $read): SplObjectStorage
{
    $read();
    RelationCallLog::reset();
    $read();

    return RelationCallLog::$calls;
}

function mostRelationCallsOnOneInstance(SplObjectStorage $calls): int
{
    $most = 0;

    foreach ($calls as $model) {
        $most = max($most, ...array_values($calls[$model]));
    }

    return $most;
}

function relationCallsOn(SplObjectStorage $calls, string $class, string $relation): array
{
    $counts = [];

    foreach ($calls as $model) {
        if ($model instanceof $class && isset($calls[$model][$relation])) {
            $counts[] = $calls[$model][$relation];
        }
    }

    return $counts;
}

function databaseQueriesRunBy(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}
