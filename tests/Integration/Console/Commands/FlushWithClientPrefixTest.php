<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

beforeEach(function () {
    config(['laravel-model-caching.store' => 'model-prefixed']);
    app('redis')->connection('model-cache-prefixed')->flushdb();
});

afterEach(function () {
    if ($this->redisIsAvailable) {
        app('redis')->connection('model-cache-prefixed')->flushdb();
    }
});

test('entire cache is cleared on prefixed connection', function () {
    $connection = app('redis')->connection('model-cache-prefixed');
    $connection->set('foreign:keep-me', 'should-survive');

    $cachedAuthors = Author::query()->get();
    $authorId = $cachedAuthors->first()->id;

    UncachedAuthor::query()
        ->where('id', $authorId)
        ->update(['name' => 'CLEARED_AUTHOR']);

    expect(Author::query()->get()->firstWhere('id', $authorId)->name)->not->toBe('CLEARED_AUTHOR');

    $this->artisan('modelCache:clear')
        ->assertExitCode(0);

    expect(Author::query()->get()->firstWhere('id', $authorId)->name)->toBe('CLEARED_AUTHOR');

    expect($connection->get('foreign:keep-me'))->toBe('should-survive');
});
