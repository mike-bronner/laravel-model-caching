<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\KeepsItsNamePrivate;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

function authoredSubQuery()
{
    return DB::table('books')
        ->select('author_id')
        ->groupBy('author_id');
}

function latestBookSubQuery()
{
    return DB::table('books')
        ->select('title')
        ->whereColumn('books.author_id', 'authors.id')
        ->orderByDesc('id')
        ->limit(1);
}

function withDeferredJoinSub($model)
{
    return $model
        ->select('authors.*')
        ->beforeQuery(function ($base) {
            $base->joinSub(
                DB::table('books')
                    ->select('author_id')
                    ->groupBy('author_id'),
                'authored',
                'authored.author_id',
                '=',
                'authors.id'
            );
        });
}

function processWhereClauseTestWithOperator(string $operator)
{
    $testingSqlitePath = test()->testingSqlitePath;

    $author = (new Author)->first();
    $authors = (new Author)
        ->where('name', $operator, $author->name)
        ->get();
    $keyParts = [
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name",
        '_',
        str_replace(' ', '_', strtolower($operator)),
        '_',
        $author->name,
        "-authors.deleted_at_null"
    ];
    $key = sha1(implode('', $keyParts));
    $tags = [
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = test()->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->where('name', $operator, $author->name)
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
}

function runTwiceExcludingTheFirstResult(string $scopedClass, Closure $query) : array
{
    $globalScopes = Model::getAllGlobalScopes();
    $excludedId = 0;
    $scopedClass::addGlobalScope('excludesOneRow', function ($builder) use (&$excludedId, $scopedClass) {
        $builder->where((new $scopedClass)->getTable() . '.id', '!=', $excludedId);
    });

    try {
        $first = $query();
        $excludedId = collect($first)->first();
        $second = $query();
    } finally {
        Model::setAllGlobalScopes($globalScopes);
    }

    return [collect($first)->filter()->values()->all(), collect($second)->filter()->values()->all()];
}

function expectEachScopedQueryToMatchItsLiveRows(array $queries) : void
{
    collect($queries)->each(function (array $pair, string $description) {
        [$cachedQuery, $liveQuery] = $pair;

        expect($cachedQuery()->get()->pluck('id')->sort()->values()->all())
            ->toEqual($liveQuery()->get()->pluck('id')->sort()->values()->all(), $description);
    });
}

function expectPrivateBindingsToKeepTheirOwnRows(Closure $query, array $values): void
{
    expectBindingsToKeepTheirOwnRows(
        $query,
        collect($values)
            ->map(fn (string $value) => new KeepsItsNamePrivate($value))
            ->all(),
    );
}

function expectBindingsToKeepTheirOwnRows(Closure $query, array $bindings): void
{
    [$first, $second] = $bindings;
    $liveResults = $query(new UncachedAuthor, $second)->get()->toArray();
    $firstKey = cacheKey($query(new Author, $first));

    expect($firstKey)->not->toBe("")
        ->and($firstKey)->not->toBe(cacheKey($query(new Author, $second)));

    $query(new Author, $first)->get();

    expect($liveResults)->not->toBeEmpty()
        ->and($query(new Author, $second)->get()->toArray())->toEqual($liveResults);
}

function firstTwoDistinctValues(string $table, string $column): array
{
    $values = DB::table($table)
        ->orderBy("id")
        ->pluck($column)
        ->unique()
        ->take(2)
        ->values()
        ->all();

    expect($values)->toHaveCount(2);

    return $values;
}

function assertActivePageInLinks(int $page, string $linksHtml): void
{
    expect($linksHtml)->toMatch(
        '/aria-current="page">\s*<span[^>]*>' . $page . '<\/span>/s',
        "Expected page {$page} to be marked as the active page in pagination links.",
    );
}

function booksHavingAuthorAbove(Model $model, array $value)
{
    return $model
        ->groupBy("author_id")
        ->having("author_id", ">", $value);
}
