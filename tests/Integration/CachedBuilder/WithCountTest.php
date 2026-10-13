<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;

test('with count updates after record is added', function () {
    $author1 = (new Author)
        ->withCount("books")
        ->first();
    Book::factory()->count(1)
        ->make()
        ->each(function ($book) use ($author1) {
            $publisher = (new Publisher)->first();
            $book->author()->associate($author1);
            $book->publisher()->associate($publisher);
            $book->save();
        });

    $author2 = (new Author)
        ->withCount("books")
        ->where("id", $author1->id)
        ->first();

    expect($author2->books_count)->not->toEqual($author1->books_count);
    expect($author2->books_count)->toEqual($author1->books_count + 1);
});

test('with count on morph many relationship updates after record is added', function () {
    $book1 = (new Book)
        ->withCount("comments")
        ->first();
    $comment = Comment::factory()->count(1)
        ->create()
        ->first();

    $book1->comments()->save($comment);

    $book2 = (new Book)
        ->withCount("comments")
        ->where("id", $book1->id)
        ->first();

    expect($book2->comments_count)->not->toEqual($book1->comments_count);
    expect($book2->comments_count)->toEqual($book1->comments_count + 1);
});
