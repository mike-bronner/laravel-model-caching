<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;

// make() read the where clauses, the having clauses, the columns, the orders,
// the limit and the offset, and nothing else. groupBy, joins, unions and
// distinct never reached the key, so two queries differing only in one of them
// shared a key and the second read the first one's rows.
//
// Book carries no global scope, so nothing but the part under test moves these
// keys.
class QueryPartCacheKeyTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    public function testGroupByColumnsReachTheKey()
    {
        $byAuthor = $this->cacheKey((new Book)->groupBy("author_id"));
        $byPublisher = $this->cacheKey((new Book)->groupBy("publisher_id"));

        $this->assertStringEndsWith("-groupBy_author_id", $byAuthor);
        $this->assertStringEndsWith("-groupBy_publisher_id", $byPublisher);
    }

    public function testGroupByReturnsItsOwnRowsAfterAnotherGroupingWasCached()
    {
        (new Book)->groupBy("author_id")->get();

        $books = (new Book)->groupBy("publisher_id")->get();
        $liveResults = (new UncachedBook)->groupBy("publisher_id")->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    public function testGroupByRawKeysItsBindings()
    {
        $lowBound = $this->cacheKey((new Book)->groupByRaw("author_id + ?", [1]));
        $highBound = $this->cacheKey((new Book)->groupByRaw("author_id + ?", [2]));

        $this->assertNotEquals($lowBound, $highBound);
        $this->assertStringContainsString("-groupByBindings_1", $lowBound);
    }

    public function testJoinedTableAndTypeReachTheKey()
    {
        $inner = $this->cacheKey((new Book)->join("authors", "authors.id", "=", "books.author_id"));
        $left = $this->cacheKey((new Book)->leftJoin("authors", "authors.id", "=", "books.author_id"));

        $this->assertStringContainsString("-join_inner_authors_", $inner);
        $this->assertStringContainsString("-join_left_authors_", $left);
        $this->assertNotEquals($inner, $left);
    }

    // Two joins of one table on different columns share their type, their
    // table and their cache tags. Only the hashed ON conditions separate them.
    public function testJoinsOnTheSameTableWithDifferentConditionsKeyDistinctly()
    {
        $byAuthor = $this->cacheKey((new Book)->join("authors", "authors.id", "=", "books.author_id"));
        $byPublisher = $this->cacheKey((new Book)->join("authors", "authors.id", "=", "books.publisher_id"));

        $this->assertNotEquals($byAuthor, $byPublisher);
    }

    public function testUnjoinedQueryKeyIsUnchanged()
    {
        $key = $this->cacheKey((new Book)->where("title", "a"));

        $this->assertStringEndsWith("-title_=_a", $key);
    }

    public function testDistinctReachesTheKey()
    {
        $distinct = $this->cacheKey((new Book)->distinct());
        $plain = $this->cacheKey(new Book);

        $this->assertStringEndsWith("-distinct", $distinct);
        $this->assertStringNotContainsString("-distinct", $plain);
    }

    public function testDistinctColumnsReachTheKey()
    {
        $key = $this->cacheKey((new Book)->distinct("author_id"));

        $this->assertStringEndsWith("-distinct_author_id", $key);
    }

    public function testUnionsReachTheKey()
    {
        $first = $this->cacheKey((new Book)->where("id", 1)->union(DB::table("books")->where("id", 2)));
        $second = $this->cacheKey((new Book)->where("id", 1)->union(DB::table("books")->where("id", 3)));

        $this->assertStringContainsString("-union_", $first);
        $this->assertNotEquals($first, $second);
    }

    public function testUnionReturnsItsOwnRowsAfterAnotherUnionWasCached()
    {
        (new Book)->where("id", 1)->union(DB::table("books")->where("id", 2))->get();

        $books = (new Book)->where("id", 1)->union(DB::table("books")->where("id", 3))->get();
        $liveResults = (new UncachedBook)->where("id", 1)->union(DB::table("books")->where("id", 3))->get();

        $this->assertEquals($liveResults->pluck("id")->sort()->values(), $books->pluck("id")->sort()->values());
    }

    public function testUnionAllKeysDistinctlyFromUnion()
    {
        $union = $this->cacheKey((new Book)->where("id", 1)->union(DB::table("books")->where("id", 2)));
        $unionAll = $this->cacheKey((new Book)->where("id", 1)->unionAll(DB::table("books")->where("id", 2)));

        $this->assertNotEquals($union, $unionAll);
        $this->assertStringContainsString("-union_all_", $unionAll);
    }

    // The backstop. Nothing names lock, indexHint, groupLimit, unionLimit,
    // unionOffset or unionOrders, and each of them changes which rows come
    // back. They reach the key through the unkeyed-property hash instead.
    public function testUnkeyedQueryPropertiesReachTheKeyThroughTheBackstopHash()
    {
        $unlocked = $this->cacheKey((new Book)->where("id", 1));
        $locked = $this->cacheKey((new Book)->where("id", 1)->lockForUpdate());
        $shared = $this->cacheKey((new Book)->where("id", 1)->sharedLock());

        $this->assertStringNotContainsString("-q", $unlocked);
        $this->assertStringContainsString("-q", $locked);
        $this->assertNotEquals($unlocked, $locked);
        $this->assertNotEquals($locked, $shared);
    }

    public function testUnionOrderingReachesTheKeyThroughTheBackstopHash()
    {
        $ascending = $this->cacheKey((new Book)
            ->where("id", 1)
            ->union(DB::table("books")->where("id", 2))
            ->orderBy("id"));
        $descending = $this->cacheKey((new Book)
            ->where("id", 1)
            ->union(DB::table("books")->where("id", 2))
            ->orderBy("id", "desc"));

        $this->assertNotEquals($ascending, $descending);
    }

    // Both property lists are closed sets. A member silently added to either
    // one stops that property reaching the key, which is the exact failure
    // this backstop exists to prevent, and no other test would notice.
    public function testTheKeyedAndInfrastructurePropertyListsArePinned()
    {
        $constants = (new ReflectionClass(CacheKey::class))->getConstants();

        $this->assertSame(
            [
                "beforeQueryCallbacks",
                "columns",
                "distinct",
                "from",
                "groups",
                "havings",
                "joins",
                "limit",
                "offset",
                "orders",
                "unions",
                "wheres",
            ],
            $constants["KEYED_QUERY_PROPERTIES"],
        );
        $this->assertSame(
            [
                "bindings",
                "bitwiseOperators",
                "connection",
                "grammar",
                "operators",
                "processor",
                "useWritePdo",
            ],
            $constants["UNKEYED_INFRASTRUCTURE_PROPERTIES"],
        );
    }

    // Every property named in either list has to exist on the builder. A
    // rename in Laravel would otherwise leave a live property unaccounted for
    // while the list still looks complete.
    public function testEveryListedPropertyExistsOnTheQueryBuilder()
    {
        $constants = (new ReflectionClass(CacheKey::class))->getConstants();
        $builderProperties = array_keys(get_object_vars(DB::table("books")));

        foreach (array_merge(
            $constants["KEYED_QUERY_PROPERTIES"],
            $constants["UNKEYED_INFRASTRUCTURE_PROPERTIES"],
        ) as $property) {
            $this->assertContains(
                $property,
                $builderProperties,
                "CacheKey names '{$property}', which " . QueryBuilder::class . " no longer has.",
            );
        }
    }
}
