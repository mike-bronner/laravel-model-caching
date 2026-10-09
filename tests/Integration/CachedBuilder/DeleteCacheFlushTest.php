<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

// --- delete() tests ---
test('delete zero rows does not flush cache', function () {
    $key = populateBookCache();

    $result = (new Book)->where("id", 0)->delete();

    expect($result)->toEqual(0);
    expect($this->cache()->tags(bookTags())->get($key))->not->toBeNull();
});

test('delete one row flushes cache', function () {
    $key = populateBookCache();
    $book = (new Book)->first();

    $result = (new Book)->where("id", $book->id)->delete();

    expect($result)->toEqual(1);
    expect($this->cache()->tags(bookTags())->get($key))->toBeNull();
});

test('delete multiple rows flushes cache', function () {
    $key = populateBookCache();

    $result = (new Book)->where("id", ">", 0)->delete();

    expect($result)->toBeGreaterThan(1);
    expect($this->cache()->tags(bookTags())->get($key))->toBeNull();
});

// --- forceDelete() tests ---
test('force delete zero rows does not flush cache', function () {
    $key = populateAuthorCache();

    $result = (new Author)->where("id", 0)->forceDelete();

    expect($result)->toEqual(0);
    expect($this->cache()->tags(authorTags())->get($key))->not->toBeNull();
});

test('force delete one row flushes cache', function () {
    $key = populateAuthorCache();

    $result = (new Author)->where("id", 1)->forceDelete();

    expect($result)->toEqual(1);
    expect($this->cache()->tags(authorTags())->get($key))->toBeNull();
});

test('force delete multiple rows flushes cache', function () {
    $key = populateAuthorCache();

    $result = (new Author)->where("id", ">", 0)->forceDelete();

    expect($result)->toBeGreaterThan(1);
    expect($this->cache()->tags(authorTags())->get($key))->toBeNull();
});

// --- Integration test ---
test('repeated deletes on empty result set do not flush cache', function () {
    $key = populateBookCache();

    // Delete with no matching rows multiple times
    for ($i = 0; $i < 3; $i++) {
        $result = (new Book)->where("id", 0)->delete();
        expect($result)->toEqual(0);
    }

    // Cache should still be intact
    expect($this->cache()->tags(bookTags())->get($key))->not->toBeNull();
});

function populateAuthorCache(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    (new Author)->all();

    $key = sha1(
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"
    );

    expect(test()->cache()->tags(authorTags())->get($key))->not->toBeNull();

    return $key;
}
