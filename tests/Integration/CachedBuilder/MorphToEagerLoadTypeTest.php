<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionMethod;

/**
 * Tests for issue #539: Eager-loaded morphTo resolves wrong type from cache.
 */
class MorphToEagerLoadTypeTest extends IntegrationTestCase
{
    /**
     * AC1: Eager-loading a morphTo returns the correct polymorphic concrete
     * type on both cache miss and cache hit.
     */
    public function testMorphToReturnsCorrectConcreteTypeOnCacheHit(): void
    {
        $post = (new Post)->first();
        $book = (new Book)->first();

        $postComment = Comment::create([
            'commentable_id' => $post->id,
            'commentable_type' => Post::class,
            'description' => 'Comment on post',
            'subject' => 'Post comment',
        ]);
        $bookComment = Comment::create([
            'commentable_id' => $book->id,
            'commentable_type' => Book::class,
            'description' => 'Comment on book',
            'subject' => 'Book comment',
        ]);

        $this->cache()->flush();

        // First load — cache miss
        $comments = (new Comment)
            ->with('commentable')
            ->whereIn('id', [$postComment->id, $bookComment->id])
            ->orderBy('id')
            ->get();

        $postCommentResult = $comments->firstWhere('id', $postComment->id);
        $bookCommentResult = $comments->firstWhere('id', $bookComment->id);

        $this->assertInstanceOf(Post::class, $postCommentResult->commentable);
        $this->assertInstanceOf(Book::class, $bookCommentResult->commentable);

        // Second load — cache hit
        $cachedComments = (new Comment)
            ->with('commentable')
            ->whereIn('id', [$postComment->id, $bookComment->id])
            ->orderBy('id')
            ->get();

        $cachedPostComment = $cachedComments->firstWhere('id', $postComment->id);
        $cachedBookComment = $cachedComments->firstWhere('id', $bookComment->id);

        $this->assertInstanceOf(
            Post::class,
            $cachedPostComment->commentable,
            'morphTo should return Post on cache hit, got: ' . get_class($cachedPostComment->commentable)
        );
        $this->assertInstanceOf(
            Book::class,
            $cachedBookComment->commentable,
            'morphTo should return Book on cache hit, got: ' . get_class($cachedBookComment->commentable)
        );
    }

    /**
     * AC2: Morph target methods are callable after a cache hit (regression test).
     */
    public function testMorphTargetMethodsCallableAfterCacheHit(): void
    {
        $post = (new Post)->first();

        $comment = Comment::create([
            'commentable_id' => $post->id,
            'commentable_type' => Post::class,
            'description' => 'Test morph method call',
            'subject' => 'Method test',
        ]);

        $this->cache()->flush();

        // First load — cache miss
        $result = (new Comment)
            ->with('commentable.tags')
            ->where('id', $comment->id)
            ->first();

        $this->assertInstanceOf(Post::class, $result->commentable);
        $this->assertTrue($result->commentable->relationLoaded('tags'));

        // Second load — cache hit
        $cached = (new Comment)
            ->with('commentable.tags')
            ->where('id', $comment->id)
            ->first();

        $this->assertInstanceOf(
            Post::class,
            $cached->commentable,
            'morphTo should return Post on cache hit for nested eager load'
        );
        $this->assertTrue(
            $cached->commentable->relationLoaded('tags'),
            'Nested relation "tags" should be loaded on cache hit'
        );

        // Key regression: calling a method on the morph target must not throw
        $tags = $cached->commentable->tags;
        $this->assertNotNull($tags);
    }

    public function testUpdatingPostInvalidatesCommentWithCommentableCache(): void
    {
        $post = (new Post)->first();

        $comment = Comment::create([
            'commentable_id' => $post->id,
            'commentable_type' => Post::class,
            'description' => 'Original comment',
            'subject' => 'Invalidation test',
        ]);

        $this->cache()->flush();

        $cached = (new Comment)
            ->with('commentable')
            ->where('id', $comment->id)
            ->first();

        $this->assertSame($post->title, $cached->commentable->title);

        $post->title = 'Updated post title ' . uniqid();
        $post->save();

        $fresh = (new Comment)
            ->with('commentable')
            ->where('id', $comment->id)
            ->first();

        $this->assertSame(
            $post->title,
            $fresh->commentable->title,
            'Comment cache should be invalidated when an eager-loaded Post is updated.'
        );
    }

    public function testUpdatingBookInvalidatesCommentWithCommentableCache(): void
    {
        $book = (new Book)->first();
        $originalTitle = $book->title;

        $comment = Comment::create([
            'commentable_id' => $book->id,
            'commentable_type' => Book::class,
            'description' => 'Comment on book',
            'subject' => 'Book invalidation test',
        ]);

        $this->cache()->flush();

        $cached = (new Comment)
            ->with('commentable')
            ->where('id', $comment->id)
            ->first();

        $this->assertSame($originalTitle, $cached->commentable->title);

        $book->title = 'Updated book title ' . uniqid();
        $book->save();

        $fresh = (new Comment)
            ->with('commentable')
            ->where('id', $comment->id)
            ->first();

        $this->assertSame(
            $book->title,
            $fresh->commentable->title,
            'Comment cache should be invalidated when an eager-loaded Book is updated.'
        );
    }

    // With a morph map registered, an eager-loaded morphTo is tagged with every
    // mapped class that exists, in map order, and a mapped name that is not a
    // class is skipped. The map is global, so it is cleared again afterwards.
    public function testMorphMapTagsEveryMappedClassThatExists(): void
    {
        Relation::morphMap([
            "post" => Post::class,
            "missing" => "GeneaLabs\\LaravelModelCaching\\Tests\\Fixtures\\DoesNotExist",
            "book" => Book::class,
        ], false);

        try {
            $builder = (new Comment)->with("commentable");
            $tags = (new ReflectionMethod($builder, "makeCacheTags"))->invoke($builder);
        } finally {
            Relation::morphMap([], false);
        }

        $this->assertSame([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
        ], $tags);
    }
}
