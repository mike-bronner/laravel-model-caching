<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

// getHavingClause() passed every member of the clause array to str_replace(),
// which only accepts a string or an array of them. having() stores an int or
// a float in "value", havingBetween() stores an array in "values", and both
// store a bool in "not". So having() raised a TypeError on a cold query, and
// havingBetween() folded both bounds into the literal "Array", giving every
// range one key.
//
// Book carries no global scope, so nothing but the clause under test moves
// these keys.
class HavingTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    public function testHavingWithNumericValueDoesNotThrow()
    {
        $books = (new Book)
            ->groupBy("author_id")
            ->having("author_id", ">", 1)
            ->get();
        $liveResults = (new UncachedBook)
            ->groupBy("author_id")
            ->having("author_id", ">", 1)
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
        $this->assertNotEmpty($books);
    }

    public function testHavingBetweenDoesNotThrow()
    {
        $books = (new Book)
            ->groupBy("author_id")
            ->havingBetween("author_id", [1, 3])
            ->get();
        $liveResults = (new UncachedBook)
            ->groupBy("author_id")
            ->havingBetween("author_id", [1, 3])
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
        $this->assertNotEmpty($books);
    }

    public function testHavingKeysTheBoundValueRatherThanItsType()
    {
        $lowBound = $this->cacheKey((new Book)->groupBy("author_id")->having("author_id", ">", 1));
        $highBound = $this->cacheKey((new Book)->groupBy("author_id")->having("author_id", ">", 3));

        $this->assertStringContainsString("-having_type_Basic_column_author_id_operator_>_value_1_boolean_and", $lowBound);
        $this->assertStringContainsString("-having_type_Basic_column_author_id_operator_>_value_3_boolean_and", $highBound);
    }

    public function testHavingBetweenKeysBothBoundsRatherThanTheWordArray()
    {
        $narrow = $this->cacheKey((new Book)->groupBy("author_id")->havingBetween("author_id", [1, 3]));
        $wide = $this->cacheKey((new Book)->groupBy("author_id")->havingBetween("author_id", [4, 9]));

        $this->assertStringContainsString("_values_1_3_", $narrow);
        $this->assertStringContainsString("_values_4_9_", $wide);
        $this->assertStringNotContainsString("Array", $narrow);
    }

    public function testHavingBetweenReturnsItsOwnRowsAfterAnotherRangeWasCached()
    {
        (new Book)->groupBy("author_id")->havingBetween("author_id", [1, 2])->get();

        $books = (new Book)->groupBy("author_id")->havingBetween("author_id", [3, 4])->get();
        $liveResults = (new UncachedBook)->groupBy("author_id")->havingBetween("author_id", [3, 4])->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    // havingRaw() keeps its bindings out of the clause array, so two calls
    // differing only in a bound value produce the same clause string. The
    // "having" binding channel is what tells them apart.
    public function testHavingRawKeysItsBindings()
    {
        $lowBound = $this->cacheKey((new Book)->groupBy("author_id")->havingRaw("author_id > ?", [1]));
        $highBound = $this->cacheKey((new Book)->groupBy("author_id")->havingRaw("author_id > ?", [3]));

        $this->assertNotEquals($lowBound, $highBound);
        $this->assertStringContainsString("-havingBindings_1", $lowBound);
        $this->assertStringContainsString("-havingBindings_3", $highBound);
    }

    public function testHavingRawReturnsItsOwnRowsAfterAnotherBindingWasCached()
    {
        (new Book)->groupBy("author_id")->havingRaw("author_id > ?", [1])->get();

        $books = (new Book)->groupBy("author_id")->havingRaw("author_id > ?", [3])->get();
        $liveResults = (new UncachedBook)->groupBy("author_id")->havingRaw("author_id > ?", [3])->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    // A raw having with no bindings is the one shape that already worked, so
    // its key has to stay exactly as it was or every cached one goes cold.
    public function testUnboundHavingRawKeyIsUnchanged()
    {
        $key = $this->cacheKey((new Book)->groupBy("author_id")->havingRaw("count(*) > 1"));

        $this->assertStringEndsWith(
            "-groupBy_author_id-having_type_Raw_sql_count(*)_>_1_boolean_and",
            $key,
        );
    }

    public function testOrHavingKeysDistinctlyFromHaving()
    {
        $conjunction = $this->cacheKey((new Book)
            ->groupBy("author_id")
            ->having("author_id", ">", 1)
            ->having("author_id", "<", 9));
        $disjunction = $this->cacheKey((new Book)
            ->groupBy("author_id")
            ->having("author_id", ">", 1)
            ->orHaving("author_id", "<", 9));

        $this->assertNotEquals($conjunction, $disjunction);
        $this->assertStringContainsString("_boolean_or", $disjunction);
    }
}
