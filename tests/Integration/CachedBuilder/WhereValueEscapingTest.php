<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;

// The key format joins its segments with "-" and "_". A value containing either
// used to be written into the key raw, so it could spell out a different query's
// clauses and read that query's cached rows.
class WhereValueEscapingTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    private function keyPrefix() : string
    {
        return "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite"
            . ":books:genealabslaravelmodelcachingtestsfixturesbook";
    }

    public function testValueContainingSeparatorsDoesNotCollideWithTwoSeparateClauses()
    {
        $oneValue = (new Book)->where("title", "a-title_=_b");
        $twoClauses = (new Book)->where("title", "a")->where("title", "b");

        $this->assertEquals(
            $this->keyPrefix() . "-title_=_a%2Dtitle%5F=%5Fb",
            $this->cacheKey($oneValue)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-title_=_a-title_=_b",
            $this->cacheKey($twoClauses)
        );
        $this->assertNotEquals(
            $this->cacheKey($oneValue),
            $this->cacheKey($twoClauses)
        );
    }

    public function testValueContainingSeparatorsReturnsItsOwnResultsAfterTheCollidingQueryWasCached()
    {
        $book = (new UncachedBook)->first();
        $book->title = "a-title_=_b";
        $book->save();

        $collidingQuery = (new Book)->where("title", "a")->where("title", "b");
        $collidingKey = sha1($this->cacheKey($collidingQuery));
        $collidingResults = $collidingQuery->get();
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cached = $this->cache()
            ->tags($tags)
            ->get($collidingKey);

        $this->assertNotNull(
            $cached,
            "The first query must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEmpty($collidingResults);

        $results = (new Book)->where("title", "a-title_=_b")->get();
        $liveResults = (new UncachedBook)->where("title", "a-title_=_b")->get();

        $this->assertCount(1, $results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    // "%" is the escape character, so it has to be escaped too, or the encoding
    // is not reversible: the value "a%2Db" and the value "a-b" would both come
    // out as "a%2Db" and share a key.
    public function testPercentIsEscapedSoTheEncodingStaysReversible()
    {
        $literal = (new Book)->where("title", "a%2Db");
        $separator = (new Book)->where("title", "a-b");

        $this->assertEquals(
            $this->keyPrefix() . "-title_=_a%252Db",
            $this->cacheKey($literal)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-title_=_a%2Db",
            $this->cacheKey($separator)
        );
        $this->assertNotEquals(
            $this->cacheKey($literal),
            $this->cacheKey($separator)
        );
    }

    // Nothing else here would fail if the encoding were rewritten to replace
    // its pairs sequentially with "%" reached last, which would encode the "%"
    // of every "%2D" it had just written and turn "-" into "%252D". This pins
    // the output against that. Verified by mutation: swapping strtr() for such
    // a loop reddens this test, and reordering the pairs is what does it.
    public function testSeparatorEncodingIsNotDoubleEncoded()
    {
        $this->assertEquals(
            $this->keyPrefix() . "-title_=_a%2Db%5Fc%25d",
            $this->cacheKey((new Book)->where("title", "a-b_c%d"))
        );
    }

    // The escaping only rewrites the three characters above, so a value holding
    // none of them keeps the exact bytes it had before — no existing cache entry
    // for an ordinary value goes cold on upgrade.
    public function testKeysForValuesWithoutSeparatorsAreUnchanged()
    {
        $cases = [
            "a" => "-title_=_a",
            "plain title" => "-title_=_plain title",
            "Ünïcödé" => "-title_=_Ünïcödé",
            "0" => "-title_=_0",
            "a.b:c/d" => "-title_=_a.b:c/d",
        ];

        foreach ($cases as $value => $expectedClause) {
            $this->assertEquals(
                $this->keyPrefix() . $expectedClause,
                $this->cacheKey((new Book)->where("title", (string) $value)),
                "The key for the value {$value} must not change"
            );
        }
    }

    public function testBothBetweenBoundsAreEscaped()
    {
        $query = (new Book)->whereBetween("title", ["a-b", "c_d"]);

        $this->assertEquals(
            $this->keyPrefix() . "-title_between_a%2Db_c%5Fd",
            $this->cacheKey($query)
        );
    }

    public function testRowValuesAreEscaped()
    {
        $query = (new Book)->whereRowValues(["title", "description"], "=", ["a-b", "c_d"]);

        $this->assertEquals(
            $this->keyPrefix() . "-title_description_=_a%2Db_c%5Fd",
            $this->cacheKey($query)
        );
    }

    // "_" joins the values of an "in" clause, so one value containing "_" spelt
    // out two values and shared their key.
    public function testWhereInValuesAreEscaped()
    {
        $oneValue = (new Book)->whereIn("title", ["1_2"]);
        $twoValues = (new Book)->whereIn("title", ["1", "2"]);

        $this->assertEquals(
            $this->keyPrefix() . "-title_in_1%5F2",
            $this->cacheKey($oneValue)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-title_in_1_2",
            $this->cacheKey($twoValues)
        );
        $this->assertNotEquals(
            $this->cacheKey($oneValue),
            $this->cacheKey($twoValues)
        );
    }

    public function testWhereNotInValuesAreEscaped()
    {
        $this->assertEquals(
            $this->keyPrefix() . "-title_notin_a%2Db",
            $this->cacheKey((new Book)->whereNotIn("title", ["a-b"]))
        );
    }

    // The third return path of getInAndNotInClauses(), reached when a value
    // contains "?": that path treats the "?" as a placeholder and rebuilds the
    // clause by substituting bindings for it. Both the value and the
    // substituted binding land in the key, so both are escaped.
    //
    // The repeated fragment in the expected key is that substitution folding
    // the value back into itself. It is pre-existing behaviour of this path,
    // not something the escaping introduced; it is pinned here rather than
    // corrected, because rewriting that path is not what this change is for.
    public function testWhereInValuesContainingAPlaceholderAreEscaped()
    {
        $key = $this->cacheKey((new Book)->whereIn("title", ["a_b?c"]));

        $this->assertEquals(
            $this->keyPrefix() . "-title_in_a%5Fba%5Fb?cc",
            $key
        );
        $this->assertStringNotContainsString("_b?c", $key);
    }

    // The binary-UUID branch of getInAndNotInClauses() recognises its value by
    // being exactly 16 bytes long. Escaping runs below that branch precisely so
    // a 0x2D or 0x5F byte inside a UUID cannot stretch it past 16 and send it
    // down the wrong path. This UUID contains both bytes.
    public function testBinaryUuidWhereInValueIsNotEscaped()
    {
        $uuid = Uuid::fromString("2d5f2d5f-2d5f-4d5f-8d5f-2d5f2d5f2d5f");
        $bytes = $uuid->getBytes();

        $this->assertSame(16, strlen($bytes));
        $this->assertStringContainsString("-", $bytes);
        $this->assertStringContainsString("_", $bytes);

        $this->assertEquals(
            $this->keyPrefix() . "-id_in_{$uuid->toString()}",
            $this->cacheKey((new Book)->whereIn("id", [$bytes]))
        );
    }
}
