<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Support\Facades\DB;

test('eagerloaded has many', function () {
    $authorId = (new Author)
        ->disableModelCaching()
        ->whereHas("books")
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_inraw_{$authorId}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $books = (new Author)
        ->with("books")
        ->find($authorId)
        ->books;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->with("books")
        ->find($authorId)
        ->books;

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('lazyloaded has many', function () {
    $authorId = (new Author)
        ->disableModelCaching()
        ->whereHas("books")
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_{$authorId}-books.author_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $books = (new Author)
        ->find($authorId)
        ->books;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->find($authorId)
        ->books;

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('a chaperoned relation is served from the cache with its inverse set', function (): void {
    $author = (new Author)->first();
    $books = $author->books()
        ->chaperone()
        ->get();
    $titles = $books->pluck('title')
        ->all();

    DB::table('books')
        ->where('author_id', $author->id)
        ->update(['title' => 'Changed behind the cache']);

    $cachedBooks = $author->books()
        ->chaperone()
        ->get();
    $cachedTitles = $cachedBooks->pluck('title')
        ->all();
    $hasInverse = $cachedBooks->every(fn ($book) => $book->relationLoaded('author'));
    $inverseIsParent = $cachedBooks->every(fn ($book) => $book->author === $author);

    expect($books)
        ->not
        ->toBeEmpty();
    expect($cachedTitles)
        ->toBe($titles);
    expect($cachedTitles)
        ->not
        ->toContain('Changed behind the cache');
    expect($hasInverse)
        ->toBeTrue();
    expect($inverseIsParent)
        ->toBeTrue();
});
