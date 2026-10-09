<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;

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
    // First call — cache miss, populates cache
    (new Author)->get();

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    // Second call — cache hit
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

    // Cache hit
    $result = (new Author)->find($author->id);

    expect($result)->not->toBeNull();
    expect($firedCount)->toBeGreaterThanOrEqual(
        1,
        'Retrieved event should fire on cache hit for find()',
    );
});

test('retrieved event fires on cache hit for first', function () {
    // Cache miss
    (new Author)->first();

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    // Cache hit
    $result = (new Author)->first();

    expect($result)->not->toBeNull();
    expect($firedCount)->toBeGreaterThanOrEqual(
        1,
        'Retrieved event should fire on cache hit for first()',
    );
});

test('retrieved event fires on cache hit for paginate', function () {
    // Cache miss
    (new Author)->paginate(5);

    $firedCount = 0;

    Author::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    // Cache hit
    $result = (new Author)->paginate(5);

    expect($result->count())->toBeGreaterThan(0);
    expect($firedCount)->toBeGreaterThan(
        0,
        'Retrieved event should fire on cache hit for paginate()',
    );
});
