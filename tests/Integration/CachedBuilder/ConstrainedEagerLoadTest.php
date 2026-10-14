<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPublisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

test('eager load constraints that differ only in order, limit or columns keep their own rows', function (Closure $first, Closure $second) {
    $liveResults = (new UncachedAuthor)->with(["books" => $second])->get()->toArray();

    expect(cacheKey((new Author)->with(["books" => $first])))
        ->not->toBe(cacheKey((new Author)->with(["books" => $second])));

    (new Author)->with(["books" => $first])->get();

    expect((new Author)->with(["books" => $second])->get()->toArray())->toEqual($liveResults);
})->with([
    "no constraint and an order" => [
        fn ($query) => $query,
        fn ($query) => $query->orderByDesc("title"),
    ],
    "two orders" => [
        fn ($query) => $query->orderBy("title"),
        fn ($query) => $query->orderByDesc("title"),
    ],
    "a where with two orders" => [
        fn ($query) => $query->where("id", ">", 0)->orderBy("title"),
        fn ($query) => $query->where("id", ">", 0)->orderByDesc("title"),
    ],
    "two limits" => [
        fn ($query) => $query->limit(1),
        fn ($query) => $query->limit(2),
    ],
    "a where with two limits" => [
        fn ($query) => $query->where("id", ">", 0)->limit(1),
        fn ($query) => $query->where("id", ">", 0)->limit(2),
    ],
    "two selects" => [
        fn ($query) => $query->select("id", "author_id"),
        fn ($query) => $query->select("id", "author_id", "title"),
    ],
    "a where with two selects" => [
        fn ($query) => $query->where("id", ">", 0)->select("id", "author_id"),
        fn ($query) => $query->where("id", ">", 0)->select("id", "author_id", "title"),
    ],
]);

test('an eager load constraint that changes another query part enters the key', function (Closure $constraint) {
    expect(cacheKey((new Author)->with(["books" => $constraint])))
        ->not->toBe(cacheKey((new Author)->with("books")));
})->with([
    "offset" => fn ($query) => $query->offset(1),
    "group by" => fn ($query) => $query->groupBy("author_id"),
    "having" => fn ($query) => $query->having("id", ">", 0),
    "join" => fn ($query) => $query->join("publishers", "publishers.id", "=", "books.publisher_id"),
    "union" => fn ($query) => $query->union(Book::query()->getQuery()),
    "distinct" => fn ($query) => $query->distinct(),
    "from" => fn ($query) => $query->from("books as b"),
    "before query" => fn ($query) => $query->beforeQuery(fn ($query) => $query->orderBy("id")),
    "lock" => fn ($query) => $query->lockForUpdate(),
]);

test('a plain eager load and a where-only constraint keep their key', function () {
    expect(cacheKey((new Author)->with("books")))->toEndWith("testing.sqlite:books")
        ->and(cacheKey((new Author)->with(["books" => fn ($query) => $query->where("id", ">", 0)])))
        ->toEndWith("testing.sqlite:books=a0fb20c51f775e86474ed119a779a7eeb56944ce");
});

test('where-only constraints of other shapes keep their key', function (Closure $query, string $suffix) {
    expect(cacheKey($query()))->toEndWith("testing.sqlite:{$suffix}");
})->with([
    "a plain belongs to" => [
        fn () => (new Book)->with("author"),
        "author",
    ],
    "a where on a belongs to many" => [
        fn () => (new Book)->with(["stores" => fn ($query) => $query->where("stores.id", ">", 0)]),
        "stores=f515135f138528b74a1d1541d9087456761a5b82",
    ],
    "a where in subquery" => [
        fn () => (new Author)->with(["books" => fn ($query) => $query
            ->whereIn("publisher_id", fn ($sub) => $sub->select("id")->from("publishers"))]),
        "books=54d199896d516bdbf4a78e69dcf80e44943b7801",
    ],
    "a where pivot" => [
        fn () => (new Book)->with(["stores" => fn ($query) => $query->wherePivot("store_id", ">", 0)]),
        "stores=67d2af62f3bc3fc16192f851b5eb34a70f176c42",
    ],
    "a where pivot in" => [
        fn () => (new Book)->with(["stores" => fn ($query) => $query->wherePivotIn("store_id", [1, 2])]),
        "stores=f3c7107d522165cb417e656bd036ee597026fffa",
    ],
    "a where pivot between" => [
        fn () => (new Book)->with(["stores" => fn ($query) => $query->wherePivotBetween("store_id", [1, 2])]),
        "stores=fa6478aa0c96d08988288a7f7ccbdd6def1339f8",
    ],
    "a where pivot null" => [
        fn () => (new Book)->with(["stores" => fn ($query) => $query->wherePivotNull("store_id")]),
        "stores=67d09f945c4e5bff8bc041c363167c784384f606",
    ],
]);

test('where-only constraints that nest a builder keep the key the framework gave them', function (Model $model, string $relation, Closure $constraint) {
    $suffix = whereOnlyEagerLoadKeySuffix($model, $relation, $constraint);
    $expected = $suffix === null
        ? ""
        : cacheKey($model->with($relation)) . $suffix;

    expect(cacheKey($model->with([$relation => $constraint])))->toBe($expected);
})->with([
    "a where has" => [
        fn () => new Author,
        "books",
        fn ($query) => $query->whereHas("publisher", fn ($publisher) => $publisher->where("name", "!=", "")),
    ],
    "a where pivot closure" => [
        fn () => new Book,
        "stores",
        fn ($query) => $query->wherePivot(fn ($pivot) => $pivot->where("book_store.store_id", ">", 0)),
    ],
    "a nested where" => [
        fn () => new Author,
        "books",
        fn ($query) => $query->where(fn ($nested) => $nested->where("title", "!=", "")),
    ],
    "a local scope that nests a where" => [
        fn () => new Author,
        "books",
        fn ($query) => $query->titledLike("A%"),
    ],
]);

test('a trashed eager load and a plain eager load keep their own authors', function (array $order) {
    (new Book)->first()->author->delete();
    $queries = [
        "plain" => fn (Model $book) => $book->with("author"),
        "trashed" => fn (Model $book) => $book->with(["author" => fn ($query) => $query->withTrashed()]),
    ];
    $authorIds = fn (Model $book, string $name) => $queries[$name]($book)
        ->get()
        ->pluck("author.id", "id")
        ->all();

    foreach ($order as $name) {
        expect($authorIds(new Book, $name))->toBe($authorIds(new UncachedBook, $name));
    }

    expect($authorIds(new UncachedBook, "plain"))->not->toBe($authorIds(new UncachedBook, "trashed"));
})->with([
    "plain first" => [["plain", "trashed"]],
    "trashed first" => [["trashed", "plain"]],
]);

test('an eager load constraint that changes the relation builder does not share the plain key', function (Closure $constraint) {
    expect(cacheKey((new Book)->with(["author" => $constraint])))
        ->not->toBe(cacheKey((new Book)->with("author")));
})->with([
    "without global scopes" => fn ($query) => $query->withoutGlobalScopes(),
    "without one global scope" => fn ($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
    "a nested eager load" => fn ($query) => $query->with("books"),
]);

test('eager load constraints with different nested eager loads keep their own keys', function () {
    $nested = fn (string $title) => (new Author)->with(["books" => fn ($query) => $query
        ->with(["publisher" => fn ($publisher) => $publisher->where("name", $title)])]);

    expect(cacheKey($nested("first")))->not->toBe(cacheKey($nested("second")))
        ->and(cacheKey($nested("first")))->not->toBe(cacheKey((new Author)->with("books")));
});

test('an eager load constraint that changes a pivot join in place does not share the plain key', function () {
    $changed = (new Book)->with(["stores" => function ($query) {
        $query->getBaseQuery()->joins[0]->where("book_store.store_id", ">", 0);
    }]);

    expect(cacheKey($changed))->not->toBe("")
        ->and(cacheKey($changed))->not->toBe(cacheKey((new Book)->with("stores")));
});

test('an eager load constraint with an after query callback runs uncached', function (Closure $constraint) {
    expect(cacheKey((new Author)->with(["books" => $constraint])))->toBe("");
})->with([
    "on the eloquent builder" => fn ($query) => $query->afterQuery(fn ($books) => $books),
    "on the base query" => fn ($query) => $query->getBaseQuery()->afterQuery(fn ($books) => $books),
    "in a nested eager load" => fn ($query) => $query
        ->with(["publisher" => fn ($publisher) => $publisher->afterQuery(fn ($publishers) => $publishers)]),
]);

test('an eager load constraint that changes only the relation does not share the plain key', function () {
    $withPivot = fn (string $column) => (new Book)
        ->with(["stores" => fn ($query) => $query->withPivot($column)]);

    expect(cacheKey($withPivot("created_at")))->not->toBe(cacheKey((new Book)->with("stores")))
        ->and(cacheKey($withPivot("created_at")))->not->toBe(cacheKey($withPivot("updated_at")));
});
