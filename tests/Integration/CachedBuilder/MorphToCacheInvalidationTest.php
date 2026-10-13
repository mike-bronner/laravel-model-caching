<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Tag;

test('deleting morph to child invalidates parent cache', function () {
    $post = (new Post)->with('comments')->first();
    expect($post->comments)->not->toBeEmpty();

    $cachedComments = (new Post)->with('comments')->first()->comments;
    expect($cachedComments)->not->toBeEmpty();

    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];

    $cachedPost = (new Post)->first();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    $cachedResult = $this->cache()->tags($tags)->get($key);
    expect($cachedResult)->not->toBeNull();

    $comment = $post->comments->first();
    $comment->delete();

    $afterDeleteResult = $this->cache()->tags($tags)->get($key);
    expect($afterDeleteResult)->toBeNull(
        'Post cache should be invalidated after deleting a morphTo child Comment.',
    );
});

test('attach via morph to many invalidates cache', function () {
    $post = (new Post)->first();

    $tags = (new Tag)->all();
    expect($tags)->not->toBeEmpty();

    $tagCacheTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturestag",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:tags",
    ];

    $tagKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:tags:genealabslaravelmodelcachingtestsfixturestag");
    $cachedTags = $this->cache()->tags($tagCacheTags)->get($tagKey);
    expect($cachedTags)->not->toBeNull();

    $newTag = Tag::factory()->create();
    $post->tags()->attach($newTag->id);

    $postCacheTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];
    $postKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    $afterAttachPost = $this->cache()->tags($postCacheTags)->get($postKey);
    $afterAttachTags = $this->cache()->tags($tagCacheTags)->get($tagKey);

    expect($afterAttachPost)->toBeNull(
        'Post cache should be invalidated after attaching via morphToMany.',
    );
    expect($afterAttachTags)->toBeNull(
        'Tag cache should be invalidated after attaching via morphToMany.',
    );
});

test('detach via morph to many invalidates cache', function () {
    $post = (new Post)->first();

    $postTags = $post->tags;
    expect($postTags)->not->toBeEmpty();

    (new Post)->first();
    (new Tag)->all();

    $postCacheTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];
    $postKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    expect($this->cache()->tags($postCacheTags)->get($postKey))->not->toBeNull();

    $post->tags()->detach($postTags->first()->id);

    $afterDetachPost = $this->cache()->tags($postCacheTags)->get($postKey);
    expect($afterDetachPost)->toBeNull(
        'Post cache should be invalidated after detaching via morphToMany.',
    );
});
