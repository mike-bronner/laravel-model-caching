<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithMorphToModelRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;

function cachedPostTitles(): array
{
    return (new Post)
        ->orderBy("id")
        ->pluck("title", "id")
        ->all();
}

function livePostTitles(): array
{
    return (new UncachedPost)
        ->orderBy("id")
        ->pluck("title", "id")
        ->all();
}

function cachedCommentSubjects(): array
{
    return (new CommentWithMorphToModelRelation)
        ->orderBy("id")
        ->pluck("subject", "id")
        ->all();
}

function liveCommentSubjects(): array
{
    return (new UncachedComment)
        ->orderBy("id")
        ->pluck("subject", "id")
        ->all();
}

function createCommentWithMorphToModelRelation(array $parent = []): CommentWithMorphToModelRelation
{
    $attributes = Comment::factory()
        ->raw($parent);

    return CommentWithMorphToModelRelation::create($attributes);
}

function morphToParent(Post $post): array
{
    return [
        "commentable_id" => $post->getKey(),
        "commentable_type" => Post::class,
    ];
}

function renamePostBehindTheCache(Post $post): void
{
    (new UncachedPost)
        ->whereKey($post->getKey())
        ->update(["title" => "Renamed behind the cache"]);
}
