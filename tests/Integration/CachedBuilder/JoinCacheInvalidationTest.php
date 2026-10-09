<?php

use GeneaLabs\LaravelModelCaching\CachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorBaseQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableRoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('join query cache tags contain joined table tag', function () {
    $query = (new Author)
        ->select('authors.*', 'books.title')
        ->join('books', 'books.author_id', '=', 'authors.id');

    // CacheTags expects the Eloquent builder (CachedBuilder), which has getQuery()
    $tags = (new CacheTags(
        $query->getEagerLoads(),
        $query->getModel(),
        $query
    ))->make();

    $bookTableTag = (new Str)->slug('books');

    expect(collect($tags)->contains(function ($tag) {
            return str_contains($tag, (new Str)->slug(Author::class));
        }))->toBeTrue('Tags should include the primary model class tag');

    expect(collect($tags)->contains(function ($tag) use ($bookTableTag) {
            return str_contains($tag, $bookTableTag);
        }))->toBeTrue('Tags should include the joined table tag');
});

test('join query cache invalidates when joined model updated', function () {
    $results = (new Author)
        ->select('authors.*', 'books.title AS book_title')
        ->join('books', 'books.author_id', '=', 'authors.id')
        ->get();

    expect($results->count())->toBeGreaterThan(0);

    $book = (new Book)->first();
    $newTitle = 'Updated Title ' . uniqid();
    $book->title = $newTitle;
    $book->save();

    $freshResults = (new Author)
        ->select('authors.*', 'books.title AS book_title')
        ->join('books', 'books.author_id', '=', 'authors.id')
        ->get();

    $matchingRows = $freshResults->where('book_title', $newTitle);
    expect($matchingRows->count())->toBeGreaterThan(
        0,
        'Join query should return fresh data after the joined model is updated',
    );
});

test('left join query cache invalidates when joined model updated', function () {
    $results = (new Author)
        ->select('authors.*', 'books.title AS book_title')
        ->leftJoin('books', 'books.author_id', '=', 'authors.id')
        ->get();

    expect($results->count())->toBeGreaterThan(0);

    $book = (new Book)->first();
    $newTitle = 'Left Join Updated ' . uniqid();
    $book->title = $newTitle;
    $book->save();

    $freshResults = (new Author)
        ->select('authors.*', 'books.title AS book_title')
        ->leftJoin('books', 'books.author_id', '=', 'authors.id')
        ->get();

    $matchingRows = $freshResults->where('book_title', $newTitle);
    expect($matchingRows->count())->toBeGreaterThan(
        0,
        'Left join query should return fresh data after the joined model is updated',
    );
});

test('relation query cache tags contain pivot table tag', function () {
    // Via makeCacheTags(), the only caller the caching path actually uses
    $relation = (new Book)
        ->first()
        ->stores();
    $method = new ReflectionMethod($relation, 'makeCacheTags');
    $tags = $method->invoke($relation);

    $pivotTableTag = (new Str)->slug('book_store');

    expect(collect($tags)->contains(function ($tag) {
            return str_contains($tag, (new Str)->slug(Store::class));
        }))->toBeTrue('Tags should include the related model class tag');

    expect(collect($tags)->contains(function ($tag) use ($pivotTableTag) {
            return str_contains($tag, $pivotTableTag);
        }))->toBeTrue('Tags should include the pivot table joined by the relation');
});

test('morph to many query cache tags contain pivot table tag', function () {
    $relation = (new Post)
        ->first()
        ->tags();
    $method = new ReflectionMethod($relation, 'makeCacheTags');
    $tags = $method->invoke($relation);

    $pivotTableTag = (new Str)->slug('taggables');

    expect(collect($tags)->contains(function ($tag) use ($pivotTableTag) {
            return str_contains($tag, $pivotTableTag);
        }))->toBeTrue('Tags should include the pivot table joined by the polymorphic relation');
});

test('belongs to many cache invalidates when pivot table is written by model', function () {
    $userId = (int) DB::table('role_user')
        ->value('user_id');
    $user = (new User)
        ->find($userId);

    // Prime the read: its tags name Role and roles, the delete writes role_user
    expect($user->roles()->count())->toBeGreaterThan(0);

    // Bulk delete through a cachable model whose table IS the pivot, so no
    // pivot events fire and only the executed query's tags are invalidated
    (new CachableRoleUser)
        ->newQuery()
        ->where('user_id', $userId)
        ->delete();

    expect($user->roles()->count())->toBe(
        0,
        'BelongsToMany query should return fresh data after its pivot table is written',
    );
});

test('join sub query cache tags are built without crashing', function () {
    $subQuery = DB::table('books')
        ->select('author_id')
        ->groupBy('author_id');

    $query = (new Author)
        ->select('authors.*')
        ->joinSub($subQuery, 'authored', 'authored.author_id', '=', 'authors.id');

    $tags = (new CacheTags(
        $query->getEagerLoads(),
        $query->getModel(),
        $query
    ))->make();

    expect(collect($tags)->contains(function ($tag) {
            return str_contains($tag, (new Str)->slug(Author::class));
        }))->toBeTrue('Tags should include the primary model class tag');
});

test('join sub query results are cached', function () {
    $query = (new Author)
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');
    $key = sha1((new ReflectionMethod($query, 'makeCacheKey'))->invoke($query));
    $authors = $query->get();

    $cached = $this->cache()
        ->tags((new ReflectionMethod($query, 'makeCacheTags'))->invoke($query))
        ->get($key);

    expect($authors->count())->toBeGreaterThan(0);
    expect($cached)->not->toBeNull('The joinSub query should have been cached');
    expect($cached['value']->pluck('id'))->toEqual($authors->pluck('id'));
});

test('join sub query cache tags contain the subquery table tag', function () {
    $query = (new Author)
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

    // joinSub() should tag the table its subquery reads from
    expect(joinSubTags($query))->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('left join sub query cache tags contain the subquery table tag', function () {
    $query = (new Author)
        ->select('authors.*')
        ->leftJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

    // leftJoinSub() should tag the table its subquery reads from
    expect(joinSubTags($query))->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('join sub query cache tags contain the subquery table tag for a closure subquery', function () {
    $query = (new Author)
        ->select('authors.*')
        ->joinSub(
            function ($subQuery) {
                $subQuery->from('books')
                    ->select('author_id')
                    ->groupBy('author_id');
            },
            'authored',
            'authored.author_id',
            '=',
            'authors.id'
        );

    // A Closure subquery should be recorded the same way a builder is
    expect(joinSubTags($query))->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('join sub query cache is invalidated when the subquery table is written', function () {
    // An author with no books is invisible to the inner-joined subquery,
    // so giving them one changes the row count the query returns.
    $authorWithoutBooks = Author::factory()->create();

    $before = (new Author)
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
        ->get();

    Book::factory()->create(['author_id' => $authorWithoutBooks->id]);

    $afterCachedRead = (new Author)
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
        ->get();
    $liveTruth = (new UncachedAuthor)
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
        ->get();

    expect($liveTruth)->toHaveCount($before->count() + 1);
    expect($afterCachedRead->pluck('id'))->toEqual(
        $liveTruth->pluck('id'),
        'A write to the subquery table should invalidate the cached joinSub query',
    );
});

// The case that justifies the base query builder existing at all. Eloquent
// defers some of its own subquery joins into a beforeQuery() callback, and
// that callback is handed the query builder, never the Eloquent builder
// wrapping it — so a hook on CachedBuilder never sees the join. Before this,
// the query below tagged only "author" and "authors"; a write to books left
// the cached rows in place.
test('deferred join sub query cache tags contain the subquery table tag', function () {
    $query = withDeferredJoinSub(new Author);
    $tags = (new ReflectionMethod($query, 'makeCacheTags'))
        ->invoke($query);

    // A subquery join added by a beforeQuery callback should tag the table it reads
    expect($tags)->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('deferred join sub query cache is invalidated when the subquery table is written', function () {
    // An author with no books is invisible to the inner-joined subquery, so
    // giving them one changes the row count the query returns.
    $authorWithoutBooks = Author::factory()->create();

    $before = withDeferredJoinSub(new Author)->get();

    Book::factory()->create(['author_id' => $authorWithoutBooks->id]);

    $afterCachedRead = withDeferredJoinSub(new Author)->get();
    $liveTruth = withDeferredJoinSub(new UncachedAuthor)->get();

    expect($liveTruth)->toHaveCount($before->count() + 1);
    expect($afterCachedRead->pluck('id'))->toEqual(
        $liveTruth->pluck('id'),
        'A write to the subquery table should invalidate the deferred joinSub query',
    );
});

// A tag is worth nothing if the builder it came from replaced one the
// consumer supplied. The package only swaps in its own base query builder
// when the model is using Laravel's.
test('a custom base query builder is not replaced', function () {
    $builder = (new ReflectionMethod(new AuthorWithCustomBaseBuilder, 'newBaseQueryBuilder'))
        ->invoke(new AuthorWithCustomBaseBuilder);

    expect($builder)->toBeInstanceOf(AuthorBaseQueryBuilder::class);
    expect($builder)->not->toBeInstanceOf(CachedQueryBuilder::class);
});

test('the default base query builder is the recording one', function () {
    $builder = (new ReflectionMethod(new Author, 'newBaseQueryBuilder'))
        ->invoke(new Author);

    expect($builder)->toBeInstanceOf(CachedQueryBuilder::class);
});

// Not an ofMany() test on purpose. An ofMany() relation already tags the
// related model's own table through that model, so its tags are identical
// with and without any recording — a test for it would pass before the fix
// as readily as after. The deferred joinSub above is the case that changes.
test('of many relation builds cache tags without raising a type error', function () {
    $author = (new Author)->first();
    $relation = $author->newestBook();

    // Executing the relation first runs the deferred beforeQuery callback
    // that builds the ofMany() join, so the JoinClause holds a compiled
    // Expression by the time the tags are made — the shape that used to
    // raise a TypeError inside stripos().
    $relation->first();

    $builder = $relation->getQuery();
    $tags = (new ReflectionMethod($builder, 'makeCacheTags'))
        ->invoke($builder);

    // The ofMany() subquery reads from the related model's own table, so
    // that table is already tagged through the model; unlike a hand-written
    // joinSub() against an unrelated table, nothing has to be recovered
    // from the compiled expression here.
    expect($tags)->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

function authoredSubQuery()
{
    return DB::table('books')
        ->select('author_id')
        ->groupBy('author_id');
}

function joinSubTags($query): array
{
    return (new CacheTags(
        $query->getEagerLoads(),
        $query->getModel(),
        $query
    ))->make();
}

function withDeferredJoinSub($model)
{
    return $model
        ->select('authors.*')
        ->beforeQuery(function ($base) {
            $base->joinSub(
                DB::table('books')
                    ->select('author_id')
                    ->groupBy('author_id'),
                'authored',
                'authored.author_id',
                '=',
                'authors.id'
            );
        });
}
