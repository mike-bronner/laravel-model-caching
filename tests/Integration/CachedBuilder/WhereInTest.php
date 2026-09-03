<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class WhereInTest extends IntegrationTestCase
{
    public function testWhereInUsingCollectionQuery()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-author_id_in_1_2_3_4");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];
        $authors = (new UncachedAuthor)
            ->where("id", "<", 5)
            ->get(["id"]);

        $books = (new Book)
            ->whereIn("author_id", $authors)
            ->get();
        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedBook)
            ->whereIn("author_id", $authors)
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }

    public function testWhereInWhenSetIsEmpty()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_in_-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
        $authors = (new Author)
            ->whereIn("id", [])
            ->get();
        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereIn("id", [])
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $authors->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }

    public function testBindingsAreCorrectWithMultipleWhereInClauses()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_in_John-id_in_-name_in_Mike-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
        $authors = (new Author)
            ->whereIn("name", ["John"])
            ->whereIn("id", [])
            ->whereIn("name", ["Mike"])
            ->get();
        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereIn("name", ["Mike"])
            ->whereIn("id", [])
            ->whereIn("name", ["John"])
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $authors->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }

    public function testWhereInUsesCorrectBindings()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-id_in_1_2_3_4_5-id_between_1_99999-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $authors = (new Author)
            ->whereIn('id', [1,2,3,4,5])
            ->whereBetween('id', [1, 99999])
            ->get();
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereIn('id', [1,2,3,4,5])
            ->whereBetween('id', [1, 99999])
            ->get();

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testWhereInWithPercentCharacterInValueDoesNotThrow()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-name_in_10%25_20%25-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $authors = (new Author)
            ->whereIn('name', ['10%', '20%'])
            ->get();
        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereIn('name', ['10%', '20%'])
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $authors->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }

    public function testWhereInWithSubqueryContainingSingleWhereClause()
    {
        $books = (new Book)
            ->whereIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "John");
            })
            ->get();

        $liveResults = (new UncachedBook)
            ->whereIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "John");
            })
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    public function testWhereInWithSubqueryContainingMultipleWhereClauses()
    {
        $books = (new Book)
            ->whereIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "John")
                    ->where("id", ">", 0);
            })
            ->get();

        $liveResults = (new UncachedBook)
            ->whereIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "John")
                    ->where("id", ">", 0);
            })
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    public function testNestedWhereNotInWithSubqueryDoesNotCrashWithUuidException()
    {
        $books = (new Book)
            ->whereNotIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "John");
            })
            ->whereNotIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "Mike");
            })
            ->get();

        $liveResults = (new UncachedBook)
            ->whereNotIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "John");
            })
            ->whereNotIn("author_id", function ($query) {
                $query->select("id")
                    ->from("authors")
                    ->where("name", "=", "Mike");
            })
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    public function testWhereInWithNonUuidStringValuesSkipsFromBytes()
    {
        $books = (new Book)
            ->whereIn("author_id", function ($query) {
                $query->selectRaw("distinct id")
                    ->from("authors");
            })
            ->get();

        $liveResults = (new UncachedBook)
            ->whereIn("author_id", function ($query) {
                $query->selectRaw("distinct id")
                    ->from("authors");
            })
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }
}
