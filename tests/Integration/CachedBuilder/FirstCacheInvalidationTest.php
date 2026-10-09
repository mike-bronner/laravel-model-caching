<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use Illuminate\Database\Eloquent\Collection;

/**
 * AC 1: `Model::where(...)->first()` returns a non-stale result consistent
 *       with `->get()->first()` when the cache is warm.
 */
test('first returns non stale result after model saved', function () {
    // Warm the cache via first()
    $cachedAuthor = (new Author)->first();

    // Confirm get()->first() and first() agree on initial data
    $viaGet = (new Author)->get()->first();
    expect($viaGet->id)->toEqual($cachedAuthor->id);

    // Mutate the model
    $newName = 'Updated Name ' . uniqid();
    $cachedAuthor->name = $newName;
    $cachedAuthor->save();

    // After save, first() must return the fresh (updated) record — not stale cache
    $freshAuthor = (new Author)->first();
    expect($freshAuthor->name)->toEqual($newName);
});

test('first returns non stale result after model created', function () {
    // Truncate then cache the "no match" result
    (new Author)->truncate();

    $uniqueName = 'Unique Author X9Y8Z7';
    $noAuthor = (new Author)->where('name', $uniqueName)->first();
    expect($noAuthor)->toBeNull();

    // Create a matching author — created event must flush the cache
    $author = Author::create([
        'name'  => $uniqueName,
        'email' => 'x9y8z7@noemail.com',
    ]);

    // first() must now return the newly created author, not null
    $freshAuthor = (new Author)->where('name', $uniqueName)->first();
    expect($freshAuthor)->not->toBeNull();
    expect($freshAuthor->id)->toEqual($author->id);
});

/**
 * AC 2: Cache key for ->first() is distinct from ->get() when results differ.
 */
test('first cache key is distinct from get', function () {
    // Warm both caches
    $collection = (new Author)->get();
    $model      = (new Author)->first();

    // first() must return a single Model instance, not a Collection
    expect($model)->toBeInstanceOf(Author::class);
    expect($model)->not->toBeInstanceOf(Collection::class);

    // The cache keys must be different
    $firstKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first"
    );
    $getKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"
    );
    expect($getKey)->not->toEqual($firstKey);

    // The cached value under the -first key must be a single model, not a collection
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $cached = $this->cache()->tags($tags)->get($firstKey);
    expect($cached)->not->toBeNull('No cache entry found for ->first()');
    expect($cached['value'])->toBeInstanceOf(Author::class);

    // The cached get() collection must be distinct from the first() value
    $cachedCollection = $this->cache()->tags($tags)->get($getKey);
    expect($cachedCollection)->not->toBeNull('No cache entry found for ->get()');
    expect($cachedCollection['value'])->toBeInstanceOf(Collection::class);
});

/**
 * AC 3: Regression — ->first() on composite where conditions (unique-key pattern)
 *       returns fresh data after model updates.
 */
test('first on composite where returns non stale result after update', function () {
    $email = 'composite-' . uniqid() . '@noemail.com';
    $name  = 'Composite Test Author';

    // Create an author matched by the composite where
    $author = Author::create(['name' => $name, 'email' => $email]);

    // Using array-style where (the syntax originally reported as problematic)
    $found = (new Author)->where(['email' => $email, 'name' => $name])->first();
    expect($found)->not->toBeNull();
    expect($found->id)->toEqual($author->id);

    // Update the author's name — the original composite where no longer matches
    $newName = 'Renamed Composite Author';
    $author->name = $newName;
    $author->save();

    // Cache must be invalidated: old conditions should return null
    $staleCheck = (new Author)->where(['email' => $email, 'name' => $name])->first();
    expect($staleCheck)->toBeNull(
        'first() returned a stale result — cache was not properly invalidated after save',
    );

    // New conditions should return the updated model
    $updated = (new Author)->where(['email' => $email, 'name' => $newName])->first();
    expect($updated)->not->toBeNull();
    expect($updated->name)->toEqual($newName);
});
