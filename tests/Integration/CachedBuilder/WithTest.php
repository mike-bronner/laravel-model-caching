<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Support\Facades\DB;

test('with limited query', function () {
    $authors = (new Author)
        ->where("id", 1)
        ->with([
            'books' => function ($query) {
                $query->where("id", "<", 100)
                    ->offset(5)
                    ->limit(1);
            }
        ])
        ->first();
    $uncachedAuthor = (new UncachedAuthor)->with([
            'books' => function ($query) {
                $query->where("id", "<", 100)
                    ->offset(5)
                    ->limit(1);
            }
        ])
        ->first();

    expect($authors->books()->pluck("id"))->toEqual($uncachedAuthor->books()->pluck("id"));
    expect($authors->id)->toEqual($uncachedAuthor->id);
});

test('with query', function () {
    $author = (new Author)
        ->where("id", 1)
        ->with([
            'books' => function ($query) {
                $query->where("id", "<", 100);
            }
        ])
        ->first();
    $uncachedAuthor = (new UncachedAuthor)->with([
            'books' => function ($query) {
                $query->where("id", "<", 100);
            },
        ])
        ->where("id", 1)
        ->first();

    expect($author->books()->count())->toEqual($uncachedAuthor->books()->count());
    expect($author->id)->toEqual($uncachedAuthor->id);
});

test('multi level with query', function () {
    $author = (new Author)
        ->where("id", 1)
        ->with([
            'books.publisher' => function ($query) {
                $query->where("id", "<", 100);
            }
        ])
        ->first();
    $uncachedAuthor = (new UncachedAuthor)->with([
            'books.publisher' => function ($query) {
                $query->where("id", "<", 100);
            },
        ])
        ->where("id", 1)
        ->first();

    expect($author->books()->count())->toEqual($uncachedAuthor->books()->count());
    expect($author->id)->toEqual($uncachedAuthor->id);
});

test('with belongs to many relationship query', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.id_=_3-testing:{$this->testingSqlitePath}testing.sqlite:stores-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:book-store",
    ];

    $stores = (new Book)
        ->with("stores")
        ->find(3)
        ->stores;
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ->stores;
    $liveResults = (new UncachedBook)
        ->with("stores")
        ->find(3)
        ->stores;

    expect($stores->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('eager loading a dynamic relation returns its related models', function () {
    Author::resolveRelationUsing('dynamicallyResolvedBooks', fn ($author) => $author->hasMany(Book::class));

    $author = (new Author)
        ->with('dynamicallyResolvedBooks')
        ->find(1);

    expect($author->relationLoaded('dynamicallyResolvedBooks'))->toBeTrue()
        ->and($author->dynamicallyResolvedBooks->pluck('id')->sort()->values()->all())
        ->toEqual(DB::table('books')->where('author_id', 1)->orderBy('id')->pluck('id')->all());
});

test('a dynamic relation eager load is invalidated when the related model is written', function () {
    Author::resolveRelationUsing('dynamicBooks', fn ($author) => $author->hasMany(Book::class));
    (new Author)->with('dynamicBooks')->find(1);

    Book::factory()->create(['author_id' => 1]);

    expect((new Author)->with('dynamicBooks')->find(1)->dynamicBooks)
        ->toHaveCount(DB::table('books')->where('author_id', 1)->count());
});

test('constrained eager loads of a dynamic relation are cached separately', function () {
    Author::resolveRelationUsing('dynamicBooks', fn ($author) => $author->hasMany(Book::class));
    $firstBookId = DB::table('books')->where('author_id', 1)->min('id');
    $lastBookId = DB::table('books')->where('author_id', 1)->max('id');

    $first = (new Author)
        ->with(['dynamicBooks' => fn ($query) => $query->where('id', $firstBookId)])
        ->find(1);
    $last = (new Author)
        ->with(['dynamicBooks' => fn ($query) => $query->where('id', $lastBookId)])
        ->find(1);

    expect($first->dynamicBooks->pluck('id')->all())->toEqual([$firstBookId])
        ->and($last->dynamicBooks->pluck('id')->all())->toEqual([$lastBookId]);
});

test('eager loading a missing relation throws and caches nothing', function () {
    expect(fn () => (new Author)->with('missing')->get())
        ->toThrow(RelationNotFoundException::class)
        ->and(modelCacheKeys())->toBe([]);
});
