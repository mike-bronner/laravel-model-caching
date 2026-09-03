<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

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
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;

class JoinCacheInvalidationTest extends IntegrationTestCase
{
    public function testJoinQueryCacheTagsContainJoinedTableTag()
    {
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

        $this->assertTrue(
            collect($tags)->contains(function ($tag) {
                return str_contains($tag, (new Str)->slug(Author::class));
            }),
            'Tags should include the primary model class tag'
        );

        $this->assertTrue(
            collect($tags)->contains(function ($tag) use ($bookTableTag) {
                return str_contains($tag, $bookTableTag);
            }),
            'Tags should include the joined table tag'
        );
    }

    public function testJoinQueryCacheInvalidatesWhenJoinedModelUpdated()
    {
        $results = (new Author)
            ->select('authors.*', 'books.title AS book_title')
            ->join('books', 'books.author_id', '=', 'authors.id')
            ->get();

        $this->assertGreaterThan(0, $results->count());

        $book = (new Book)->first();
        $newTitle = 'Updated Title ' . uniqid();
        $book->title = $newTitle;
        $book->save();

        $freshResults = (new Author)
            ->select('authors.*', 'books.title AS book_title')
            ->join('books', 'books.author_id', '=', 'authors.id')
            ->get();

        $matchingRows = $freshResults->where('book_title', $newTitle);
        $this->assertGreaterThan(
            0,
            $matchingRows->count(),
            'Join query should return fresh data after the joined model is updated'
        );
    }

    public function testLeftJoinQueryCacheInvalidatesWhenJoinedModelUpdated()
    {
        $results = (new Author)
            ->select('authors.*', 'books.title AS book_title')
            ->leftJoin('books', 'books.author_id', '=', 'authors.id')
            ->get();

        $this->assertGreaterThan(0, $results->count());

        $book = (new Book)->first();
        $newTitle = 'Left Join Updated ' . uniqid();
        $book->title = $newTitle;
        $book->save();

        $freshResults = (new Author)
            ->select('authors.*', 'books.title AS book_title')
            ->leftJoin('books', 'books.author_id', '=', 'authors.id')
            ->get();

        $matchingRows = $freshResults->where('book_title', $newTitle);
        $this->assertGreaterThan(
            0,
            $matchingRows->count(),
            'Left join query should return fresh data after the joined model is updated'
        );
    }

    public function testRelationQueryCacheTagsContainPivotTableTag()
    {
        // Via makeCacheTags(), the only caller the caching path actually uses
        $relation = (new Book)
            ->first()
            ->stores();
        $method = new ReflectionMethod($relation, 'makeCacheTags');
        $method->setAccessible(true);
        $tags = $method->invoke($relation);

        $pivotTableTag = (new Str)->slug('book_store');

        $this->assertTrue(
            collect($tags)->contains(function ($tag) {
                return str_contains($tag, (new Str)->slug(Store::class));
            }),
            'Tags should include the related model class tag',
        );

        $this->assertTrue(
            collect($tags)->contains(function ($tag) use ($pivotTableTag) {
                return str_contains($tag, $pivotTableTag);
            }),
            'Tags should include the pivot table joined by the relation',
        );
    }

    public function testMorphToManyQueryCacheTagsContainPivotTableTag()
    {
        $relation = (new Post)
            ->first()
            ->tags();
        $method = new ReflectionMethod($relation, 'makeCacheTags');
        $method->setAccessible(true);
        $tags = $method->invoke($relation);

        $pivotTableTag = (new Str)->slug('taggables');

        $this->assertTrue(
            collect($tags)->contains(function ($tag) use ($pivotTableTag) {
                return str_contains($tag, $pivotTableTag);
            }),
            'Tags should include the pivot table joined by the polymorphic relation',
        );
    }

    public function testBelongsToManyCacheInvalidatesWhenPivotTableIsWrittenByModel()
    {
        $userId = (int) DB::table('role_user')
            ->value('user_id');
        $user = (new User)
            ->find($userId);

        // Prime the read: its tags name Role and roles, the delete writes role_user
        $this->assertGreaterThan(0, $user->roles()->count());

        // Bulk delete through a cachable model whose table IS the pivot, so no
        // pivot events fire and only the executed query's tags are invalidated
        (new CachableRoleUser)
            ->newQuery()
            ->where('user_id', $userId)
            ->delete();

        $this->assertSame(
            0,
            $user->roles()->count(),
            'BelongsToMany query should return fresh data after its pivot table is written',
        );
    }

    public function testJoinSubQueryCacheTagsAreBuiltWithoutCrashing()
    {
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

        $this->assertTrue(
            collect($tags)->contains(function ($tag) {
                return str_contains($tag, (new Str)->slug(Author::class));
            }),
            'Tags should include the primary model class tag'
        );
    }

    public function testJoinSubQueryResultsAreCached()
    {
        $query = (new Author)
            ->select('authors.*')
            ->joinSub($this->authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');
        $key = sha1((new ReflectionMethod($query, 'makeCacheKey'))->invoke($query));
        $authors = $query->get();

        $cached = $this->cache()
            ->tags((new ReflectionMethod($query, 'makeCacheTags'))->invoke($query))
            ->get($key);

        $this->assertGreaterThan(0, $authors->count());
        $this->assertNotNull($cached, 'The joinSub query should have been cached');
        $this->assertEquals($authors->pluck('id'), $cached['value']->pluck('id'));
    }

    private function authoredSubQuery()
    {
        return DB::table('books')
            ->select('author_id')
            ->groupBy('author_id');
    }

    private function joinSubTags($query) : array
    {
        return (new CacheTags(
            $query->getEagerLoads(),
            $query->getModel(),
            $query
        ))->make();
    }

    public function testJoinSubQueryCacheTagsContainTheSubqueryTableTag()
    {
        $query = (new Author)
            ->select('authors.*')
            ->joinSub($this->authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

        $this->assertContains(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            $this->joinSubTags($query),
            'joinSub() should tag the table its subquery reads from',
        );
    }

    public function testLeftJoinSubQueryCacheTagsContainTheSubqueryTableTag()
    {
        $query = (new Author)
            ->select('authors.*')
            ->leftJoinSub($this->authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id');

        $this->assertContains(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            $this->joinSubTags($query),
            'leftJoinSub() should tag the table its subquery reads from',
        );
    }

    public function testJoinSubQueryCacheTagsContainTheSubqueryTableTagForAClosureSubquery()
    {
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

        $this->assertContains(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            $this->joinSubTags($query),
            'A Closure subquery should be recorded the same way a builder is',
        );
    }

    public function testJoinSubQueryCacheIsInvalidatedWhenTheSubqueryTableIsWritten()
    {
        // An author with no books is invisible to the inner-joined subquery,
        // so giving them one changes the row count the query returns.
        $authorWithoutBooks = Author::factory()->create();

        $before = (new Author)
            ->select('authors.*')
            ->joinSub($this->authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
            ->get();

        Book::factory()->create(['author_id' => $authorWithoutBooks->id]);

        $afterCachedRead = (new Author)
            ->select('authors.*')
            ->joinSub($this->authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
            ->get();
        $liveTruth = (new UncachedAuthor)
            ->select('authors.*')
            ->joinSub($this->authoredSubQuery(), 'authored', 'authored.author_id', '=', 'authors.id')
            ->get();

        $this->assertCount($before->count() + 1, $liveTruth);
        $this->assertEquals(
            $liveTruth->pluck('id'),
            $afterCachedRead->pluck('id'),
            'A write to the subquery table should invalidate the cached joinSub query',
        );
    }

    private function withDeferredJoinSub($model)
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

    // The case that justifies the base query builder existing at all. Eloquent
    // defers some of its own subquery joins into a beforeQuery() callback, and
    // that callback is handed the query builder, never the Eloquent builder
    // wrapping it — so a hook on CachedBuilder never sees the join. Before this,
    // the query below tagged only "author" and "authors"; a write to books left
    // the cached rows in place.
    public function testDeferredJoinSubQueryCacheTagsContainTheSubqueryTableTag()
    {
        $query = $this->withDeferredJoinSub(new Author);
        $tags = (new ReflectionMethod($query, 'makeCacheTags'))
            ->invoke($query);

        $this->assertContains(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            $tags,
            'A subquery join added by a beforeQuery callback should tag the table it reads',
        );
    }

    public function testDeferredJoinSubQueryCacheIsInvalidatedWhenTheSubqueryTableIsWritten()
    {
        // An author with no books is invisible to the inner-joined subquery, so
        // giving them one changes the row count the query returns.
        $authorWithoutBooks = Author::factory()->create();

        $before = $this->withDeferredJoinSub(new Author)->get();

        Book::factory()->create(['author_id' => $authorWithoutBooks->id]);

        $afterCachedRead = $this->withDeferredJoinSub(new Author)->get();
        $liveTruth = $this->withDeferredJoinSub(new UncachedAuthor)->get();

        $this->assertCount($before->count() + 1, $liveTruth);
        $this->assertEquals(
            $liveTruth->pluck('id'),
            $afterCachedRead->pluck('id'),
            'A write to the subquery table should invalidate the deferred joinSub query',
        );
    }

    // A tag is worth nothing if the builder it came from replaced one the
    // consumer supplied. The package only swaps in its own base query builder
    // when the model is using Laravel's.
    public function testACustomBaseQueryBuilderIsNotReplaced()
    {
        $builder = (new ReflectionMethod(new AuthorWithCustomBaseBuilder, 'newBaseQueryBuilder'))
            ->invoke(new AuthorWithCustomBaseBuilder);

        $this->assertInstanceOf(AuthorBaseQueryBuilder::class, $builder);
        $this->assertNotInstanceOf(CachedQueryBuilder::class, $builder);
    }

    public function testTheDefaultBaseQueryBuilderIsTheRecordingOne()
    {
        $builder = (new ReflectionMethod(new Author, 'newBaseQueryBuilder'))
            ->invoke(new Author);

        $this->assertInstanceOf(CachedQueryBuilder::class, $builder);
    }

    // Not an ofMany() test on purpose. An ofMany() relation already tags the
    // related model's own table through that model, so its tags are identical
    // with and without any recording — a test for it would pass before the fix
    // as readily as after. The deferred joinSub above is the case that changes.
    public function testOfManyRelationBuildsCacheTagsWithoutRaisingATypeError()
    {
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
        $this->assertContains(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            $tags,
        );
    }
}
