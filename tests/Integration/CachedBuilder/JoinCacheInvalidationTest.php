<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableRoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
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
}
