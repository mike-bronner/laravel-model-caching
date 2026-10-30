<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use Illuminate\Support\Facades\DB;

function bookWithComments(Author $author, array $descriptions, array $attributes = []): Book
{
    $book = Book::factory()
        ->create([
            "author_id" => $author->id,
            "publisher_id" => Publisher::factory()->create()->id,
            ...$attributes,
        ]);
    collect($descriptions)
        ->each(fn (string $description) => $book->comments()->create([
            "description" => $description,
            "subject" => $description,
        ]));

    return $book;
}

function authorWithCommentedBook(): Author
{
    $author = Author::factory()
        ->create();
    bookWithComments($author, ["AAA", "BBB"]);

    return $author;
}

function authorWithPublishedBooks(): Author
{
    $author = Author::factory()
        ->create();
    $publisher = Publisher::factory()
        ->create(["name" => "Penguin"]);
    collect(["One", "Two"])
        ->each(fn (string $title) => Book::factory()->create([
            "author_id" => $author->id,
            "publisher_id" => $publisher->id,
            "title" => $title,
        ]));

    return $author;
}

function whereDescription(string $description): Closure
{
    return fn ($query) => $query->where("description", $description);
}

function whereRawDescription(string $description): Closure
{
    return fn ($query) => $query->where("description", DB::raw("'{$description}'"));
}

function publisherIdsWhere(string $condition): Closure
{
    return fn ($query) => $query->select("id")
        ->from("publishers")
        ->whereRaw($condition);
}

function commentsOfFirstBook(Author $author): array
{
    return $author->books
        ->first()
        ->comments
        ->pluck("description")
        ->all();
}

function commentsByBook(Author $author): array
{
    return $author->books
        ->mapWithKeys(fn (Book $book) => [$book->description => $book->comments->pluck("description")->all()])
        ->all();
}

function commentColumnsOfFirstBook(Author $author): array
{
    return collect($author->books->first()->comments->first()->getAttributes())
        ->keys()
        ->sort()
        ->values()
        ->all();
}

function publishedTitlesOfFirstBook(Author $author): ?array
{
    return $author->books
        ->first()
        ->publisher
        ?->books
        ->pluck("title")
        ->all();
}

function commentsBesideComment(Comment $comment): array
{
    return $comment->commentable
        ->comments
        ->pluck("description")
        ->all();
}

function queriesRunBy(Closure $read): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = $read();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return [$result, $log];
}

function readsWithLiveRepeat(Closure $query, Closure $shape, string $first, string $second): array
{
    $reads = [$shape($query($first)), $shape($query($second))];
    [$repeat, $log] = queriesRunBy(fn () => $query($second));
    $rootQuery = (new Comment)->where("id", $repeat->id)
        ->toSql();
    $ranLive = collect($log)->pluck("query")
        ->contains($rootQuery);

    return [...$reads, $shape($repeat), $ranLive];
}
