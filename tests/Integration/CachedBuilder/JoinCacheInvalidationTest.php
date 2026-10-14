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
