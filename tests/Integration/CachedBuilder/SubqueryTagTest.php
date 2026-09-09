<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBaseBuilderAndBooks;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithPrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionMethod;

// A cached query has to be tagged with every table it reads, or a write to one
// of those tables leaves it serving stale rows. #623 covered the tables Laravel
// still holds as builders on `wheres`. These cover the ones it compiles to an
// Expression and drops — count-constrained has(), withCount()/withExists(), and
// whereIn() — plus the traversal branches #623 added without pinning.
//
// Each test asserts on the tag set directly rather than on a busted result,
// because an empty result after a write is also what a query that stopped
// caching altogether returns. The tag list is the mechanism; the result is not.
class SubqueryTagTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    private function prefix() : string
    {
        return "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:";
    }

    private function bookTags() : array
    {
        return [
            $this->prefix() . "genealabslaravelmodelcachingtestsfixturesbook",
            $this->prefix() . "books",
        ];
    }

    /**
     * Assert the query cached its result under exactly the given tags.
     *
     * Laravel's TagSet namespaces a tagged entry by the tags it was written
     * with, so a lookup under the wrong list — or a list missing one tag —
     * finds nothing. Passing the expected list to the reader is therefore the
     * assertion: it fails if the writer tagged anything else.
     */
    private function assertCachedUnderTags($query, array $tags) : void
    {
        $key = sha1($this->cacheKey($query));
        $query->get();

        $this->assertNotNull(
            $this->cache()
                ->tags($tags)
                ->get($key),
            "The query was not cached under the expected tags.",
        );
    }

    // Item 1. canUseExistsForExistenceCheck() sends only ">= 1" and "< 1" to
    // addWhereExistsQuery(); every other operator and count goes to
    // addWhereCountQuery(), which leaves a Basic where holding an Expression
    // and no builder to walk.
    public function testCountConstrainedHasTagsTheRelatedTable()
    {
        $this->assertCachedUnderTags(
            (new Author)->has("books", ">", 1),
            [
                $this->prefix() . "genealabslaravelmodelcachingtestsfixturesauthor",
                $this->prefix() . "authors",
                $this->prefix() . "books",
            ],
        );
    }

    // Item 4. has() also accepts an already-resolved Relation, which Laravel
    // takes straight past the is_string() guard on its relation-resolution
    // block. That shape never reaches getRelationWithoutConstraints(), so the
    // choke point records nothing for it and has() must record it itself. The
    // count constraint is what makes this discriminating: ">= 1" would leave a
    // builder on `wheres` for the tag walk to find on its own.
    public function testHasWithARelationInstanceTagsTheRelatedTable()
    {
        $relation = Relation::noConstraints(fn () => (new Author)->books());

        $this->assertCachedUnderTags(
            (new Author)->has($relation, ">", 1),
            [
                $this->prefix() . "genealabslaravelmodelcachingtestsfixturesauthor",
                $this->prefix() . "authors",
                $this->prefix() . "books",
            ],
        );
    }

    public function testZeroCountHasTagsTheRelatedTable()
    {
        $this->assertCachedUnderTags(
            (new Author)->has("books", "=", 0),
            [
                $this->prefix() . "genealabslaravelmodelcachingtestsfixturesauthor",
                $this->prefix() . "authors",
                $this->prefix() . "books",
            ],
        );
    }

    public function testCountConstrainedHasCacheIsBustedWhenRelatedTableIsWritten()
    {
        $author = Author::factory()->create(["name" => "John"]);
        $books = Book::factory()->count(2)->create(["author_id" => $author->id]);
        $query = fn () => (new Author)
            ->where("id", $author->id)
            ->has("books", ">", 1)
            ->get();

        $this->assertNotEmpty($query());

        $books->last()->delete();

        $this->assertEmpty($query());
    }

    // Item 3. withCount()/withExists() put their subquery in `columns`, as a
    // selectSub() Expression and a selectRaw() string respectively. Neither is
    // walked, and neither keeps the builder.
    public function testWithCountTagsTheAggregatedTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->withCount("author"),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    public function testWithExistsTagsTheAggregatedTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->withExists("author"),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    // `withCount("author as writer_count")` aliases the result column. The
    // relation is the first space-separated segment; the alias is not a
    // relation and must not be resolved as one.
    public function testAliasedWithCountTagsTheAggregatedTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->withCount("author as writer_count"),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    public function testWithCountCacheIsBustedWhenAggregatedTableIsWritten()
    {
        $author = Author::factory()->create(["name" => "John"]);
        Book::factory()->create(["author_id" => $author->id]);
        $query = fn () => (new Book)
            ->where("author_id", $author->id)
            ->withCount("author")
            ->get()
            ->first()
            ->author_count;

        $this->assertSame(1, $query());

        $author->delete();

        $this->assertSame(0, $query());
    }

    // The whereIn() carve-out #623 named. isQueryable() values are compiled to
    // an Expression by createSub(), the same way the count-constrained has() is.
    public function testWhereInSubqueryTagsTheSubqueryTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->whereIn("author_id", fn ($query) => $query
                ->select("id")
                ->from("authors")),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    public function testWhereNotInSubqueryTagsTheSubqueryTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->whereNotIn("author_id", fn ($query) => $query
                ->select("id")
                ->from("authors")),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    // Item 4. The `Sub` where-type, which where() builds when the value is a
    // closure. #623 added the type to the list without a test for it.
    public function testSubWhereClauseTagsItsSubqueryTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->where("id", ">", fn ($query) => $query
                ->selectRaw("min(id)")
                ->from("authors")),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    // Item 4. A join clause carries its own `wheres`, walked separately from
    // the outer query's.
    public function testJoinClauseSubqueryTagsItsTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->join("authors", function ($join) {
                $join->on("books.author_id", "=", "authors.id")
                    ->whereExists(fn ($query) => $query
                        ->select("id")
                        ->from("profiles")
                        ->whereColumn("profiles.author_id", "authors.id"));
            }),
            [
                ...$this->bookTags(),
                $this->prefix() . "authors",
                $this->prefix() . "profiles",
            ],
        );
    }

    // A join may name its table with an alias. The tag has to be the table, not
    // the aliased spelling: a write to `authors` flushes "…:authors", and
    // "…:authors as a" is a tag nothing ever flushes.
    public function testAliasedJoinTagsTheUnaliasedTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->join("authors as a", "books.author_id", "=", "a.id"),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    // The same alias handling on the subquery path, where the aliased name
    // arrives as the subquery builder's own `from`.
    public function testAliasedSubqueryFromTagsTheUnaliasedTable()
    {
        $this->assertCachedUnderTags(
            (new Book)->whereExists(fn ($query) => $query
                ->select("id")
                ->from("authors as a")
                ->whereColumn("a.id", "books.author_id")),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    // A model keeping its own base query builder has no recorder to record to.
    // The walk has to find that out and do nothing: calling the recorder anyway
    // raises "call to undefined method" on the consumer's builder, and turns a
    // supported configuration into a fatal error. The tags that survive are the
    // ones CacheTags finds by walking `wheres` itself.
    public function testDottedHasOnAModelKeepingItsOwnBaseBuilderStillTagsWhatItCanWalk()
    {
        $this->assertCachedUnderTags(
            (new AuthorWithCustomBaseBuilderAndBooks)->has("books.author"),
            [
                $this->prefix()
                    . "genealabslaravelmodelcachingtestsfixturesauthorwithcustombasebuilderandbooks",
                $this->prefix() . "authors",
                $this->prefix() . "books",
            ],
        );
    }

    // Item 4. A union's own builder, which the outer query's `wheres` never
    // reach.
    public function testUnionSubqueryTagsItsTable()
    {
        $this->assertCachedUnderTags(
            (new Book)
                ->where("id", ">", 0)
                ->union((new Book)->whereHas("author")->getQuery()),
            [...$this->bookTags(), $this->prefix() . "authors"],
        );
    }

    // Item 4, the branch that was cut instead of tested. Of the six having
    // types Laravel builds, only the nested one carries a builder, and that
    // builder is forNestedWhere(), whose `from` is the outer query's own table.
    // A nested having can name no other table, so there is nothing for a
    // havings walk to contribute.
    public function testNestedHavingTagsNoTableBeyondTheQueriedOne()
    {
        $this->assertCachedUnderTags(
            (new Book)
                ->groupBy("author_id")
                ->having(fn ($query) => $query->havingRaw("count(*) > 0")),
            $this->bookTags(),
        );
    }

    // Item 2. PrefixedAuthor reads the same `authors` table as Author and
    // differs only in declaring $cachePrefix, so it flushes
    // "…:model-prefix:authors". Tagging the related table with the querying
    // model's prefix would write "…:authors", which that write never touches.
    public function testRelatedTableTagCarriesTheRelatedModelsCachePrefix()
    {
        $this->assertCachedUnderTags(
            (new BookWithPrefixedAuthor)->whereHas("author"),
            [
                $this->prefix() . "genealabslaravelmodelcachingtestsfixturesbookwithprefixedauthor",
                $this->prefix() . "books",
                $this->prefix() . "model-prefix:authors",
            ],
        );
    }

    public function testAggregatedTableTagCarriesTheRelatedModelsCachePrefix()
    {
        $this->assertCachedUnderTags(
            (new BookWithPrefixedAuthor)->withCount("author"),
            [
                $this->prefix() . "genealabslaravelmodelcachingtestsfixturesbookwithprefixedauthor",
                $this->prefix() . "books",
                $this->prefix() . "model-prefix:authors",
            ],
        );
    }

    // Item 2 again, on the eager-load tag rather than the subquery one. That
    // tag names the related *class*, and PrefixedAuthor flushes it as
    // "…:model-prefix:…prefixedauthor", so the querying model's prefix misses
    // it the same way.
    public function testEagerLoadedRelationTagCarriesTheRelatedModelsCachePrefix()
    {
        $this->assertCachedUnderTags(
            (new BookWithPrefixedAuthor)->with("author"),
            [
                $this->prefix() . "genealabslaravelmodelcachingtestsfixturesbookwithprefixedauthor",
                $this->prefix() . "model-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor",
                $this->prefix() . "books",
            ],
        );
    }

    public function testCrossPrefixWhereHasCacheIsBustedWhenRelatedTableIsWritten()
    {
        $author = PrefixedAuthor::create(["name" => "John", "email" => "john@example.com"]);
        Book::factory()->create(["author_id" => $author->id]);
        $query = fn () => (new BookWithPrefixedAuthor)
            ->whereHas("author", fn ($query) => $query->where("name", "John"))
            ->get();

        $this->assertNotEmpty($query());

        $author->name = "Jane";
        $author->save();

        $this->assertEmpty($query());
    }
}
