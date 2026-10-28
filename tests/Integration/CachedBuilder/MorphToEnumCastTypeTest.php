<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentableTypeEnum;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentableTypePureEnum;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithEnumCastType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithPureEnumCastType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;
use Illuminate\Database\Eloquent\Relations\Relation;

afterEach(function () {
    Relation::morphMap([], false);
});

test('creating a child whose morph type column is cast to a backed enum, then deleting it, invalidates the parent cache', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    $comment = CommentWithEnumCastType::create([
        'description' => 'enum cast',
        'subject' => 'enum cast',
        'commentable_id' => $post->id,
        'commentable_type' => CommentableTypeEnum::Post,
    ]);

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    (new Post)->first();
    expect($this->cache()->tags($tags)->get($key))->not->toBeNull();

    $comment->delete();

    expect($this->cache()->tags($tags)->get($key))->toBeNull(
        'Post cache should be invalidated after deleting a child with an enum-cast morph type.',
    );
});

test('eager loading a morph to whose type column is cast to an enum works on a warm cache', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    CommentWithEnumCastType::create([
        'description' => 'enum cast',
        'subject' => 'enum cast',
        'commentable_id' => $post->id,
        'commentable_type' => CommentableTypeEnum::Post,
    ]);

    CommentWithEnumCastType::with('commentable')->where('subject', 'enum cast')->get();
    $warm = CommentWithEnumCastType::with('commentable')->where('subject', 'enum cast')->get();

    expect($warm->first()->commentable)->toBeInstanceOf(Post::class);
});

test('a pure enum morph type cast invalidates the parent cache on create, update and delete', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();

    $comment = expectWriteToFlushFirstPost(
        fn () => CommentWithPureEnumCastType::create(morphChildAttributes(CommentableTypePureEnum::post, $post->id)),
    );
    expectWriteToFlushFirstPost(fn () => $comment->update(['subject' => 'pure enum updated']));
    expectWriteToFlushFirstPost(fn () => $comment->delete());
});

test('eager loading a pure enum morph type cast returns the parent on a cold load and serves the warm load from the cache', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    UncachedComment::forceCreate(morphChildAttributes('post', $post->id));

    $cold = CommentWithPureEnumCastType::with('commentable')->where('subject', 'morph type cast')->get();
    $warm = null;
    expectServedFromCache(function () use (&$warm) {
        $warm = CommentWithPureEnumCastType::with('commentable')->where('subject', 'morph type cast')->get();
    });

    expect($cold->first()->commentable)->toBeInstanceOf(Post::class)
        ->and($warm->first()->commentable)->toBeInstanceOf(Post::class);
});
