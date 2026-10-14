<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithModelRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithUnloadableModelRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use Illuminate\Support\Facades\DB;

test('writes do not load a relation named model', function (): void {
    $author = (new Author)->first();
    $authorId = $author->getKey();
    $attributes = Book::factory()
        ->raw(["author_id" => $authorId]);
    DB::enableQueryLog();

    $book = BookWithModelRelation::create($attributes);
    $book->update(["title" => "Renamed"]);
    $book->delete();

    $authorQueries = collect(DB::getQueryLog())
        ->pluck("query")
        ->filter(fn (string $query) => str_contains($query, "\"authors\""));
    $loaded = $book->relationLoaded("model");

    expect($authorQueries)->toBeEmpty();
    expect($loaded)->toBeFalse();
});

test('writes succeed when the model relation cannot be loaded', function (): void {
    $attributes = Book::factory()
        ->raw();
    $book = BookWithUnloadableModelRelation::create($attributes);
    $book->update(["title" => "Renamed"]);
    $book->delete();

    $bookId = $book->getKey();
    $exists = (new BookWithUnloadableModelRelation)
        ->whereKey($bookId)
        ->exists();

    expect($exists)->toBeFalse();
});

test('the model relation still loads when asked for', function (): void {
    $author = (new Author)->first();
    $authorId = $author->getKey();
    $attributes = Book::factory()
        ->raw(["author_id" => $authorId]);
    $book = BookWithModelRelation::create($attributes);
    $loaded = $book->fresh()
        ->model;

    expect($loaded->is($author))->toBeTrue();
});

test('writes do not load a morph to model relation and flush its parent', function (): void {
    $post = (new Post)->first();
    $comment = createCommentWithMorphToModelRelation(morphToParent($post));
    cachedPostTitles();
    renamePostBehindTheCache($post);

    $comment->update(["subject" => "Renamed"]);
    $loaded = $comment->relationLoaded("model");

    expect($loaded)->toBeFalse();
    expect(cachedPostTitles())->toEqual(livePostTitles());
});

test('writes flush the cache of a morph to model relation with no parent', function (): void {
    cachedCommentSubjects();
    $comment = createCommentWithMorphToModelRelation();
    $afterCreate = cachedCommentSubjects();

    $comment->update(["subject" => "Renamed"]);
    $afterUpdate = cachedCommentSubjects();

    $comment->delete();
    $afterDelete = cachedCommentSubjects();
    $type = $comment->getAttribute("commentable_type");
    $commentId = $comment->getKey();

    expect($type)->toBeNull();
    expect($afterCreate)->toHaveKey($commentId);
    expect(data_get($afterUpdate, $commentId))->toBe("Renamed");
    expect($afterDelete)->toEqual(liveCommentSubjects());
    expect(array_key_exists($commentId, $afterDelete))->toBeFalse();
});

test('deleting a morph to model relation flushes its parent and itself', function (): void {
    $post = (new Post)->first();
    $comment = createCommentWithMorphToModelRelation(morphToParent($post));
    cachedPostTitles();
    $comments = cachedCommentSubjects();
    renamePostBehindTheCache($post);

    $comment->delete();
    $commentId = $comment->getKey();

    expect($comments)->toHaveKey($commentId);
    expect(cachedPostTitles())->toEqual(livePostTitles());
    expect(cachedCommentSubjects())->toEqual(liveCommentSubjects());
});

test('writes flush a morph to model relation with half a parent', function (array $parent): void {
    cachedCommentSubjects();
    $comment = createCommentWithMorphToModelRelation($parent);
    $afterCreate = cachedCommentSubjects();

    $comment->update(["subject" => "Renamed"]);
    $afterUpdate = cachedCommentSubjects();

    $comment->delete();
    $afterDelete = cachedCommentSubjects();
    $commentId = $comment->getKey();

    expect($afterCreate)->toHaveKey($commentId);
    expect(data_get($afterUpdate, $commentId))->toBe("Renamed");
    expect($afterDelete)->toEqual(liveCommentSubjects());
    expect(array_key_exists($commentId, $afterDelete))->toBeFalse();
})->with([
    "a type with no id" => [["commentable_type" => Post::class, "commentable_id" => null]],
    "an id with no type" => [["commentable_type" => null, "commentable_id" => 1]],
]);

test('a write with a morph type and no id leaves the parent cache alone', function (): void {
    cachedPostTitles();

    $comment = createCommentWithMorphToModelRelation([
        "commentable_type" => Post::class,
        "commentable_id" => null,
    ]);
    $comment->update(["subject" => "Renamed"]);

    expectServedFromCache(fn () => cachedPostTitles());
});
