<?php

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function cacheReadsAndQueriesOfTheSecondRun(Closure $query): array
{
    $query();
    $cacheReads = 0;
    Event::listen([CacheHit::class, CacheMissed::class], function () use (&$cacheReads) {
        $cacheReads++;
    });
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $result = $query();

        return [$result, $cacheReads, DB::getQueryLog()];
    } finally {
        DB::disableQueryLog();
    }
}

function resultsPerScopeBinding(string $scopedClass, string $column, array $bindings, Closure $query): array
{
    $globalScopes = Model::getAllGlobalScopes();
    $binding = null;
    $scopedClass::addGlobalScope('matchesTheBinding', function ($builder) use (&$binding, $scopedClass, $column) {
        $builder->where((new $scopedClass)->getTable() . ".{$column}", $binding);
    });

    try {
        return collect($bindings)
            ->map(function ($value) use (&$binding, $query) {
                $binding = $value;

                return $query();
            })
            ->all();
    } finally {
        Model::setAllGlobalScopes($globalScopes);
    }
}

function rewriteCachedEntry($query, Closure $rewrite): void
{
    $cacheKey = (new ReflectionMethod($query, "makeCacheKey"))->invoke($query);
    $cacheTags = (new ReflectionMethod($query, "makeCacheTags"))->invoke($query);
    $entry = (new ReflectionMethod($query, "getModelCacheValue"))->invoke($query, $cacheKey, $cacheTags, true);

    (new ReflectionMethod($query, "putModelCacheValue"))->invoke($query, $cacheKey, $rewrite($entry), $cacheTags, true);
}

function addScopeFailingPastLimits(string $scopedClass, int $seconds, int $applications): Closure
{
    $deadline = microtime(true) + $seconds;
    $applied = 0;
    $limitReached = false;
    $scopedClass::addGlobalScope('failsPastLimits', function () use ($deadline, $applications, &$applied, &$limitReached) {
        if (++$applied > $applications || microtime(true) > $deadline) {
            $limitReached = true;

            throw new RuntimeException("The read ran past its time or recursion limit.");
        }
    });

    return function () use (&$limitReached) {
        return $limitReached;
    };
}

function queriesAgainstTable(array $queryLog, string $table): array
{
    return collect($queryLog)
        ->filter(fn (array $entry) => str_contains($entry["query"], "from \"{$table}\""))
        ->values()
        ->all();
}
