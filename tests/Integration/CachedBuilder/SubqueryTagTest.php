<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseBuilderAndBooks;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithPrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use Illuminate\Database\Eloquent\Relations\Relation;

// A cached query has to be tagged with every table it reads, or a write to one
// of those tables leaves it serving stale rows. #623 covered the tables Laravel
// still holds as builders on `wheres`. These cover the ones it compiles to an
// Expression and drops — count-constrained has(), withCount()/withExists(), and
// whereIn() — plus the traversal branches #623 added without pinning.
//
// Each test asserts on the tag set directly rather than on a busted result,
// because an empty result after a write is also what a query that stopped
// caching altogether returns. The tag list is the mechanism; the result is not.

// Item 1. canUseExistsForExistenceCheck() sends only ">= 1" and "< 1" to
// addWhereExistsQuery(); every other operator and count goes to
// addWhereCountQuery(), which leaves a Basic where holding an Expression
// and no builder to walk.
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

// Item 4. has() also accepts an already-resolved Relation, which Laravel
// takes straight past the is_string() guard on its relation-resolution
// block. That shape never reaches getRelationWithoutConstraints(), so the
// choke point records nothing for it and has() must record it itself. The
// count constraint is what makes this discriminating: ">= 1" would leave a
// builder on `wheres` for the tag walk to find on its own.
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

// Item 3. withCount()/withExists() put their subquery in `columns`, as a
// selectSub() Expression and a selectRaw() string respectively. Neither is
// walked, and neither keeps the builder.
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

// `withCount("author as writer_count")` aliases the result column. The
// relation is the first space-separated segment; the alias is not a
// relation and must not be resolved as one.
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

// The whereIn() carve-out #623 named. isQueryable() values are compiled to
// an Expression by createSub(), the same way the count-constrained has() is.
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

// Item 4. The `Sub` where-type, which where() builds when the value is a
// closure. #623 added the type to the list without a test for it.
test('sub where clause tags its subquery table', function () {
    assertCachedUnderTags(
        (new Book)->where("id", ">", fn ($query) => $query
            ->selectRaw("min(id)")
            ->from("authors")),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

// Item 4. A join clause carries its own `wheres`, walked separately from
// the outer query's.
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

// A join may name its table with an alias. The tag has to be the table, not
// the aliased spelling: a write to `authors` flushes "…:authors", and
// "…:authors as a" is a tag nothing ever flushes.
test('aliased join tags the unaliased table', function () {
    assertCachedUnderTags(
        (new Book)->join("authors as a", "books.author_id", "=", "a.id"),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

// The same alias handling on the subquery path, where the aliased name
// arrives as the subquery builder's own `from`.
test('aliased subquery from tags the unaliased table', function () {
    assertCachedUnderTags(
        (new Book)->whereExists(fn ($query) => $query
            ->select("id")
            ->from("authors as a")
            ->whereColumn("a.id", "books.author_id")),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

// A model keeping its own base query builder has no recorder to record to.
// The walk has to find that out and do nothing: calling the recorder anyway
// raises "call to undefined method" on the consumer's builder, and turns a
// supported configuration into a fatal error. The tags that survive are the
// ones CacheTags finds by walking `wheres` itself.
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

// Item 4. A union's own builder, which the outer query's `wheres` never
// reach.
test('union subquery tags its table', function () {
    assertCachedUnderTags(
        (new Book)
            ->where("id", ">", 0)
            ->union((new Book)->whereHas("author")->getQuery()),
        [...bookTags(), tagPrefix() . "authors"],
    );
});

// Item 4, the branch that was cut instead of tested. Of the six having
// types Laravel builds, only the nested one carries a builder, and that
// builder is forNestedWhere(), whose `from` is the outer query's own table.
// A nested having can name no other table, so there is nothing for a
// havings walk to contribute.
test('nested having tags no table beyond the queried one', function () {
    assertCachedUnderTags(
        (new Book)
            ->groupBy("author_id")
            ->having(fn ($query) => $query->havingRaw("count(*) > 0")),
        bookTags(),
    );
});

// Item 2. PrefixedAuthor reads the same `authors` table as Author and
// differs only in declaring $cachePrefix, so it flushes
// "…:model-prefix:authors". Tagging the related table with the querying
// model's prefix would write "…:authors", which that write never touches.
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

// Item 2 again, on the eager-load tag rather than the subquery one. That
// tag names the related *class*, and PrefixedAuthor flushes it as
// "…:model-prefix:…prefixedauthor", so the querying model's prefix misses
// it the same way.
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

function tagPrefix(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:";
}
