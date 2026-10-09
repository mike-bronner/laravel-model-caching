<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Tests for issue #539: Eager-loaded morphTo resolves wrong type from cache.
 */

/**
 * AC1: Eager-loading a morphTo returns the correct polymorphic concrete
 * type on both cache miss and cache hit.
 */
test('morph to returns correct concrete type on cache hit', function () {
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

    expect($postCommentResult->commentable)->toBeInstanceOf(Post::class);
    expect($bookCommentResult->commentable)->toBeInstanceOf(Book::class);

    // Second load — cache hit
    $cachedComments = (new Comment)
        ->with('commentable')
        ->whereIn('id', [$postComment->id, $bookComment->id])
        ->orderBy('id')
        ->get();

    $cachedPostComment = $cachedComments->firstWhere('id', $postComment->id);
    $cachedBookComment = $cachedComments->firstWhere('id', $bookComment->id);

    expect($cachedPostComment->commentable)->toBeInstanceOf(
        Post::class,
        'morphTo should return Post on cache hit, got: ' . get_class($cachedPostComment->commentable),
    );
    expect($cachedBookComment->commentable)->toBeInstanceOf(
        Book::class,
        'morphTo should return Book on cache hit, got: ' . get_class($cachedBookComment->commentable),
    );
});

/**
 * AC2: Morph target methods are callable after a cache hit (regression test).
 */
test('morph target methods callable after cache hit', function () {
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

    expect($result->commentable)->toBeInstanceOf(Post::class);
    expect($result->commentable->relationLoaded('tags'))->toBeTrue();

    // Second load — cache hit
    $cached = (new Comment)
        ->with('commentable.tags')
        ->where('id', $comment->id)
        ->first();

    expect($cached->commentable)->toBeInstanceOf(
        Post::class,
        'morphTo should return Post on cache hit for nested eager load',
    );
    expect($cached->commentable->relationLoaded('tags'))->toBeTrue(
        'Nested relation "tags" should be loaded on cache hit',
    );

    // Key regression: calling a method on the morph target must not throw
    $tags = $cached->commentable->tags;
    expect($tags)->not->toBeNull();
});

test('updating post invalidates comment with commentable cache', function () {
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

    expect($cached->commentable->title)->toBe($post->title);

    $post->title = 'Updated post title ' . uniqid();
    $post->save();

    $fresh = (new Comment)
        ->with('commentable')
        ->where('id', $comment->id)
        ->first();

    expect($fresh->commentable->title)->toBe(
        $post->title,
        'Comment cache should be invalidated when an eager-loaded Post is updated.',
    );
});

test('updating book invalidates comment with commentable cache', function () {
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

    expect($cached->commentable->title)->toBe($originalTitle);

    $book->title = 'Updated book title ' . uniqid();
    $book->save();

    $fresh = (new Comment)
        ->with('commentable')
        ->where('id', $comment->id)
        ->first();

    expect($fresh->commentable->title)->toBe(
        $book->title,
        'Comment cache should be invalidated when an eager-loaded Book is updated.',
    );
});

// With a morph map registered, an eager-loaded morphTo is tagged with every
// mapped class that exists, in map order, and a mapped name that is not a
// class is skipped. The map is global, so it is cleared again afterwards.
test('morph map tags every mapped class that exists', function () {
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

    expect($tags)->toBe([
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
    ]);
});
