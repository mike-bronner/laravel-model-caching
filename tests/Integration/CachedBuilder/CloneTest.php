<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseAndEloquentBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;

test('cloning builder isolates wheres', function () {
    $original = (new Author)->where('name', 'LIKE', 'A%');
    $clone = clone $original;

    $clone->where('email', 'test@example.com');

    $originalWheres = $original->getQuery()->wheres;
    $cloneWheres = $clone->getQuery()->wheres;

    expect($cloneWheres)->toHaveCount(
        count($originalWheres) + 1,
        'Clone should have one more where clause than the original after modification.',
    );

    $originalColumns = array_column($originalWheres, 'column');
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

test('cloning builder deep clones inner builder', function () {
    $builder = (new Author)->newQuery();

    if (! $builder instanceof CachedBuilder) {
        $this->markTestSkipped('Builder is not a CachedBuilder; cannot test innerBuilder cloning.');
    }

    $inner = $builder->getInnerBuilder();

    if ($inner === null) {
        expect(true)->toBeTrue();

        return;
    }

    $clone = clone $builder;

    expect($clone->getInnerBuilder())->not->toBe(
        $inner,
        'Cloned CachedBuilder must have a different innerBuilder instance.',
    );
});

test('morph to eager load does not leak constraints across types', function () {
    $post = (new Post)->first();
    $book = (new Book)->first();

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

    $comments = (new Comment)
        ->with('commentable')
        ->whereIn('id', [$postComment->id, $bookComment->id])
        ->orderBy('id')
        ->get();

    $uncachedComments = (new UncachedComment)
        ->with('commentable')
        ->whereIn('id', [$postComment->id, $bookComment->id])
        ->orderBy('id')
        ->get();

    expect($comments)->toHaveCount(2);
    expect($uncachedComments)->toHaveCount(2);

    $cachedPostComment = $comments->firstWhere('id', $postComment->id);
    $cachedBookComment = $comments->firstWhere('id', $bookComment->id);
    $uncachedPostComment = $uncachedComments->firstWhere('id', $postComment->id);
    $uncachedBookComment = $uncachedComments->firstWhere('id', $bookComment->id);

    expect($cachedPostComment->commentable)->not->toBeNull('Post commentable should not be null.');
    expect($cachedBookComment->commentable)->not->toBeNull('Book commentable should not be null.');

    expect($cachedPostComment->commentable)->toBeInstanceOf(Post::class);
    expect($cachedBookComment->commentable)->toBeInstanceOf(Book::class);

    expect($cachedPostComment->commentable->id)->toEqual(
        $uncachedPostComment->commentable->id,
        'Cached morphTo Post id should match uncached result.',
    );
    expect($cachedBookComment->commentable->id)->toEqual(
        $uncachedBookComment->commentable->id,
        'Cached morphTo Book id should match uncached result.',
    );
});

test('soft deletes scope does not leak into morph to query', function () {
    (new Author)->first();

    $post = (new Post)->first();

    $comment = Comment::create([
        'commentable_id' => $post->id,
        'commentable_type' => Post::class,
        'description' => 'soft-delete leak test',
        'subject' => 'soft-delete scope isolation',
    ]);

    $this->cache()->flush();

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

    $firstLoad = (new Comment)
        ->with('commentable')
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get();

    $secondLoad = (new Comment)
        ->with('commentable')
        ->whereIn('id', $ids)
        ->orderBy('id')
        ->get();

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

test('multiple clones do not share state', function () {
    $base = (new Author)->where('name', 'LIKE', 'A%');

    $clone1 = clone $base;
    $clone1->where('email', 'clone1@example.com');

    $clone2 = clone $base;
    $clone2->where('id', '>', 5);

    $baseWheres = $base->getQuery()->wheres;
    $clone1Wheres = $clone1->getQuery()->wheres;
    $clone2Wheres = $clone2->getQuery()->wheres;

    $baseColumns = array_column($baseWheres, 'column');
    expect($baseColumns)->not->toContain('email');
    expect($baseColumns)->not->toContain('id');

    $clone1Columns = array_column($clone1Wheres, 'column');
    expect($clone1Columns)->toContain('email');
    expect($clone1Columns)->not->toContain('id');

    $clone2Columns = array_column($clone2Wheres, 'column');
    expect($clone2Columns)->toContain('id');
    expect($clone2Columns)->not->toContain('email');
});

test('cloned reads on a custom eloquent builder model return their own rows', function (): void {
    $query = (new AuthorWithCustomBuilder)->newQuery();
    [$firstId, $secondId] = firstTwoDistinctValues("authors", "id");

    $first = (clone $query)->where("id", $firstId)
        ->pluck("id")
        ->all();
    $second = (clone $query)->where("id", $secondId)
        ->pluck("id")
        ->all();

    expect($first)->toBe([$firstId]);
    expect($second)->toBe([$secondId]);
});

test('a cloned builder does not share macros with its original', function (): void {
    $query = (new AuthorWithCustomBaseAndEloquentBuilder)->newQuery();
    $clone = clone $query;
    $cloneInner = $clone->getInnerBuilder();
    $originalInner = $query->getInnerBuilder();

    $cloneInner->macro("cloneOnly", fn ($builder) => $builder);

    expect($cloneInner->hasMacro("cloneOnly"))->toBeTrue();
    expect($originalInner->hasMacro("cloneOnly"))->toBeFalse();
});
