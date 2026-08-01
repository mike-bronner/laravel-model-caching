<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CachableRoleUser;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;

/**
 * Regression tests for issue #610: relation queries that join a pivot or
 * intermediate table were never tagged with that table, so a write to it
 * could not invalidate the cached relation read.
 */
class RelationJoinCacheInvalidationTest extends IntegrationTestCase
{
    private function makeCacheTags(object $subject): array
    {
        $method = new ReflectionMethod($subject, 'makeCacheTags');
        $method->setAccessible(true);

        return $method->invoke($subject);
    }

    private function assertTagsContainTable(array $tags, string $table): void
    {
        $suffix = ':' . (new Str)->slug($table);

        $this->assertTrue(
            collect($tags)->contains(fn ($tag) => str_ends_with($tag, $suffix)),
            "Relation cache tags should contain a tag for the joined table '{$table}'. Got: "
                . implode(', ', $tags)
        );
    }

    public function testBelongsToManyRelationTagsContainPivotTable(): void
    {
        $tags = $this->makeCacheTags((new Book)->first()->stores());

        $this->assertTagsContainTable($tags, 'book_store');
        $this->assertTagsContainTable($tags, 'stores');
    }

    public function testMorphToManyRelationTagsContainPivotTable(): void
    {
        $tags = $this->makeCacheTags((new Post)->first()->tags());

        $this->assertTagsContainTable($tags, 'taggables');
        $this->assertTagsContainTable($tags, 'tags');
    }

    /**
     * CachedHasOneThrough and CachedHasManyThrough override makeCacheTags() and
     * already unwrap the Eloquent builder, so the trait fix does not change them.
     * This guards that behavior against regression.
     */
    public function testHasOneThroughRelationStillTagsIntermediateTable(): void
    {
        $tags = $this->makeCacheTags((new Supplier)->first()->history());

        $this->assertTagsContainTable($tags, 'users');
    }

    public function testPlainBuilderJoinTagsStillResolveThroughMakeCacheTags(): void
    {
        $tags = $this->makeCacheTags(
            (new Author)
                ->select('authors.*', 'books.title')
                ->join('books', 'books.author_id', '=', 'authors.id')
        );

        $this->assertTagsContainTable($tags, 'books');
        $this->assertTagsContainTable($tags, 'authors');
    }

    public function testCachedBelongsToManyReadIsInvalidatedByPivotTableWrite(): void
    {
        $userId = (int) DB::table('role_user')->value('user_id');

        $primedCount = (new User)->find($userId)->roles()->count();
        $this->assertGreaterThan(0, $primedCount);

        (new CachableRoleUser)
            ->where('user_id', $userId)
            ->first()
            ->delete();

        $this->assertSame(
            $primedCount - 1,
            (new User)->find($userId)->roles()->count(),
            'A cached belongsToMany read should be invalidated by a write to its pivot table.'
        );
    }
}
