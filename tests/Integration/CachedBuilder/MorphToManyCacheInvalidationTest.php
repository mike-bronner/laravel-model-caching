<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Tag;
use Illuminate\Support\Facades\DB;

/**
 * @see https://github.com/mikebronner/laravel-model-caching/issues/538
 */

test('morph to many attach invalidates cache', function () {
    $post = (new Post)->first();
    $newTag = Tag::factory()->create(['name' => 'attach-tag']);

    $initialCount = $post->tags()->count();
    $post->tags()->attach($newTag->id);

    $cachedCount = $post->tags()->count();
    $dbCount = DB::table('taggables')
        ->where('taggable_id', $post->id)
        ->where('taggable_type', Post::class)
        ->count();

    expect($cachedCount)->toEqual(
        $dbCount,
        'Cache should be invalidated after morphToMany attach.',
    );
    expect($cachedCount)->toEqual($initialCount + 1);
});

test('morph to many detach invalidates cache', function () {
    $post = (new Post)->first();

    expect($post->tags()->count())->toBeGreaterThan(0, 'Post should have at least one tag.');

    $post->tags()->count();
    $post->tags()->detach();

    $cachedCount = $post->tags()->count();
    $dbCount = DB::table('taggables')
        ->where('taggable_id', $post->id)
        ->where('taggable_type', Post::class)
        ->count();

    expect($dbCount)->toEqual(0);
    expect($cachedCount)->toEqual(
        $dbCount,
        'Cache should be invalidated after morphToMany detach.',
    );
});

test('morph to many sync invalidates cache', function () {
    $post = (new Post)->first();
    $newTags = Tag::factory()->count(3)->create();

    $post->tags()->count();
    $post->tags()->sync($newTags->pluck('id'));

    $cachedCount = $post->tags()->count();
    $dbCount = DB::table('taggables')
        ->where('taggable_id', $post->id)
        ->where('taggable_type', Post::class)
        ->count();

    expect($dbCount)->toEqual(3);
    expect($cachedCount)->toEqual($dbCount, 'Cache should be invalidated after morphToMany sync.');
});
