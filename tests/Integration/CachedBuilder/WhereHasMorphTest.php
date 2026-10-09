<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;
use Illuminate\Database\Eloquent\Builder;

test('with single morph model', function () {
    $comments = (new Comment)
        ->whereHasMorph('commentable', Post::class)
        ->get();
    $uncachedComments = (new UncachedComment())
        ->whereHasMorph('commentable', Post::class)
        ->get();

    $cacheResults = $this->cache()->tags([
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-nested-or-nested-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post-exists-comments.commentable_id_=_posts.id"
        ))['value'];

    expect($comments)->toHaveCount(5);
    expect($uncachedComments->pluck("id"))->toEqual($comments->pluck("id"));
    expect($cacheResults->pluck("id"))->toEqual($uncachedComments->pluck("id"));
});

test('with multiple morph models', function () {
    $comments = (new Comment)
        ->whereHasMorph('commentable', [Post::class, UncachedPost::class])
        ->get();
    $uncachedComments = (new UncachedComment())
        ->whereHasMorph('commentable', [Post::class, UncachedPost::class])
        ->get();

    $cacheResults = $this->cache()->tags([
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-nested-or-nested-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post-exists-comments.commentable_id_=_posts.id-or-nested-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost-exists-comments.commentable_id_=_posts.id"
        ))['value'];

    expect($comments)->toHaveCount(10);
    expect($uncachedComments->pluck("id"))->toEqual($comments->pluck("id"));
    expect($cacheResults->pluck("id"))->toEqual($uncachedComments->pluck("id"));
});

test('with multiple morph models with closure', function () {
    $comments = (new Comment)
        ->whereHasMorph('commentable', [Post::class, UncachedPost::class], function (Builder $query) {
            return $query->where('subject', 'like',  '%uncached post');
        })
        ->get();
    $uncachedComments = (new UncachedComment())
        ->whereHasMorph('commentable', [Post::class, UncachedPost::class], function (Builder $query) {
            return $query->where('subject', 'like',  '%uncached post');
        })
        ->get();

    $cacheResults = $this->cache()->tags([
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ])
        ->get(sha1(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-nested-or-nested-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post-exists-comments.commentable_id_=_posts.id-subject_like_%25uncached post-or-nested-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost-exists-comments.commentable_id_=_posts.id-subject_like_%25uncached post"
        ))['value'];

    expect($comments)->toHaveCount(5);
    expect($uncachedComments->pluck("id"))->toEqual($comments->pluck("id"));
    expect($cacheResults->pluck("id"))->toEqual($uncachedComments->pluck("id"));
});
