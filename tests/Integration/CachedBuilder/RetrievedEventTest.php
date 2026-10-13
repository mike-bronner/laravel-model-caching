<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

test('retrieved event fires on cache miss', function () {
    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    $this->cache()->flush();

    $authors = (new Author)->get();

    expect($authors->count())->toBeGreaterThan(0);
    expect($firedCount)->toBeGreaterThan(0, 'Retrieved event should fire on cache miss');
});

test('retrieved event fires on cache hit', function () {
    (new Author)->get();

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    $authors = (new Author)->get();

    expect($authors->count())->toBeGreaterThan(0);
    expect($firedCount)->toBeGreaterThan(0, 'Retrieved event should fire on cache hit');
});

test('retrieved event fires on cache hit for find', function () {
    $author = (new Author)->first();

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    $result = (new Author)->find($author->id);

    expect($result)->not->toBeNull();
    expect($firedCount)->toBeGreaterThanOrEqual(
        1,
        'Retrieved event should fire on cache hit for find()',
    );
});

test('retrieved event fires on cache hit for first', function () {
    (new Author)->first();

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    $result = (new Author)->first();

    expect($result)->not->toBeNull();
    expect($firedCount)->toBeGreaterThanOrEqual(
        1,
        'Retrieved event should fire on cache hit for first()',
    );
});

test('retrieved event fires on cache hit for paginate', function () {
    (new Author)->paginate(5);

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    $result = (new Author)->paginate(5);

    expect($result->count())->toBeGreaterThan(0);
    expect($firedCount)->toBeGreaterThan(
        0,
        'Retrieved event should fire on cache hit for paginate()',
    );
});

test('a cache hit is served when models have no event dispatcher', function () {
    $authors = (new Author)->get();
    $dispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    try {
        DB::table('authors')->delete();
        $cachedAuthors = (new Author)->get();
    } finally {
        Model::setEventDispatcher($dispatcher);
    }

    expect($cachedAuthors->pluck('id'))->toEqual($authors->pluck('id'))
        ->and($authors)->not->toBeEmpty();
});
