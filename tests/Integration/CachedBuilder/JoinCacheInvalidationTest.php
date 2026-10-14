<?php

use GeneaLabs\LaravelModelCaching\CachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorBaseQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorDelegatingToModelCaching;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithBaseBuilderTrait;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithClassBodyBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseAndEloquentBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithMacroEloquentBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableRoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

test('join query cache tags contain joined table tag', function () {
    $query = (new Author)
        ->select('authors.*', 'books.title')
        ->join('books', 'books.author_id', '=', 'authors.id');

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

    expect($user->roles()->count())->toBeGreaterThan(0);

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

    expect(joinSubTags($query))->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('left join sub query cache tags contain the subquery table tag', function () {
    $query = (new Author)
        ->select('authors.*')
        ->leftJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

    expect(joinSubTags($query))->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('right join sub query cache tags contain the subquery table tag', function () {
    $query = (new Author)
        ->select('authors.*')
        ->rightJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

    expect(joinSubTags($query))->toContain(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        )
        ->and(collect($query->getQuery()->joins)->pluck('type')->all())->toBe(['right']);
});

test('right join sub query cache is invalidated when the subquery table is written', function () {
    $authorWithoutBooks = Author::factory()->create();
    $before = (new Author)
        ->select('authors.*')
        ->rightJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
        ->get();

    Book::factory()->create(['author_id' => $authorWithoutBooks->id]);

    $afterCachedRead = (new Author)
        ->select('authors.*')
        ->rightJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
        ->get();
    $liveTruth = (new UncachedAuthor)
        ->select('authors.*')
        ->rightJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
        ->get();

    expect($liveTruth)->toHaveCount($before->count() + 1)
        ->and($afterCachedRead->pluck('id'))->toEqual($liveTruth->pluck('id'));
});

test('lateral join cache tags contain the subquery table tag', function () {
    $query = (new Author)
        ->select('authors.*')
        ->joinLateral(latestBookSubQuery(), 'latest_book');

    expect(joinSubTags($query))->toContain(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        )
        ->and(collect($query->getQuery()->joins)->pluck('type')->all())->toBe(['inner']);
});

test('left lateral join cache tags contain the subquery table tag', function () {
    $query = (new Author)
        ->select('authors.*')
        ->leftJoinLateral(latestBookSubQuery(), 'latest_book');

    expect(joinSubTags($query))->toContain(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        )
        ->and(collect($query->getQuery()->joins)->pluck('type')->all())->toBe(['left']);
});

test('join sub query cache tags contain the subquery table tag for a relation subquery', function () {
    $subQuery = (new Author)
        ->first()
        ->books()
        ->select('author_id')
        ->groupBy('author_id');
    $query = (new Author)
        ->select('authors.*')
        ->joinSub($subQuery, 'authored', 'authored.author_id', '=', 'authors.id');

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

    expect(joinSubTags($query))->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('join sub query cache is invalidated when the subquery table is written', function () {
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

test('deferred join sub query cache tags contain the subquery table tag', function () {
    $query = withDeferredJoinSub(new Author);
    $tags = (new ReflectionMethod($query, 'makeCacheTags'))
        ->invoke($query);

    expect($tags)->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});

test('deferred join sub query cache is invalidated when the subquery table is written', function () {
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

test('a custom base query builder is not replaced', function () {
    $builder = (new AuthorWithCustomBaseBuilder)->newQuery()->getQuery();

    expect($builder)->toBeInstanceOf(AuthorBaseQueryBuilder::class);
    expect($builder)->not->toBeInstanceOf(CachedQueryBuilder::class);
});

test('the default base query builder is the recording one', function () {
    $builder = (new Author)->newQuery()->getQuery();

    expect($builder)->toBeInstanceOf(CachedQueryBuilder::class);
});

test('fromBase keeps the sql and bindings of a query with clauses', function (): void {
    $query = DB::table('authors')
        ->select('authors.*')
        ->join('books', 'books.author_id', '=', 'authors.id')
        ->where('authors.name', 'like', 'A%')
        ->whereIn('books.title', ['First', 'Second'])
        ->orderBy('authors.id');

    $recording = CachedQueryBuilder::fromBase($query);
    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect($recording)->toBeInstanceOf(CachedQueryBuilder::class);
    expect($recording->toSql())->toBe($sql);
    expect($recording->getBindings())->toBe($bindings);
});

test('fromBase keeps a custom base query builder', function (): void {
    $query = (new AuthorWithCustomBaseBuilder)
        ->newQuery()
        ->getQuery();

    expect(CachedQueryBuilder::fromBase($query))->toBe($query);
});

test('the recording base query builder is used when caching is disabled', function (): void {
    config(['laravel-model-caching.enabled' => false]);
    $query = (new Author)
        ->newQuery()
        ->getQuery();

    expect($query)->toBeInstanceOf(CachedQueryBuilder::class);
});

test('a class body new eloquent builder gets the recording base builder', function (): void {
    $query = (new AuthorWithClassBodyBuilder)
        ->newQuery()
        ->getQuery();

    expect($query)->toBeInstanceOf(CachedQueryBuilder::class);
});

test('cachable combines with a trait defining new base query builder', function () {
    $builder = (new AuthorWithBaseBuilderTrait)->newQuery()->getQuery();

    expect($builder)->toBeInstanceOf(AuthorBaseQueryBuilder::class);

    $authors = (new AuthorWithBaseBuilderTrait)->where('id', '<', 5)->get();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $cached = (new AuthorWithBaseBuilderTrait)->where('id', '<', 5)->get();

    expect($authors)->not->toBeEmpty();
    expect($cached->pluck('id'))->toEqual($authors->pluck('id'));
    expect(DB::getQueryLog())->toBe([], 'The second read should be served from the cache');
});

test('a subquery read refreshes after a write', function (string $model, Closure $query): void {
    expectReadToRefreshAfterABookIsAdded(
            fn () => $query(new $model)->get(),
            fn () => $query(new UncachedAuthor)->get(),
        );
})->with([
    'the default builder' => Author::class,
    'a class body new eloquent builder' => AuthorWithClassBodyBuilder::class,
    'a model delegating to model caching' => AuthorDelegatingToModelCaching::class,
    'a custom base builder from a trait' => AuthorWithBaseBuilderTrait::class,
    'a custom base builder from a parent class' => AuthorWithCustomBaseBuilder::class,
])->with([
    'join sub' => fn (Model $model) => $model
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id'),
    'cross join sub' => fn (Model $model) => $model
        ->select('authors.*')
        ->crossJoinSub(authoredSubQuery(), 'authored')
        ->whereColumn('authored.author_id', 'authors.id'),
    'from sub' => fn (Model $model) => $model
        ->fromSub(bookAuthorsSubQuery(), 'authors'),
    'from sub by named arguments' => fn (Model $model) => $model
        ->fromSub(query: bookAuthorsSubQuery(), as: 'authors'),
    'where in sub' => fn (Model $model) => $model
        ->whereIn('authors.id', bookAuthorIdsSubQuery()),
    'where in sub in another letter case' => fn (Model $model) => $model
        ->whereIN('authors.id', bookAuthorIdsSubQuery()),
    'join sub by named arguments' => fn (Model $model) => $model
        ->select('authors.*')
        ->joinSub(
            query: authoredSubQuery(),
            as: 'authored',
            first: 'authored.author_id',
            operator: '=',
            second: 'authors.id',
        ),
    'where in sub by named arguments' => fn (Model $model) => $model
        ->whereIn(column: 'authors.id', values: bookAuthorIdsSubQuery()),
    'where in closure' => fn (Model $model) => $model
        ->whereIn('authors.id', fn ($query) => $query
            ->select('author_id')
            ->from('books')),
    'nested where in sub' => fn (Model $model) => $model
        ->where(fn ($query) => $query
            ->whereIn('authors.id', bookAuthorIdsSubQuery())),
]);

test('a deferred subquery read refreshes', function (string $model, Closure $query): void {
    expectReadToRefreshAfterABookIsAdded(
            fn () => $query(new $model)->get(),
            fn () => $query(new UncachedAuthor)->get(),
        );
})->with([
    'the default builder' => Author::class,
    'a class body new eloquent builder' => AuthorWithClassBodyBuilder::class,
    'a model delegating to model caching' => AuthorDelegatingToModelCaching::class,
])->with([
    'join sub' => fn (Model $model) => withDeferredJoinSub($model),
    'where in sub' => fn (Model $model) => $model
        ->beforeQuery(fn ($query) => $query->whereIn('authors.id', bookAuthorIdsSubQuery())),
    'from sub' => fn (Model $model) => $model
        ->beforeQuery(fn ($query) => $query->fromSub(bookAuthorsSubQuery(), 'authors')),
]);

test('a straight join sub SQLite and Postgres reject is tagged', function (string $model): void {
    $query = (new $model)
        ->select('authors.*')
        ->straightJoinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');
    $tags = joinSubTags($query);
    $cache = $this->cache()
        ->tags($tags);
    $cache->put('straight-join-probe', 'cached');

    Book::factory()
        ->create();

    expect($cache->get('straight-join-probe'))->toBeNull();
})->with([
    'a custom base builder from a trait' => AuthorWithBaseBuilderTrait::class,
    'a custom base builder from a parent class' => AuthorWithCustomBaseBuilder::class,
])->skip(
    ! method_exists(QueryBuilder::class, "straightJoinSub"),
    "Method " . QueryBuilder::class . "::straightJoinSub() does not exist",
);

test('a throwing before query callback runs once per tag build', function (): void {
    $calls = 0;
    $query = (new Author)->beforeQuery(function () use (&$calls): void {
        $calls++;

        throw new RuntimeException('The callback failed.');
    });

    joinSubTags($query);

    expect($calls)->toBe(1);
});

test('an on clone callback subquery read refreshes after a write', function (): void {
    $query = (new AuthorWithBaseBuilderTrait)
        ->select('authors.*')
        ->onClone(fn ($clone) => $clone
            ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id'));

    expectReadToRefreshAfterABookIsAdded(
            fn () => (clone $query)->get(),
            fn () => (new UncachedAuthor)
                ->select('authors.*')
                ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
                ->get(),
        );
});

test('a subquery call with a wildcard method name is left alone', function (): void {
    $recorder = CachedQueryBuilder::recorderFor(DB::table('authors'));

    expect($recorder->recordSubqueryCall('*', ['authors.id']))->toBe(['authors.id']);
});

test('a custom builder method subquery refreshes', function (string $method, Closure $query): void {
    $model = new AuthorWithCustomBaseAndEloquentBuilder;

    expectReadToRefreshAfterABookIsAdded(
            fn () => $model->{$method}()
                ->get(),
            fn () => $query(new UncachedAuthor)->get(),
        );
})->with([
    'join sub' => ['joinAuthoredBooks', fn (Model $model) => $model
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')],
    'from sub' => ['fromAuthoredBooks', fn (Model $model) => $model
        ->fromSub(bookAuthorsSubQuery(), 'authors')],
    'where in sub' => ['whereAuthoredBooks', fn (Model $model) => $model
        ->whereIn('authors.id', bookAuthorIdsSubQuery())],
    'join sub by named arguments' => ['joinAuthoredBooksByName', fn (Model $model) => $model
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')],
    'from sub by named arguments' => ['fromAuthoredBooksByName', fn (Model $model) => $model
        ->fromSub(bookAuthorsSubQuery(), 'authors')],
    'where in sub by named arguments' => ['whereAuthoredBooksByName', fn (Model $model) => $model
        ->whereIn('authors.id', bookAuthorIdsSubQuery())],
    'cross join sub' => ['crossJoinAuthoredBooks', fn (Model $model) => $model
        ->select('authors.*')
        ->crossJoinSub(authoredSubQuery(), 'authored')
        ->whereColumn('authored.author_id', 'authors.id')],
]);

test('a custom builder method named like a subquery method is tagged', function (): void {
    $authorWithoutBooks = Author::factory()
        ->create();
    $read = fn (Model $model) => $model
        ->where('authors.id', 0)
        ->orWhereNotIn('authors.id', bookAuthorIdsSubQuery())
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
    $before = $read(new AuthorWithCustomBaseAndEloquentBuilder);
    expectServedFromCache(fn () => $read(new AuthorWithCustomBaseAndEloquentBuilder));
    $authorId = $authorWithoutBooks->getKey();

    Book::factory()
        ->create(['author_id' => $authorId]);

    expect($before)->toContain($authorId);
    expect($read(new AuthorWithCustomBaseAndEloquentBuilder))->toBe($read(new UncachedAuthor));
});

test('a local macro named like a subquery method keeps running and is tagged', function (): void {
    expectReadToRefreshAfterABookIsAdded(
            fn () => (new AuthorWithMacroEloquentBuilder)->whereAuthoredBooks()
                ->get(),
            fn () => (new UncachedAuthor)
                ->whereIn('authors.id', bookAuthorIdsSubQuery())
                ->where('authors.id', '!=', 1)
                ->get(),
        );
});

test('a local macro registered after the builder is made is tagged', function (): void {
    expectReadToRefreshAfterABookIsAdded(
            fn () => (new AuthorWithCustomBaseAndEloquentBuilder)
                ->excludingTheFirstAuthorFromWhereIn()
                ->whereAuthoredBooks()
                ->get(),
            fn () => (new UncachedAuthor)
                ->whereIn('authors.id', bookAuthorIdsSubQuery())
                ->where('authors.id', '!=', 1)
                ->get(),
        );
});

test('a cloned custom builder method subquery read refreshes after a write', function (): void {
    $query = (new AuthorWithCustomBaseAndEloquentBuilder)->newQuery();

    expectReadToRefreshAfterABookIsAdded(
            fn () => (clone $query)->joinAuthoredBooks()
                ->get(),
            fn () => (new UncachedAuthor)
                ->select('authors.*')
                ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
                ->get(),
        );
});

test('a cloned custom base builder subquery read refreshes after a write', function (): void {
    $query = (new AuthorWithBaseBuilderTrait)
        ->select('authors.*')
        ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

    expectReadToRefreshAfterABookIsAdded(
            fn () => (clone $query)->get(),
            fn () => (new UncachedAuthor)
                ->select('authors.*')
                ->joinSub(authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
                ->get(),
        );
});

test('of many relation builds cache tags without raising a type error', function () {
    $author = (new Author)->first();
    $relation = $author->newestBook();

    $relation->first();

    $builder = $relation->getQuery();
    $tags = (new ReflectionMethod($builder, 'makeCacheTags'))
        ->invoke($builder);

    expect($tags)->toContain(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    );
});
