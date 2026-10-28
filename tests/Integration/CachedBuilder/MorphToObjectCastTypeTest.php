<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentableType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentableTypeEnum;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentableTypePureEnum;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithEnumCastType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithObjectCastType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithPureEnumCastType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CommentWithStringableCastType;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedComment;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

afterEach(function () {
    Relation::morphMap([], false);
});

test('a stringable morph type cast invalidates the parent cache on create, update and delete', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();

    $comment = expectWriteToFlushFirstPost(
        fn () => CommentWithStringableCastType::create(morphChildAttributes(Str::of('post'), $post->id)),
    );
    expectWriteToFlushFirstPost(fn () => $comment->update(['subject' => 'stringable updated']));
    expectWriteToFlushFirstPost(fn () => $comment->delete());
});

test('eager loading a stringable morph type cast returns the parent on a cold and a warm cache', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    UncachedComment::forceCreate(morphChildAttributes('post', $post->id));

    $cold = CommentWithStringableCastType::with('commentable')->where('subject', 'morph type cast')->get();
    $warm = CommentWithStringableCastType::with('commentable')->where('subject', 'morph type cast')->get();

    expect($cold->first()->commentable)->toBeInstanceOf(Post::class)
        ->and($warm->first()->commentable)->toBeInstanceOf(Post::class);
});

test('a custom object morph type cast invalidates the parent cache on create, update and delete', function () {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();
    $type = new CommentableType('post');
    expect((string) $type)->not->toBe('post');

    $comment = expectWriteToFlushFirstPost(
        fn () => CommentWithObjectCastType::create(morphChildAttributes($type, $post->id)),
    );
    expectWriteToFlushFirstPost(fn () => $comment->update(['subject' => 'custom object updated']));
    expectWriteToFlushFirstPost(fn () => $comment->delete());
});

test('a write with a null morph type or id on a cast morph type column completes without an error', function (string $model, Closure $type) {
    Relation::morphMap(['post' => Post::class]);
    $post = (new Post)->first();

    foreach ([[null, $post->id], [$type(), null], [null, null]] as [$morphType, $morphId]) {
        $comment = $model::create(morphChildAttributes($morphType, $morphId));
        $comment->update(['subject' => 'null morph updated']);
        $comment->delete();

        expect($comment->exists)->toBeFalse();
    }
})->with([
    'backed enum' => [CommentWithEnumCastType::class, fn () => CommentableTypeEnum::Post],
    'pure enum' => [CommentWithPureEnumCastType::class, fn () => CommentableTypePureEnum::post],
    'stringable' => [CommentWithStringableCastType::class, fn () => Str::of('post')],
    'custom object' => [CommentWithObjectCastType::class, fn () => new CommentableType('post')],
]);
