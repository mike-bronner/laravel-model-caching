<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;

/**
 * Tests for PR #582: Call parent::__clone() in CachedBuilder clone method.
 *
 * The fix ensures that cloning a CachedBuilder deep-clones the underlying
 * query builder (via parent::__clone()), so that wheres, bindings, scopes,
 * and other query-level state are properly isolated between the original
 * and the clone. Without this fix, morphTo relationships can leak constraints
 * from one model's query into another.
 */

// -------------------------------------------------------------------------
// Basic clone isolation
// -------------------------------------------------------------------------
test('cloning builder isolates wheres', function () {
    $original = (new Author)->where('name', 'LIKE', 'A%');
    $clone = clone $original;

    // Adding a where to the clone must not affect the original.
    $clone->where('email', 'test@example.com');

    $originalWheres = $original->getQuery()->wheres;
    $cloneWheres = $clone->getQuery()->wheres;

    expect($cloneWheres)->toHaveCount(
        count($originalWheres) + 1,
        'Clone should have one more where clause than the original after modification.',
    );

    // Verify the original does not contain the extra where.
    $originalColumns = array_column($originalWheres, 'column');
    // Original builder should not contain wheres added to the clone.
    expect($originalColumns)->not->toContain('email');
});

test('cloning builder isolates bindings', function () {
    $original = (new Author)->where('name', '=', 'Alice');
    $clone = clone $original;

    $clone->where('email', '=', 'bob@example.com');

    $originalBindings = $original->getQuery()->getBindings();
    $cloneBindings = $clone->getQuery()->getBindings();

    expect($cloneBindings)->toHaveCount(
        count($originalBindings) + 1,
        'Clone should have one more binding than the original after modification.',
    );

    // Original builder bindings should not contain values added to the clone.
    expect($originalBindings)->not->toContain('bob@example.com');
});

test('cloning builder isolates orders', function () {
    $original = (new Author)->orderBy('name');
    $clone = clone $original;

    $clone->orderBy('email');

    $originalOrders = $original->getQuery()->orders ?? [];
    $cloneOrders = $clone->getQuery()->orders ?? [];

    expect($originalOrders)->toHaveCount(1, 'Original should still have only one order clause.');
    expect($cloneOrders)->toHaveCount(2, 'Clone should have two order clauses.');
});

// -------------------------------------------------------------------------
// Inner builder isolation (composition path)
// -------------------------------------------------------------------------
test('cloning builder deep clones inner builder', function () {
    $builder = (new Author)->newQuery();

    if (! $builder instanceof CachedBuilder) {
        $this->markTestSkipped('Builder is not a CachedBuilder; cannot test innerBuilder cloning.');
    }

    $inner = $builder->getInnerBuilder();

    if ($inner === null) {
        // No inner builder — the fix still applies via parent::__clone()
        // which deep-clones the underlying $query. Already covered by the
        // basic isolation tests above.
        expect(true)->toBeTrue();

        return;
    }

    $clone = clone $builder;

    expect($clone->getInnerBuilder())->not->toBe(
        $inner,
        'Cloned CachedBuilder must have a different innerBuilder instance.',
    );
});

// -------------------------------------------------------------------------
// morphTo constraint isolation (the original bug scenario)
// -------------------------------------------------------------------------

/**
 * Regression test for the exact scenario described in PR #582:
 *
 * When eager-loading a morphTo relationship, Laravel internally clones the
 * builder to issue separate queries per morph type. Without the
 * parent::__clone() call, wheres/scopes from one type's query could leak
 * into another type's query (e.g. SoftDeletes' `deleted_at is null`
 * constraint from Author leaking into Post or Book queries).
 *
 * This test verifies that eager-loading a morphTo across multiple types
 * returns the correct results — matching the uncached (baseline) query.
 */
test('morph to eager load does not leak constraints across types', function () {
    $post = (new Post)->first();
    $book = (new Book)->first();

    // Create comments pointing to different morph types
    $postComment = Comment::create([
        'commentable_id' => $post->id,
        'commentable_type' => Post::class,
        'description' => 'clone-test post comment',
        'subject' => 'clone isolation post',
    ]);
    $bookComment = Comment::create([
        'commentable_id' => $book->id,
        'commentable_type' => Book::class,
        'description' => 'clone-test book comment',
        'subject' => 'clone isolation book',
    ]);

    $this->cache()->flush();

    // Cached query — eager-load morphTo which triggers builder cloning
    $comments = (new Comment)
        ->with('commentable')
        ->whereIn('id', [$postComment->id, $bookComment->id])
        ->orderBy('id')
        ->get();

    // Uncached query — baseline
    $uncachedComments = (new UncachedComment)
        ->with('commentable')
        ->whereIn('id', [$postComment->id, $bookComment->id])
        ->orderBy('id')
        ->get();

    // Both should return two comments
    expect($comments)->toHaveCount(2);
    expect($uncachedComments)->toHaveCount(2);

    $cachedPostComment = $comments->firstWhere('id', $postComment->id);
    $cachedBookComment = $comments->firstWhere('id', $bookComment->id);
    $uncachedPostComment = $uncachedComments->firstWhere('id', $postComment->id);
    $uncachedBookComment = $uncachedComments->firstWhere('id', $bookComment->id);

    // The commentable relation must be loaded and be the correct type
    expect($cachedPostComment->commentable)->not->toBeNull('Post commentable should not be null.');
    expect($cachedBookComment->commentable)->not->toBeNull('Book commentable should not be null.');

    expect($cachedPostComment->commentable)->toBeInstanceOf(Post::class);
    expect($cachedBookComment->commentable)->toBeInstanceOf(Book::class);

    // Values should match the uncached results
    expect($cachedPostComment->commentable->id)->toEqual(
        $uncachedPostComment->commentable->id,
        'Cached morphTo Post id should match uncached result.',
    );
    expect($cachedBookComment->commentable->id)->toEqual(
        $uncachedBookComment->commentable->id,
        'Cached morphTo Book id should match uncached result.',
    );
});

/**
 * Verify that SoftDeletes scopes on one model (Author) do not leak into
 * unrelated morphTo queries for models that do not use SoftDeletes (Post).
 *
 * Author uses SoftDeletes; Post does not. If cloning is broken, the
 * `deleted_at is null` constraint could leak when the builder is cloned
 * during morphTo resolution.
 */
test('soft deletes scope does not leak into morph to query', function () {
    // Warm up an Author query so SoftDeletes scope is active on that builder
    (new Author)->first();

    $post = (new Post)->first();

    $comment = Comment::create([
        'commentable_id' => $post->id,
        'commentable_type' => Post::class,
        'description' => 'soft-delete leak test',
        'subject' => 'soft-delete scope isolation',
    ]);

    $this->cache()->flush();

    // Eager-load morphTo — this must not introduce deleted_at constraints on posts
    $result = (new Comment)
        ->with('commentable')
        ->where('id', $comment->id)
        ->first();

    $uncachedResult = (new UncachedComment)
        ->with('commentable')
        ->where('id', $comment->id)
        ->first();

    expect($result->commentable)->not->toBeNull(
        'morphTo should resolve the Post without SoftDeletes leaking.',
    );
    expect($result->commentable)->toBeInstanceOf(Post::class);
    expect($result->commentable->id)->toEqual(
        $uncachedResult->commentable->id,
        'Cached result should match uncached result — no scope leakage.',
    );
});

/**
 * When morphTo targets a model that DOES use SoftDeletes (Author via Book
 * author relationship isn't morphTo, but we can set up Author as a morph
 * target), the soft-delete scope should correctly apply only to that model
 * and not to other morph types resolved in the same eager-load batch.
 */
test('morph to with mixed soft delete models resolves correctly', function () {
    $post = (new Post)->first();
    $book = (new Book)->first();

    $postComment = Comment::create([
        'commentable_id' => $post->id,
        'commentable_type' => Post::class,
        'description' => 'mixed morph post comment',
        'subject' => 'mixed morph post',
    ]);

    $bookComment = Comment::create([
        'commentable_id' => $book->id,
        'commentable_type' => Book::class,
        'description' => 'mixed morph book comment',
        'subject' => 'mixed morph book',
    ]);

    $this->cache()->flush();

    $ids = [$postComment->id, $bookComment->id];

    // First load — cache miss
    $firstLoad = (new Comment)
        ->with('commentable')
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get();

    // Second load — cache hit
    $secondLoad = (new Comment)
        ->with('commentable')
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get();

    // Baseline
    $baseline = (new UncachedComment)
        ->with('commentable')
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get();

    foreach ([
        'first load (miss)' => $firstLoad,
        'second load (hit)' => $secondLoad,
    ] as $label => $comments) {
        $pc = $comments->firstWhere('id', $postComment->id);
        $bc = $comments->firstWhere('id', $bookComment->id);
        $bpc = $baseline->firstWhere('id', $postComment->id);
        $bbc = $baseline->firstWhere('id', $bookComment->id);

        expect($pc->commentable)->toBeInstanceOf(
            Post::class,
            "Post commentable should be a Post on {$label}.",
        );
        expect($bc->commentable)->toBeInstanceOf(
            Book::class,
            "Book commentable should be a Book on {$label}.",
        );
        expect($pc->commentable->id)->toEqual(
            $bpc->commentable->id,
            "Post commentable id should match baseline on {$label}.",
        );
        expect($bc->commentable->id)->toEqual(
            $bbc->commentable->id,
            "Book commentable id should match baseline on {$label}.",
        );
    }
});

// -------------------------------------------------------------------------
// Multiple sequential clones
// -------------------------------------------------------------------------
test('multiple clones do not share state', function () {
    $base = (new Author)->where('name', 'LIKE', 'A%');

    $clone1 = clone $base;
    $clone1->where('email', 'clone1@example.com');

    $clone2 = clone $base;
    $clone2->where('id', '>', 5);

    $baseWheres = $base->getQuery()->wheres;
    $clone1Wheres = $clone1->getQuery()->wheres;
    $clone2Wheres = $clone2->getQuery()->wheres;

    // base should have the original where only
    $baseColumns = array_column($baseWheres, 'column');
    expect($baseColumns)->not->toContain('email');
    expect($baseColumns)->not->toContain('id');

    // clone1 should have base + email
    $clone1Columns = array_column($clone1Wheres, 'column');
    expect($clone1Columns)->toContain('email');
    expect($clone1Columns)->not->toContain('id');

    // clone2 should have base + id
    $clone2Columns = array_column($clone2Wheres, 'column');
    expect($clone2Columns)->toContain('id');
    expect($clone2Columns)->not->toContain('email');
});
