<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Tag;

/**
 * Tests for issue #544: Cache not invalidated on morphTo / morphedByMany delete.
 */

// -------------------------------------------------------------------------
// AC1: Deleting a morphTo child invalidates the parent model's cache
// -------------------------------------------------------------------------
test('deleting morph to child invalidates parent cache', function () {
    $post = (new Post)->with('comments')->first();
    expect($post->comments)->not->toBeEmpty();

    // Warm the cache by querying comments through the parent
    $cachedComments = (new Post)->with('comments')->first()->comments;
    expect($cachedComments)->not->toBeEmpty();

    // Build the cache tag for Post
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];

    // Verify cache is populated (any key with this tag)
    $cachedPost = (new Post)->first();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    $cachedResult = $this->cache()->tags($tags)->get($key);
    expect($cachedResult)->not->toBeNull();

    // Delete a comment (morphTo child)
    $comment = $post->comments->first();
    $comment->delete();

    // Post cache should be invalidated
    $afterDeleteResult = $this->cache()->tags($tags)->get($key);
    expect($afterDeleteResult)->toBeNull(
        'Post cache should be invalidated after deleting a morphTo child Comment.',
    );
});

// -------------------------------------------------------------------------
// AC2: Attach via morphToMany / morphedByMany invalidates caches
// -------------------------------------------------------------------------
test('attach via morph to many invalidates cache', function () {
    $post = (new Post)->first();

    // Warm the tag cache
    $tags = (new Tag)->all();
    expect($tags)->not->toBeEmpty();

    $tagCacheTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturestag",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:tags",
    ];

    $tagKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:tags:genealabslaravelmodelcachingtestsfixturestag");
    $cachedTags = $this->cache()->tags($tagCacheTags)->get($tagKey);
    expect($cachedTags)->not->toBeNull();

    // Attach a new tag via morphToMany
    $newTag = Tag::factory()->create();
    $post->tags()->attach($newTag->id);

    // Both Post and Tag caches should be flushed
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

// -------------------------------------------------------------------------
// AC2: Detach via morphToMany / morphedByMany invalidates caches
// -------------------------------------------------------------------------
test('detach via morph to many invalidates cache', function () {
    $post = (new Post)->first();

    // Ensure post has tags
    $postTags = $post->tags;
    expect($postTags)->not->toBeEmpty();

    // Warm caches
    (new Post)->first();
    (new Tag)->all();

    $postCacheTags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];
    $postKey = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-first");
    expect($this->cache()->tags($postCacheTags)->get($postKey))->not->toBeNull();

    // Detach a tag
    $post->tags()->detach($postTags->first()->id);

    $afterDetachPost = $this->cache()->tags($postCacheTags)->get($postKey);
    expect($afterDetachPost)->toBeNull(
        'Post cache should be invalidated after detaching via morphToMany.',
    );
});
