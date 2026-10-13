<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPublisher;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('constrained eager loads produce different results', function () {
    $publisher = Publisher::factory()->create();
    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Jason Bourne']);
    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Bason Journe']);

    $jasonBourneBooks = Publisher::with(['books' => function ($q) {
        $q->where('title', 'Jason Bourne');
    }])->get()->pluck('books')->flatten();

    expect($jasonBourneBooks)->toHaveCount(1);
    expect($jasonBourneBooks->first()->title)->toEqual('Jason Bourne');

    $basonJournBooks = Publisher::with(['books' => function ($q) {
        $q->where('title', 'Bason Journe');
    }])->get()->pluck('books')->flatten();

    expect($basonJournBooks)->toHaveCount(1);
    expect($basonJournBooks->first()->title)->toEqual(
        'Bason Journe',
        'Second constrained eager load should return different results than the first.',
    );
});

test('constrained eager loads produce distinct cache keys', function () {
    $publisher = Publisher::factory()->create(['name' => 'ZZZUNIQUE-Publisher']);
    sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:publishers:" .
        (new \GeneaLabs\LaravelModelCaching\CacheKey(
            ['books' => function ($q) { $q->where('title', 'Jason Bourne'); }],
            new Publisher,
            (new Publisher)->newQueryWithoutScopes()->getQuery(),
            '',
            [],
            false
        ))
        ->make(['*']));

    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Jason Bourne 2']);
    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Bason Journe 2']);

    $jasonResult = Publisher::where('name', 'ZZZUNIQUE-Publisher')
        ->with(['books' => fn($q) => $q->where('title', 'Jason Bourne 2')])->get()
        ->pluck('books')->flatten();

    $basonResult = Publisher::where('name', 'ZZZUNIQUE-Publisher')
        ->with(['books' => fn($q) => $q->where('title', 'Bason Journe 2')])->get()
        ->pluck('books')->flatten();

    expect($jasonResult)->toHaveCount(1);
    expect($basonResult)->toHaveCount(1);
    expect($basonResult->first()->id)->not->toEqual($jasonResult->first()->id);
});

test('unconstrained eager load still works', function () {
    $publisher = Publisher::factory()->create();
    Book::factory()->count(3)->create(['publisher_id' => $publisher->id]);

    $publishers = Publisher::where('id', $publisher->id)->with('books')->get();
    $uncached   = (new UncachedPublisher)->where('id', $publisher->id)->with('books')->get();

    expect($publishers->first()->books->pluck('id')->sort()->values()->toArray())->toEqual(
        $uncached->first()->books->pluck('id')->sort()->values()->toArray(),
    );
});

test('three distinct constraints return distinct results', function () {
    $publisher = Publisher::factory()->create();
    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Alpha Title']);
    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Beta Title']);
    Book::factory()->create(['publisher_id' => $publisher->id, 'title' => 'Gamma Title']);

    $getBooks = fn(string $title) => Publisher::where('id', $publisher->id)
        ->with(['books' => fn($q) => $q->where('title', $title)])
        ->get()
        ->pluck('books')
        ->flatten();

    $alpha = $getBooks('Alpha Title');
    $beta  = $getBooks('Beta Title');
    $gamma = $getBooks('Gamma Title');

    expect($alpha)->toHaveCount(1);
    expect($beta)->toHaveCount(1);
    expect($gamma)->toHaveCount(1);
    expect($alpha->first()->title)->toEqual('Alpha Title');
    expect($beta->first()->title)->toEqual('Beta Title');
    expect($gamma->first()->title)->toEqual('Gamma Title');
});

test('constrained eager load cache is invalidated on relation change', function () {
    $publisher = Publisher::factory()->create();
    $book = Book::factory()->create([
        'publisher_id' => $publisher->id,
        'title' => 'Original Title',
    ]);

    $first = Publisher::where('id', $publisher->id)
        ->with(['books' => fn($q) => $q->where('title', 'Original Title')])
        ->get()
        ->pluck('books')
        ->flatten();

    $book->title = 'Updated Title';
    $book->save();

    $second = Publisher::where('id', $publisher->id)
        ->with(['books' => fn($q) => $q->where('title', 'Updated Title')])
        ->get()
        ->pluck('books')
        ->flatten();

    expect($first)->toHaveCount(1);
    expect($second)->toHaveCount(1);
    expect($second->first()->title)->toEqual('Updated Title');
});

test('dynamic local scope in with closure produces distinct cache keys', function () {
    $authorA = Author::factory()->create(['name' => 'Author A']);
    $authorB = Author::factory()->create(['name' => 'Author B']);

    Book::factory()->create(['author_id' => $authorA->id, 'title' => 'Book for A']);
    Book::factory()->create(['author_id' => $authorB->id, 'title' => 'Book for B']);

    $getBooksForAuthor = fn(int $authorId) => Author::with([
        'books' => function (HasMany $q) use ($authorId) {
            $q->where('author_id', $authorId);
        }
    ])->where('id', $authorId)->get()->pluck('books')->flatten();

    $booksA = $getBooksForAuthor($authorA->id);
    $booksB = $getBooksForAuthor($authorB->id);

    expect($booksA)->toHaveCount(1);
    expect($booksB)->toHaveCount(1);
    expect($booksA->first()->title)->toEqual('Book for A');
    expect($booksB->first()->title)->toEqual(
        'Book for B',
        'Changing the scope parameter should return different cached results.',
    );
});
