<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Printer;
use Illuminate\Support\Facades\DB;

/**
 * @see https://github.com/mikebronner/laravel-model-caching/issues/538
 */

test('has many through cache invalidated when intermediate model created', function () {
    $author = (new Author)->first();
    $initialCount = $author->printers()->count();

    $book = Book::factory()->create(['author_id' => $author->id]);
    Printer::factory()->create(['book_id' => $book->id]);

    $cachedCount = $author->printers()->count();
    $rawCount = DB::table('printers')
        ->join('books', 'books.id', '=', 'printers.book_id')
        ->where('books.author_id', $author->id)
        ->count();

    expect($cachedCount)->toEqual($rawCount);
    expect($cachedCount)->toEqual($initialCount + 1);
});

test('has many through cache invalidated when intermediate model deleted', function () {
    $author = (new Author)->first();
    $initialCount = $author->printers()->count();
    expect($initialCount)->toBeGreaterThan(0);

    $book = Book::where('author_id', $author->id)->first();
    $book->delete();

    $rawCount = DB::table('printers')
        ->join('books', 'books.id', '=', 'printers.book_id')
        ->where('books.author_id', $author->id)
        ->count();

    $cachedCount = $author->printers()->count();

    expect($cachedCount)->toEqual(
        $rawCount,
        'HasManyThrough cache should be invalidated when intermediate model is deleted.',
    );
});
