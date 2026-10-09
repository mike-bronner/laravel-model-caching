<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Http\Resources\Author as AuthorResource;
use Illuminate\Support\Str;

/**
* @SuppressWarnings(PHPMD.TooManyPublicMethods)
* @SuppressWarnings(PHPMD.TooManyMethods)
 */

test('cache is empty before loading models', function () {
    $results = $this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books");

    expect($results)->toBeNull();
});

test('cache is not empty after loading models', function () {
    (new Author)->with('books')->get();

    $results = $this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books"));

    expect($results)->not->toBeNull();
});

test('creating model clears cache', function () {
    (new Author)->with('books')->get();

    Author::factory()->create();

    $results = $this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1_2_3_4_5_6_" .
            "7_8_9_10-genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbooks"
        ));

    expect($results)->toBeNull();
});

test('updating model clears cache', function () {
    $author = (new Author)->with('books')->get()->first();
    $author->name = "John Jinglheimer";
    $author->save();

    $results = $this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1_2_3_4_5_6_" .
            "7_8_9_10-genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbooks"
        ));

    expect($results)->toBeNull();
});

test('deleting model clears cache', function () {
    $author = (new Author)->with('books')->get()->first();
    $author->delete();

    $results = $this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1_2_3_4_5_6_" .
            "7_8_9_10-genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbooks"
        ));

    expect($results)->toBeNull();
});

test('has many relationship is cached', function () {
    $authors = (new Author)->with('books')->get();

    $results = collect($this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books"))['value']);

    expect($results)->not->toBeNull();
    expect($authors->diffKeys($results))->toBeEmpty();
    expect($authors)->not->toBeEmpty();
    expect($results)->not->toBeEmpty();
    expect($results->count())->toEqual($authors->count());
});

test('belongs to relationship is cached', function () {
    $books = (new Book)->with('author')->get();

    $results = collect($this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-testing:{$this->testingSqlitePath}testing.sqlite:author"))['value']);

    expect($results)->not->toBeNull();
    expect($books->diffKeys($results))->toBeEmpty();
    expect($books)->not->toBeEmpty();
    expect($results)->not->toBeEmpty();
    expect($results->count())->toEqual($books->count());
});

test('belongs to many relationship is cached', function () {
    $books = (new Book)->with('stores')->get();

    $results = collect($this->cache()->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-testing:{$this->testingSqlitePath}testing.sqlite:stores"))['value']);

    expect($results)->not->toBeNull();
    expect($books->diffKeys($results))->toBeEmpty();
    expect($books)->not->toBeEmpty();
    expect($results)->not->toBeEmpty();
    expect($results->count())->toEqual($books->count());
});

test('has one relationship is cached', function () {
    $authors = (new Author)->with('profile')->get();

    $results = collect($this->cache()
        ->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:profile"))['value']);

    expect($results)->not->toBeNull();
    expect($authors->diffKeys($results))->toBeEmpty();
    expect($authors)->not->toBeEmpty();
    expect($results)->not->toBeEmpty();
    expect($results->count())->toEqual($authors->count());
});

test('avg model results creates cache', function () {
    $authorId = (new Author)->with('books', 'profile')
        ->avg('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-avg_id");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResult = (new UncachedAuthor)->with('books', 'profile')
        ->avg('id');

    expect($cachedResult)->toEqual($authorId);
    expect($cachedResult)->toEqual($liveResult);
});

test('chunk model results creates cache', function () {
    $chunkedAuthors = [];
    $chunkedKeys = [];
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    (new Author)
        ->chunk(3, function ($authors) use (&$chunkedAuthors, &$chunkedKeys) {
            $offset = "";

            if (count($chunkedKeys)) {
                $offset = "-offset_" . (count($chunkedKeys) * 3);
            }

            $key = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null_orderBy_authors.id_asc{$offset}-limit_3";
            array_push($chunkedAuthors, $authors);
            array_push($chunkedKeys, $key);
        });

    for ($index = 0; $index < count($chunkedAuthors); $index++) {
        $cachedAuthors = $this
            ->cache()
            ->tags($tags)
            ->get(sha1($chunkedKeys[$index]))['value'];

        expect(count($cachedAuthors))->toEqual(count($chunkedAuthors[$index]));
        expect($cachedAuthors)->toEqual($chunkedAuthors[$index]);
    }

    expect($chunkedAuthors)->toHaveCount(4);
});

test('count model results creates cache', function () {
    $authors = (new Author)
        ->with('books', 'profile')
        ->count();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-count");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->with('books', 'profile')
        ->count();

    expect($cachedResults)->toEqual($authors);
    expect($cachedResults)->toEqual($liveResults);
});

test('count with string creates cache', function () {
    $authors = (new Author)
        ->with('books', 'profile')
        ->count("id");
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_id-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-count");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->with('books', 'profile')
        ->count("id");

    expect($cachedResults)->toEqual($authors);
    expect($cachedResults)->toEqual($liveResults);
});

test('first model results creates cache', function () {
    $author = (new Author)
        ->first();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)
        ->get($key)['value'];

    $liveResult = (new UncachedAuthor)
        ->with('books', 'profile')
        ->first();

    expect($author->id)->toEqual($cachedResult->id);
    expect($author->id)->toEqual($liveResult->id);
});

test('max model results creates cache', function () {
    $authorId = (new Author)->with('books', 'profile')
        ->max('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-max_id");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResult = (new UncachedAuthor)->with('books', 'profile')
        ->max('id');

    expect($cachedResult)->toEqual($authorId);
    expect($cachedResult)->toEqual($liveResult);
});

test('min model results creates cache', function () {
    $authorId = (new Author)->with('books', 'profile')
        ->min('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-min_id");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResult = (new UncachedAuthor)->with('books', 'profile')
        ->min('id');

    expect($cachedResult)->toEqual($authorId);
    expect($cachedResult)->toEqual($liveResult);
});

test('pluck model results creates cache', function () {
    $authors = (new Author)->with('books', 'profile')
        ->pluck('name', 'id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_name-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-pluck_name_id");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)->with('books', 'profile')
        ->pluck('name', 'id');

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('sum model results creates cache', function () {
    $authorId = (new Author)->with('books', 'profile')
        ->sum('id');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-sum_id");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResult = (new UncachedAuthor)->with('books', 'profile')
        ->sum('id');

    expect($cachedResult)->toEqual($authorId);
    expect($cachedResult)->toEqual($liveResult);
});

test('value model results creates cache', function () {
    $authorName = (new Author)->with('books', 'profile')
        ->value('name');
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-value_name");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)
        ->get($key)['value'];
    $liveResult = (new UncachedAuthor)->with('books', 'profile')
        ->value('name');

    expect($cachedResult)->toEqual($authorName);
    expect($liveResult)->toEqual($authorName);
});

test('nested relationship eager loading', function () {
    $authors = collect([(new Author)->with('books.publisher')
            ->first()]);

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-books.publisher-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespublisher",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = collect([$this->cache()->tags($tags)
            ->get($key)['value']]);
    $liveResults = collect([(new UncachedAuthor)->with('books.publisher')
            ->first()]);

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('lazy loaded relationship resolves through cached builder', function () {
    $books = (new Author)->first()->books;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)->first()->books;

    expect($books->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('lazy loading on resource is cached', function () {
    if (Str::startsWith(app()->version(), "5.4")) {
        $this->markTestIncomplete("Resources don't exist in Laravel 5.4.");
    }

    $books = (new AuthorResource((new Author)->first()))->books;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)->first()->books;

    expect($books->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('order by clause parsing', function () {
    $authors = (new Author)->orderBy('name')->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null_orderBy_name_asc");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)->orderBy('name')->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('nested relationship where clause parsing', function () {
    $authors = (new Author)
        ->with('books.publisher')
        ->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-books.publisher");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespublisher",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];

    $liveResults = (new UncachedAuthor)->with('books.publisher')
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('exists relationship where clause parsing', function () {
    $authors = (new Author)->whereHas('books')
        ->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-exists-authors.id_=_books.author_id-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)->whereHas('books')
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('doesnt have where clause parsing', function () {
    $authors = (new Author)
        ->doesntHave('books')
        ->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-notexists-authors.id_=_books.author_id-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->doesntHave('books')
        ->get();

    expect($authors->diffKeys($cachedResults))->toBeEmpty();
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});

test('relationship queries are cached', function () {
    $books = (new Author)
        ->first()
        ->books()
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->first()
        ->books()
        ->get();

    expect($cachedResults->diffKeys($books)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($books)->isEmpty())->toBeTrue();
});

test('raw order by without column reference', function () {
    $authors = (new Author)
        ->orderByRaw('DATE()')
        ->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null_orderByRaw_date");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];

    $liveResults = (new UncachedAuthor)
        ->orderByRaw('DATE()')
        ->get();

    expect($cachedResults->diffKeys($authors)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($authors)->isEmpty())->toBeTrue();
});

test('delete', function () {
    $author = (new Author)
        ->first();
    $liveResult = (new UncachedAuthor)
        ->first();
    $authorId = $author->id;
    $liveResultId = $liveResult->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $author->delete();
    $liveResult->delete();
    $cachedResult = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $deletedAuthor = (new Author)->find($authorId);

    expect($authorId)->toEqual($liveResultId);
    expect($cachedResult)->toBeNull();
    expect($deletedAuthor)->toBeNull();
});

test('where between ids results', function () {
    $books = (new Book)
        ->whereBetween('price', [5, 10])
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-price_between_5_10");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereBetween('price', [5, 10])
        ->get();

    expect($cachedResults->diffKeys($books)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($books)->isEmpty())->toBeTrue();
});

test('where between dates results', function () {
    $books = (new Book)
        ->whereBetween('created_at', ['2018-01-01', '2018-12-31'])
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-created_at_between_2018%2D01%2D01_2018%2D12%2D31");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereBetween('price', [5, 10])
        ->get();

    expect($cachedResults->diffKeys($books)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($books)->isEmpty())->toBeTrue();
});

test('where dates results', function () {
    $books = (new Book)
        ->whereDate('created_at', '>=', '2018-01-01')
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-created_at_date_>=_2018%2D01%2D01");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->whereBetween('price', [5, 10])
        ->get();

    expect($cachedResults->diffKeys($books)->isEmpty())->toBeTrue();
    expect($liveResults->diffKeys($books)->isEmpty())->toBeTrue();
});

test('hash collision', function () {
    $key1 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_notin_1_2");
    $tags1 = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];
    $books = (new Book)
        ->whereNotIn('id', [1, 2])
        ->get();
    $this->cache()->tags($tags1)->flush();

    $authors = (new Author)
        ->disableCache()
        ->get();
    $key2 = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor";
    $this->cache()
        ->tags($tags1)
        ->rememberForever(
            $key1,
            function () use ($key2, $authors) {
                return [
                    'key' => $key2,
                    'value' => $authors,
                ];
            }
        );
    $cachedBooks = (new Book)
        ->whereNotIn('id', [1, 2])
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags1)
        ->get($key1)['value'];

    expect($cachedResults->keyBy('id')->diffKeys($books->keyBy('id'))->isEmpty())->toBeTrue();
    expect($cachedResults->diff($authors)->isNotEmpty())->toBeTrue();
});

test('subsequent disabled cache queries do not cache', function () {
    (new Author)->disableCache()->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $cachedAuthors1 = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    (new Author)->disableCache()->get();
    $cachedAuthors2 = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($cachedAuthors1)->toBeEmpty();
    expect($cachedAuthors2)->toBeEmpty();
});

test('insert invalidates cache', function () {
    $authors = (new Author)
        ->get();

    (new Author)
        ->insert([
            'name' => 'Test Insert',
            'email' => 'none@noemail.com',
        ]);
    $authorsAfterInsert = (new Author)
        ->get();
    $uncachedAuthors = (new UncachedAuthor)
        ->get();

    expect($authors)->toHaveCount(10);
    expect($authorsAfterInsert)->toHaveCount(11);
    expect($uncachedAuthors)->toHaveCount(11);
});

test('update invalidates cache', function () {
    $originalAuthor = (new Author)
        ->first();
    $author = (new Author)
        ->first();

    $author->update([
        "name" => "Updated Name",
    ]);
    $authorAfterUpdate = (new Author)
        ->find($author->id);
    $uncachedAuthor = (new UncachedAuthor)
        ->find($author->id);

    expect($authorAfterUpdate->name)->not->toEqual($originalAuthor->name);
    expect($authorAfterUpdate->name)->toEqual("Updated Name");
    expect($uncachedAuthor->name)->toEqual($authorAfterUpdate->name);
});

test('attach invalidates cache', function () {
    $book = (new Book)
        ->find(1);

    $book->stores()->attach(1);
    $cachedBook = (new Book)
        ->find(1);

    expect($book->stores->keyBy('id')->has(1))->toBeTrue();
});
