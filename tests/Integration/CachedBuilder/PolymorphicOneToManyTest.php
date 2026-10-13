<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;

test('eagerloaded relationship', function () {
    $postId = (new Post)
        ->disableModelCaching()
        ->whereHas("comments")
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-comments.commentable_id_inraw_{$postId}-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
    ];

    $result = (new Post)
        ->with("comments")
        ->whereHas("comments")
        ->first()
        ->comments;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value']
        ->first();
    $liveResults = (new UncachedPost)
        ->with("comments")
        ->whereHas("comments")
        ->first()
        ->comments;

    expect($result->first()->description)->toEqual($liveResults->first()->description);
    expect($cachedResults->first()->description)->toEqual($liveResults->first()->description);
    expect($result)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('lazyloaded relationship', function () {
    $postId = (new Post)
        ->disableModelCaching()
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post-comments.commentable_id_=_{$postId}-comments.commentable_id_notnull");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
    ];

    $result = (new Post)
        ->first()
        ->comments;

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedPost)
        ->first()
        ->comments;

    expect($result->pluck("commentable_id")->values()->toArray())->toEqual(
        $liveResults->pluck("commentable_id")->values()->toArray(),
    );
    expect($cachedResults->pluck("commentable_id")->values()->toArray())->toEqual(
        $liveResults->pluck("commentable_id")->values()->toArray(),
    );
    expect($result)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});
