<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\RunsOnPostgres;
use Illuminate\Foundation\Testing\RefreshDatabase;

// This file runs against PostgreSQL, which SQLite cannot stand in for: its
// grammar refuses to compile a JSON "contains" predicate at all. RunsOnPostgres
// skips each test when no server is reachable.
uses(RefreshDatabase::class, RunsOnPostgres::class);

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    Author::factory()->count(10)->create();
});

// The key-level twin of this lives in WhereNotTest and runs on SQLite,
// because building a key touches no database. This is the half that needs a
// server: whereJsonContains() and whereJsonDoesntContain() shared one cache
// key, so the negated query was served the rows it exists to exclude.
test('where json doesnt contain returns its own results after where json contains was cached', function () {
    $containsKey = sha1(postgresAuthorKeyPrefix() . "-finances->total_jsoncontains_5000-authors.deleted_at_null");
    $doesntContainKey = sha1(postgresAuthorKeyPrefix() . "-finances->total_not_jsoncontains_5000-authors.deleted_at_null");

    $containsResults = (new Author)
        ->whereJsonContains("finances->total", 5000)
        ->get();

    expect($containsResults)->toHaveCount(10);
    expect(cachedValueFor($containsKey))->not->toBeNull(
        "The first query must populate the cache, or the second query has nothing to collide with",
    );

    $results = (new Author)
        ->whereJsonDoesntContain("finances->total", 5000)
        ->get();
    $liveResults = (new UncachedAuthor)
        ->whereJsonDoesntContain("finances->total", 5000)
        ->get();
    $cachedResults = cachedValueFor($doesntContainKey)['value'];

    expect($liveResults)->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('with in using collection query', function () {
    $key = sha1("genealabs:laravel-model-caching:pgsql:testing:authors:genealabslaravelmodelcachingtestsfixturesauthor-finances->total_jsoncontains_5000-authors.deleted_at_null");
    $tags = [
        'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabs:laravel-model-caching:pgsql:testing:authors',
    ];

    $authors = (new Author)
        ->whereJsonContains("finances->total", 5000)
        ->get();

    $liveResults = (new UncachedAuthor)
        ->whereJsonContains("finances->total", 5000)
        ->get();

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($cachedResults)->toHaveCount(10);
    expect($liveResults)->toHaveCount(10);
    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('with in using collection query with array values', function () {
    $key = sha1("genealabs:laravel-model-caching:pgsql:testing:authors:genealabslaravelmodelcachingtestsfixturesauthor-finances->tags_jsoncontains_[\"foo\",\"bar\"]-authors.deleted_at_null");
    $tags = [
        'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabs:laravel-model-caching:pgsql:testing:authors',
    ];

    $authors = (new Author)
        ->whereJsonContains("finances->tags", ['foo', 'bar'])
        ->get();
    $liveResults = (new UncachedAuthor)
        ->whereJsonContains("finances->tags", ['foo', 'bar'])
        ->get();

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($liveResults)->toHaveCount(10);
    expect($cachedResults)->toHaveCount(10);
    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

function postgresAuthorKeyPrefix(): string
{
    return "genealabs:laravel-model-caching:pgsql:testing"
        . ":authors:genealabslaravelmodelcachingtestsfixturesauthor";
}

function postgresAuthorTags(): array
{
    return [
        'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabs:laravel-model-caching:pgsql:testing:authors',
    ];
}

function cachedValueFor(string $key)
{
    return test()->cache()
        ->tags(postgresAuthorTags())
        ->get($key);
}
