<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('cursor paginate on cached model returns correct results', function () {
    $cachedResults = (new Author)->orderBy('id')->cursorPaginate(5);
    $uncachedResults = (new UncachedAuthor)->orderBy('id')->cursorPaginate(5);

    expect($cachedResults->count())->toEqual($uncachedResults->count());
    expect($cachedResults->pluck('id')->toArray())->toEqual(
        $uncachedResults->pluck('id')->toArray(),
    );
});

test('cursor paginate with cursor returns next page', function () {
    $firstPage = (new Author)->orderBy('id')->cursorPaginate(2);
    $cursor = $firstPage->nextCursor();

    if ($cursor) {
        $secondPage = (new Author)->orderBy('id')->cursorPaginate(2, ['*'], 'cursor', $cursor);
        $uncachedSecondPage = (new UncachedAuthor)->orderBy('id')->cursorPaginate(2, ['*'], 'cursor', $cursor);

        expect($secondPage->pluck('id')->toArray())->toEqual(
            $uncachedSecondPage->pluck('id')->toArray(),
        );
    } else {
        $this->markTestSkipped('Not enough authors for cursor pagination test');
    }
});

test('row values where clause serializes into cache key', function () {
    // Directly test that whereRowValues doesn't crash cache key generation
    // by fetching results — the get() call triggers makeCacheKey internally
    $results = (new Author)
        ->whereRowValues(['id', 'name'], '>', [1, 'test'])
        ->orderBy('id')
        ->get();

    expect($results)->not->toBeNull();
});

test('row values where produces different cache keys', function () {
    $query1 = (new Author)
        ->whereRowValues(['id', 'name'], '>', [1, 'a'])
        ->orderBy('id');

    $query2 = (new Author)
        ->whereRowValues(['id', 'name'], '>', [2, 'b'])
        ->orderBy('id');

    $cacheKey1 = (new \ReflectionMethod($query1, 'makeCacheKey'))
        ->invoke($query1);
    $cacheKey2 = (new \ReflectionMethod($query2, 'makeCacheKey'))
        ->invoke($query2);

    expect($cacheKey2)->not->toEqual($cacheKey1);
});
