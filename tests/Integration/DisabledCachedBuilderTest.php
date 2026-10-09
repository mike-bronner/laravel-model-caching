<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

/**
* @SuppressWarnings(PHPMD.TooManyPublicMethods)
* @SuppressWarnings(PHPMD.TooManyMethods)
 */

test('avg model results is not cached', function () {
    $authorId = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->avg('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-avg_id");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResult = (new UncachedAuthor)
        ->with('books', 'profile')
        ->avg('id');

    expect($liveResult)->toEqual($authorId);
    expect($cachedResult)->toBeNull();
});

test('chunk model results is not cached', function () {
    $cachedChunks = collect([
        'authors' => collect(),
        'keys' => collect(),
    ]);
    $chunkSize = 3;
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];
    $uncachedChunks = collect();

    $authors = (new Author)->with('books', 'profile')
        ->disableCache()
        ->chunk($chunkSize, function ($chunk) use (&$cachedChunks, $chunkSize) {
            $offset = '';

            if ($cachedChunks['authors']->count()) {
                $offsetIncrement = $cachedChunks['authors']->count() * $chunkSize;
                $offset = "-offset_{$offsetIncrement}";
            }

            $cachedChunks['authors']->push($chunk);
            $cachedChunks['keys']->push(sha1(
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile_orderBy_authors.id_asc{$offset}-limit_3"
            ));
        });

    $liveResults = (new UncachedAuthor)->with('books', 'profile')
        ->chunk($chunkSize, function ($chunk) use (&$uncachedChunks) {
            $uncachedChunks->push($chunk);
        });

    for ($index = 0; $index < $cachedChunks['authors']->count(); $index++) {
        $key = $cachedChunks['keys'][$index];
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key);

        expect($cachedResults)->toBeNull();
        expect($liveResults)->toEqual($authors);
    }
});

test('count model results is not cached', function () {
    $authors = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->count();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-count");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResults = (new UncachedAuthor)
        ->with('books', 'profile')
        ->count();

    expect($liveResults)->toEqual($authors);
    expect($cachedResults)->toBeNull();
});

test('cursor model results is not cached', function () {
    $authors = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->cursor();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-cursor");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResults = collect(
        (new UncachedAuthor)
            ->with('books', 'profile')
            ->cursor()
    );

    expect($liveResults->diffKeys($authors))->toBeEmpty();
    expect($cachedResults)->toBeNull();
});

test('find model results is not cached', function () {
    $author = (new Author)
        ->with('books')
        ->disableCache()
        ->find(1);
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResult = (new UncachedAuthor)
        ->find(1);

    expect($author->name)->toEqual($liveResult->name);
    expect($cachedResult)->toBeNull();
});

test('get model results is not cached', function () {
    $authors = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResults = (new UncachedAuthor)
        ->with('books', 'profile')
        ->get();

    expect($liveResults->diffKeys($authors))->toBeEmpty();
    expect($cachedResults)->toBeNull();
});

test('max model results is not cached', function () {
    $authorId = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->max('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-max_id");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResult = (new UncachedAuthor)
        ->with('books', 'profile')
        ->max('id');

    expect($liveResult)->toEqual($authorId);
    expect($cachedResult)->toBeNull();
});

test('min model results is not cached', function () {
    $authorId = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->min('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-min_id");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResult = (new UncachedAuthor)
        ->with('books', 'profile')
        ->min('id');

    expect($liveResult)->toEqual($authorId);
    expect($cachedResult)->toBeNull();
});

test('pluck model results is not cached', function () {
    $authors = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->pluck('name', 'id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_name-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-pluck_name_id");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResults = (new UncachedAuthor)
        ->with('books', 'profile')
        ->pluck('name', 'id');

    expect($liveResults->diffKeys($authors))->toBeEmpty();
    expect($cachedResults)->toBeNull();
});

test('sum model results is not cached', function () {
    $authorId = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->sum('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-sum_id");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResult = (new UncachedAuthor)
        ->with('books', 'profile')
        ->sum('id');

    expect($liveResult)->toEqual($authorId);
    expect($cachedResult)->toBeNull();
});

test('value model results is not cached', function () {
    $author = (new Author)
        ->with('books', 'profile')
        ->disableCache()
        ->value('name');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_name-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-first");
    $tags = [
        'genealabslaravelmodelcachingtestsfixturesauthor',
        'genealabslaravelmodelcachingtestsfixturesbook',
        'genealabslaravelmodelcachingtestsfixturesprofile',
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key);

    $liveResult = (new UncachedAuthor)
        ->with('books', 'profile')
        ->value('name');

    expect($liveResult)->toEqual($author);
    expect($cachedResult)->toBeNull();
});

test('pagination is cached', function () {
    $authors = (new Author)
        ->disableCache()
        ->paginate(3);

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-paginate_by_3_page_1");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $liveResults = (new UncachedAuthor)
        ->paginate(3);

    expect($cachedResults)->toBeNull();
    expect($authors->toArray())->toEqual($liveResults->toArray());
});
