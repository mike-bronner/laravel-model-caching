<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Support\Carbon;

test('all model results creates cache', function () {
    $authors = (new Author)->all();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->all();

    expect($cachedResults)->toEqual($authors);
    expect($liveResults->diffAssoc($cachedResults))->toBeEmpty();
});

test('scope disables caching', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = ["genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor"];
    $authors = (new Author)
        ->where("name", "Bruno")
        ->disableCache()
        ->get();

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toBeNull();
    expect($cachedResults)->not->toEqual($authors);
});

test('scope disables caching when called on model', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:test-prefix:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = ["genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:test-prefix:genealabslaravelmodelcachingtestsfixturesauthor"];
    $authors = (new PrefixedAuthor)
        ->disableCache()
        ->where("name", "Bruno")
        ->get();

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toBeNull();
    expect($cachedResults)->not->toEqual($authors);
});

test('scope disable cache doesnt crash when caching is disabled in config', function () {
    config(['laravel-model-caching.enabled' => false]);
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:test-prefix:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = ["genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:test-prefix:genealabslaravelmodelcachingtestsfixturesauthor"];
    $authors = (new PrefixedAuthor)
        ->where("name", "Bruno")
        ->disableCache()
        ->get();

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toBeNull();
    expect($cachedResults)->not->toEqual($authors);
});

test('all method caching can be disabled via config', function () {
    config(['laravel-model-caching.enabled' => false]);
    $authors = (new Author)
        ->all();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    config(['laravel-model-caching.enabled' => true]);

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedResults)->toBeEmpty();
    expect($authors)->not->toBeEmpty();
    expect($authors)->toHaveCount(10);
});

test('where has is being cached', function () {
    $books = (new Book)
        ->with('author')
        ->whereHas('author', function ($query) {
            $query->whereId('1');
        })
        ->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-exists-books.author_id_=_authors.id-id_=_1-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:author");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($books->first()->author->id)->toEqual(1);
    expect($cachedResults->first()->author->id)->toEqual(1);
});

test('where has with closure is being cached', function () {
    $books1 = (new Book)
        ->with('author')
        ->whereHas('author', function ($query) {
            $query->whereId(1);
        })
        ->get()
        ->keyBy('id');
    $books2 = (new Book)
        ->with('author')
        ->whereHas('author', function ($query) {
            $query->whereId(2);
        })
        ->get()
        ->keyBy('id');

    expect($books1->diffKeys($books2))->not->toBeEmpty();
});

test('cooldown is not queried for normal cached models', function () {
    $class = new ReflectionClass(Author::class);
    $method = $class->getMethod('getModelCacheCooldown');
    $author = (new Author)
        ->first();

    expect($method->invokeArgs($author, [$author]))->toEqual([null, null, null]);
});

test('cooldown is queried for cooldown models', function () {
    $class = new ReflectionClass(AuthorWithCooldown::class);
    $method = $class->getMethod('getModelCacheCooldown');
    $author = (new AuthorWithCooldown)
        ->withCacheCooldownSeconds(1)
        ->first();

    [$usesCacheCooldown, $expiresAt, $savedAt] = $method->invokeArgs($author, [$author]);

    expect(1)->toEqual($usesCacheCooldown);
    expect(get_class($expiresAt))->toEqual("Illuminate\Support\Carbon");
    expect($savedAt)->toBeNull();
});

test('model cache doesnt invalidate during cooldown period', function () {
    $authors = (new AuthorWithCooldown)
        ->withCacheCooldownSeconds(1)
        ->get();

    Author::factory()->count(1)->create();
    $authorsDuringCooldown = (new AuthorWithCooldown)
        ->get();
    $uncachedAuthors = (new UncachedAuthor)
        ->get();
    sleep(3);
    $authorsAfterCooldown = (new AuthorWithCooldown)
        ->get();

    expect($authors)->toHaveCount(10);
    expect($authorsDuringCooldown)->toHaveCount(11);
    expect($uncachedAuthors)->toHaveCount(11);
    expect($authorsAfterCooldown)->toHaveCount(11);
});

test('model cache does invalidate when no cooldown period', function () {
    $authors = (new AuthorWithCooldown)
        ->get();

    Author::factory()->count(1)->create();
    $authorsAfterCreate = (new Author)
        ->get();
    $uncachedAuthors = (new UncachedAuthor)
        ->get();

    expect($authors)->toHaveCount(10);
    expect($authorsAfterCreate)->toHaveCount(11);
    expect($uncachedAuthors)->toHaveCount(11);
});

test('flushing a cooldown model records when it was saved', function () {
    $method = (new ReflectionClass(AuthorWithCooldown::class))->getMethod('getModelCacheCooldown');
    $author = (new AuthorWithCooldown)
        ->withCacheCooldownSeconds(30)
        ->first();

    expect($method->invokeArgs($author, [$author])[2])->toBeNull();

    $author->flushCache();
    [$usesCacheCooldown, , $savedAt] = $method->invokeArgs($author, [$author]);

    expect($usesCacheCooldown)->toEqual(30)
        ->and($savedAt)->toBeInstanceOf(Carbon::class);
});

test('cooldown seconds default to the model setting', function () {
    $method = (new ReflectionClass(AuthorWithCooldown::class))->getMethod('getModelCacheCooldown');
    $author = new AuthorWithCooldown;
    $author->cacheCooldownSeconds = 7;

    $author->newQuery()->withCacheCooldownSeconds();

    expect($author->cacheCooldownSeconds)->toBe(7)
        ->and($method->invokeArgs($author, [$author])[0])->toEqual(7);
});

test('a cache prefix set at runtime keys the model queries', function () {
    $author = new PrefixedAuthor;
    $author->cachePrefix = 'runtime-prefix';

    $query = $author->newQuery();
    $key = cacheKey($query);
    $tags = (new ReflectionMethod($query, 'makeCacheTags'))->invoke($query);

    $authors = $query->get();
    $cached = $this->cache()->tags($tags)->get(sha1($key));

    expect($author->cachePrefix)->toBe('runtime-prefix')
        ->and($cached['key'])->toBe($key)
        ->and($cached['key'])->toContain(':runtime-prefix:authors:')
        ->and($cached['key'])->not->toContain('model-prefix')
        ->and($cached['value']->pluck('id'))->toEqual($authors->pluck('id'));
});
