<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentInheritingMorphTo;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentInheritingMorphToFromGrandparent;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;

test('a morph to inherited from the parent class invalidates the parent cache on create, update and delete', function () {
    $post = (new Post)->first();

    $comment = expectWriteToFlushFirstPost(
        fn () => CommentInheritingMorphTo::create(morphChildAttributes(Post::class, $post->id)),
    );
    expectWriteToFlushFirstPost(fn () => $comment->update(['subject' => 'inherited updated']));
    expectWriteToFlushFirstPost(fn () => $comment->delete());
});

test('a morph to inherited from the grandparent class invalidates the parent cache on create, update and delete', function () {
    $post = (new Post)->first();

    $comment = expectWriteToFlushFirstPost(
        fn () => CommentInheritingMorphToFromGrandparent::create(morphChildAttributes(Post::class, $post->id)),
    );
    expectWriteToFlushFirstPost(fn () => $comment->update(['subject' => 'inherited updated']));
    expectWriteToFlushFirstPost(fn () => $comment->delete());
});
