<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Printer;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

test('a cached has many eager load reloads its scoped related models', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Book::class, fn () => (new Author)
        ->with('books')
        ->find($author->id)
        ->books
        ->pluck('id')
        ->all());

    expect($first)->toBe([$book->id])
        ->and($second)->toBe([]);
});

test('a cached belongs to eager load reloads its scoped related model', function () {
    $book = Book::factory()->create();

    [$first, $second] = runTwiceExcludingTheFirstResult(Author::class, fn () => [(new Book)
        ->with('author')
        ->find($book->id)
        ->author
        ?->id]);

    expect($first)->toBe([$book->author_id])
        ->and($second)->toBe([]);
});

test('a cached belongs to many eager load reloads its scoped related models', function () {
    $book = Book::factory()->create();
    $store = Store::factory()->create();
    $book->stores()->attach($store);

    [$first, $second] = runTwiceExcludingTheFirstResult(Store::class, fn () => (new Book)
        ->with('stores')
        ->find($book->id)
        ->stores
        ->pluck('id')
        ->all());

    expect($first)->toBe([$store->id])
        ->and($second)->toBe([]);
});

test('a cached morph to eager load reloads its scoped related model', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $post->id,
        'commentable_type' => Post::class,
    ]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Post::class, fn () => [(new Comment)
        ->with('commentable')
        ->find($comment->id)
        ->commentable
        ?->id]);

    expect($first)->toBe([$post->id])
        ->and($second)->toBe([]);
});

test('a cached has many through eager load reloads its scoped related models', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    $printer = Printer::factory()->create(['book_id' => $book->id]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Printer::class, fn () => (new Author)
        ->with('printers')
        ->find($author->id)
        ->printers
        ->pluck('id')
        ->all());

    expect($first)->toBe([$printer->id])
        ->and($second)->toBe([]);
});

test('a cached nested eager load reloads its scoped inner related models', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    $store = Store::factory()->create();
    $book->stores()->attach($store);

    [$first, $second] = runTwiceExcludingTheFirstResult(Store::class, fn () => (new Author)
        ->with('books.stores')
        ->find($author->id)
        ->books
        ->flatMap
        ->stores
        ->pluck('id')
        ->all());

    expect($first)->toBe([$store->id])
        ->and($second)->toBe([]);
});

test('a reloaded eager load reloads the scoped eager loads nested under it', function () {
    $author = Author::factory()->create();
    $profile = $author->profile()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $book = Book::factory()->create(['author_id' => $author->id]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Profile::class, fn () => [(new Book)
        ->with('author.profile')
        ->find($book->id)
        ->author
        ->profile
        ?->id]);

    expect($first)->toBe([$profile->id])
        ->and($second)->toBe([]);
});

test('a reloaded eager load keeps its constraint', function () {
    $author = Author::factory()->create();
    $firstBook = Book::factory()->create(['author_id' => $author->id, 'title' => 'Alpha']);
    $secondBook = Book::factory()->create(['author_id' => $author->id, 'title' => 'Apex']);
    Book::factory()->create(['author_id' => $author->id, 'title' => 'Bravo']);

    [$first, $second] = runTwiceExcludingTheFirstResult(Book::class, fn () => (new Author)
        ->with(['books' => fn ($query) => $query->where('title', 'like', 'A%')])
        ->find($author->id)
        ->books
        ->pluck('id')
        ->sort()
        ->values()
        ->all());

    expect($first)->toBe([$firstBook->id, $secondBook->id])
        ->and($second)->toBe([$secondBook->id]);
});

test('a cached eager load onto an unscoped model is not reloaded', function () {
    $author = Author::factory()->create();
    Book::factory()->create(['author_id' => $author->id]);
    $query = fn () => (new Author)->with('books')->find($author->id);
    $query();
    $cacheReads = 0;
    Event::listen([CacheHit::class, CacheMissed::class], function () use (&$cacheReads) {
        $cacheReads++;
    });
    DB::enableQueryLog();

    $books = $query()->books;

    expect($books)->toHaveCount(1)
        ->and(DB::getQueryLog())->toBe([])
        ->and($cacheReads)->toBe(1);
});

test('a cached belongs to many relation query reloads its scoped eager loads', function () {
    $book = Book::factory()->create();
    $store = Store::factory()->create();
    $book->stores()->attach($store);

    [$first, $second] = runTwiceExcludingTheFirstResult(Author::class, fn () => [$store->books()
        ->with('author')
        ->get()
        ->first()
        ->author
        ?->id]);

    expect($first)->toBe([$book->author_id])
        ->and($second)->toBe([]);
});

test('a cached has many through relation query reloads its scoped eager loads', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    Printer::factory()->create(['book_id' => $book->id]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Book::class, fn () => [$author->printers()
        ->with('book')
        ->get()
        ->first()
        ->book
        ?->id]);

    expect($first)->toBe([$book->id])
        ->and($second)->toBe([]);
});

test('a cached nested eager load under a to one relation reloads its scoped related model', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    $printer = Printer::factory()->create(['book_id' => $book->id]);

    [$first, $second] = runTwiceExcludingTheFirstResult(Author::class, fn () => [(new Printer)
        ->with('book.author')
        ->find($printer->id)
        ->book
        ->author
        ?->id]);

    expect($first)->toBe([$author->id])
        ->and($second)->toBe([]);
});

test('a cached nested eager load under a missing to one relation stays empty', function () {
    $author = Author::factory()->create();
    $query = fn () => (new Author)->with('profile.author')->find($author->id);
    $query();

    expect($query()->profile)->toBeNull();
});
