<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseBuilderAndBooks;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithPrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

test('count constrained has tags the related table', function () {
    assertCachedUnderTags(
        (new Author)->has("books", ">", 1),
        [
            tagPrefix() . "genealabslaravelmodelcachingtestsfixturesauthor",
            tagPrefix() . "authors",
            tagPrefix() . "books",
        ],
    );
});

test('has with a relation instance tags the related table', function () {
    $relation = Relation::noConstraints(fn () => (new Author)->books());

    assertCachedUnderTags(
        (new Author)->has($relation, ">", 1),
        [
            tagPrefix() . "genealabslaravelmodelcachingtestsfixturesauthor",
            tagPrefix() . "authors",
            tagPrefix() . "books",
        ],
    );
});

test('subquery join tags the joined table', function () {
    assertCachedUnderTags(
        (new Book)->whereHas("stores"),
        [
            ...bookTags(),
            tagPrefix() . "stores",
            tagPrefix() . "book-store",
        ],
    );
});

test('zero count has tags the related table', function () {
    assertCachedUnderTags(
        (new Author)->has("books", "=", 0),
        [
            tagPrefix() . "genealabslaravelmodelcachingtestsfixturesauthor",
            tagPrefix() . "authors",
            tagPrefix() . "books",
        ],
    );
});

test('count constrained has cache is busted when related table is written', function () {
    $author = Author::factory()->create(["name" => "John"]);
    $books = Book::factory()->count(2)->create(["author_id" => $author->id]);
    $query = fn () => (new Author)
        ->where("id", $author->id)
        ->has("books", ">", 1)
        ->get();

    expect($query())->not->toBeEmpty();

    $books->last()->delete();

    expect($query())->toBeEmpty();
});

test('with count tags the aggregated table', function () {
    assertCachedUnderTags(
        (new Book)->withCount("author"),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('with exists tags the aggregated table', function () {
    assertCachedUnderTags(
        (new Book)->withExists("author"),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('aliased with count tags the aggregated table', function () {
    assertCachedUnderTags(
        (new Book)->withCount("author as writer_count"),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('with count cache is busted when aggregated table is written', function () {
    $author = Author::factory()->create(["name" => "John"]);
    Book::factory()->create(["author_id" => $author->id]);
    $query = fn () => (new Book)
        ->where("author_id", $author->id)
        ->withCount("author")
        ->get()
        ->first()
        ->author_count;

    expect($query())->toBe(1);

    $author->delete();

    expect($query())->toBe(0);
});

test('where in subquery tags the subquery table', function () {
    assertCachedUnderTags(
        (new Book)->whereIn("author_id", fn ($query) => $query
            ->select("id")
            ->from("authors")),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('where not in subquery tags the subquery table', function () {
    assertCachedUnderTags(
        (new Book)->whereNotIn("author_id", fn ($query) => $query
            ->select("id")
            ->from("authors")),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('sub where clause tags its subquery table', function () {
    assertCachedUnderTags(
        (new Book)->where("id", ">", fn ($query) => $query
            ->selectRaw("min(id)")
            ->from("authors")),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('join clause subquery tags its table', function () {
    assertCachedUnderTags(
        (new Book)->join("authors", function ($join) {
            $join->on("books.author_id", "=", "authors.id")
                ->whereExists(fn ($query) => $query
                    ->select("id")
                    ->from("profiles")
                    ->whereColumn("profiles.author_id", "authors.id"));
        }),
        [
            ...bookTags(),
            tagPrefix() . "authors",
            tagPrefix() . "profiles",
        ],
    );
});

test('aliased join tags the unaliased table', function () {
    assertCachedUnderTags(
        (new Book)->join("authors as a", "books.author_id", "=", "a.id"),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('a join sub inside an exists subquery tags the tables it reads', function () {
    assertCachedUnderTags(
        (new Book)->whereExists(fn ($query) => $query
            ->select("authors.id")
            ->from("authors")
            ->joinSub(
                fn ($sub) => $sub->select("author_id")->from("profiles"),
                "p",
                "p.author_id",
                "=",
                "authors.id",
            )
            ->whereColumn("authors.id", "books.author_id")),
        [...bookTags(), tagPrefix() . "authors", tagPrefix() . "profiles"],
    );
});

test('an aliased join inside a subquery tags the unaliased table', function () {
    assertCachedUnderTags(
        (new Book)->whereExists(fn ($query) => $query
            ->select("profiles.id")
            ->from("profiles")
            ->join("authors as a", "a.id", "=", "profiles.author_id")
            ->whereColumn("a.id", "books.author_id")),
        [...bookTags(), tagPrefix() . "profiles", tagPrefix() . "authors"],
    );
});

test('joins inside a subquery tag each joined table before the tables its own subquery reads', function () {
    assertCachedUnderTags(
        (new Book)->whereExists(fn ($query) => $query
            ->select("authors.id")
            ->from("authors")
            ->join("profiles", "profiles.author_id", "=", "authors.id")
            ->join("publishers", function ($join) {
                $join->on("publishers.id", "=", "books.publisher_id")
                    ->whereExists(fn ($query) => $query
                        ->select("id")
                        ->from("printers")
                        ->whereColumn("printers.book_id", "books.id"));
            })
            ->whereColumn("authors.id", "books.author_id")),
        [
            ...bookTags(),
            tagPrefix() . "authors",
            tagPrefix() . "profiles",
            tagPrefix() . "publishers",
            tagPrefix() . "printers",
        ],
    );
});

test('aliased subquery from tags the unaliased table', function () {
    assertCachedUnderTags(
        (new Book)->whereExists(fn ($query) => $query
            ->select("id")
            ->from("authors as a")
            ->whereColumn("a.id", "books.author_id")),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('dotted has on a model keeping its own base builder still tags what it can walk', function () {
    assertCachedUnderTags(
        (new AuthorWithCustomBaseBuilderAndBooks)->has("books.author"),
        [
            tagPrefix()
                . "genealabslaravelmodelcachingtestsfixturesauthorwithcustombasebuilderandbooks",
            tagPrefix() . "authors",
            tagPrefix() . "books",
        ],
    );
});

test('union subquery tags its table', function () {
    assertCachedUnderTags(
        (new Book)
            ->where("id", ">", 0)
            ->union((new Book)->whereHas("author")->getQuery()),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

test('nested having tags no table beyond the queried one', function () {
    assertCachedUnderTags(
        (new Book)
            ->groupBy("author_id")
            ->having(fn ($query) => $query->havingRaw("count(*) > 0")),
        bookTags(),
    );
});

test('related table tag carries the related models cache prefix', function () {
    assertCachedUnderTags(
        (new BookWithPrefixedAuthor)->whereHas("author"),
        [
            tagPrefix() . "genealabslaravelmodelcachingtestsfixturesbookwithprefixedauthor",
            tagPrefix() . "books",
            tagPrefix() . "model-prefix:authors",
        ],
    );
});

test('aggregated table tag carries the related models cache prefix', function () {
    assertCachedUnderTags(
        (new BookWithPrefixedAuthor)->withCount("author"),
        [
            tagPrefix() . "genealabslaravelmodelcachingtestsfixturesbookwithprefixedauthor",
            tagPrefix() . "books",
            tagPrefix() . "model-prefix:authors",
        ],
    );
});

test('eager loaded relation tag carries the related models cache prefix', function () {
    assertCachedUnderTags(
        (new BookWithPrefixedAuthor)->with("author"),
        [
            tagPrefix() . "genealabslaravelmodelcachingtestsfixturesbookwithprefixedauthor",
            tagPrefix() . "model-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor",
            tagPrefix() . "books",
        ],
    );
});

test('cross prefix where has cache is busted when related table is written', function () {
    $author = PrefixedAuthor::create(["name" => "John", "email" => "john@example.com"]);
    Book::factory()->create(["author_id" => $author->id]);
    $query = fn () => (new BookWithPrefixedAuthor)
        ->whereHas("author", fn ($query) => $query->where("name", "John"))
        ->get();

    expect($query())->not->toBeEmpty();

    $author->name = "Jane";
    $author->save();

    expect($query())->toBeEmpty();
});

test('dotted where has through a dynamic relation is busted when the last related table is written', function () {
    Publisher::resolveRelationUsing(
        'dynamicPrefixedBooks',
        fn ($publisher) => $publisher->hasMany(BookWithPrefixedAuthor::class, 'publisher_id'),
    );
    $author = PrefixedAuthor::create(["name" => "John", "email" => "john@example.com"]);
    $publisher = Publisher::factory()->create();
    Book::factory()->create(["author_id" => $author->id, "publisher_id" => $publisher->id]);
    $query = fn () => (new Publisher)
        ->whereHas("dynamicPrefixedBooks.author", fn ($query) => $query->where("name", "John"))
        ->get();

    expect($query()->pluck("id")->all())->toBe([$publisher->id]);

    $author->name = "Jane";
    $author->save();

    expect($query())->toBeEmpty();
});

function tagPrefix(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:";
}

test('an exists subquery used twice tags its table', function () {
    $authoredBooks = DB::table("books")->whereColumn("books.author_id", "authors.id");

    assertCachedUnderTags(
        (new Author)->whereExists($authoredBooks)->orWhereExists($authoredBooks),
        [...authorTags(), tagPrefix() . "books"],
    );
});
