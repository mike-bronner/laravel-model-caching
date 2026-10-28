<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentableType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;

test('an unresolvable morph type skips the flush on create, update and delete', function (string $type) {
    $post = (new Post)->first();

    $comment = Comment::create(morphChildAttributes($type, $post->id));
    $comment->update(['subject' => 'unresolved morph type updated']);

    expect(UncachedComment::find($comment->id)->subject)->toBe('unresolved morph type updated');

    $comment->delete();

    expect(UncachedComment::find($comment->id))->toBeNull();
})->with([
    'unmapped alias' => ['post'],
    'class that is not a model' => [CommentableType::class],
]);
