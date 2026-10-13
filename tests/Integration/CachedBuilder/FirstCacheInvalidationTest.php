<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use Illuminate\Database\Eloquent\Collection;

test('first returns non stale result after model saved', function () {
    $cachedAuthor = (new Author)->first();

    $viaGet = (new Author)->get()->first();
    expect($viaGet->id)->toEqual($cachedAuthor->id);

    $newName = 'Updated Name ' . uniqid();
    $cachedAuthor->name = $newName;
    $cachedAuthor->save();

    $freshAuthor = (new Author)->first();
    expect($freshAuthor->name)->toEqual($newName);
});

test('first returns non stale result after model created', function () {
    (new Author)->truncate();

    $uniqueName = 'Unique Author X9Y8Z7';
    $noAuthor = (new Author)->where('name', $uniqueName)->first();
    expect($noAuthor)->toBeNull();

    $author = Author::create([
        'name'  => $uniqueName,
        'email' => 'x9y8z7@noemail.com',
    ]);

    $freshAuthor = (new Author)->where('name', $uniqueName)->first();
    expect($freshAuthor)->not->toBeNull();
    expect($freshAuthor->id)->toEqual($author->id);
});

test('first cache key is distinct from get', function () {
    $collection = (new Author)->get();
    $model      = (new Author)->first();

    expect($model)->toBeInstanceOf(Author::class);
    expect($model)->not->toBeInstanceOf(Collection::class);

    $firstKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first"
    );
    $getKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"
    );
    expect($getKey)->not->toEqual($firstKey);

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $cached = $this->cache()->tags($tags)->get($firstKey);
    expect($cached)->not->toBeNull('No cache entry found for ->first()');
    expect($cached['value'])->toBeInstanceOf(Author::class);

    $cachedCollection = $this->cache()->tags($tags)->get($getKey);
    expect($cachedCollection)->not->toBeNull('No cache entry found for ->get()');
    expect($cachedCollection['value'])->toBeInstanceOf(Collection::class);
});

test('first on composite where returns non stale result after update', function () {
    $email = 'composite-' . uniqid() . '@noemail.com';
    $name  = 'Composite Test Author';

    $author = Author::create(['name' => $name, 'email' => $email]);

    $found = (new Author)->where(['email' => $email, 'name' => $name])->first();
    expect($found)->not->toBeNull();
    expect($found->id)->toEqual($author->id);

    $newName = 'Renamed Composite Author';
    $author->name = $newName;
    $author->save();

    $staleCheck = (new Author)->where(['email' => $email, 'name' => $name])->first();
    expect($staleCheck)->toBeNull(
        'first() returned a stale result — cache was not properly invalidated after save',
    );

    $updated = (new Author)->where(['email' => $email, 'name' => $newName])->first();
    expect($updated)->not->toBeNull();
    expect($updated->name)->toEqual($newName);
});
