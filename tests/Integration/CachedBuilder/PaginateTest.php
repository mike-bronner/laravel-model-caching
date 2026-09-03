<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StoreWithUncachedBooks;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
* @SuppressWarnings(PHPMD.TooManyPublicMethods)
* @SuppressWarnings(PHPMD.TooManyMethods)
 */
class PaginateTest extends IntegrationTestCase
{
    public function testPaginationIsCached()
    {
        $authors = (new Author)
            ->paginate(3);

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-paginate_by_3_page_1");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->paginate(3);

        $this->assertEquals($cachedResults, $authors);
        $this->assertEquals($liveResults->pluck("email"), $authors->pluck("email"));
        $this->assertEquals($liveResults->pluck("name"), $authors->pluck("name"));
    }

    public function testPaginationReturnsCorrectLinks()
    {
        $booksPage1 = (new Book)
            ->paginate(2);
        $booksPage2 = (new Book)
            ->paginate(2, ['*'], null, 2);
        $booksPage24 = (new Book)
            ->paginate(2, ['*'], null, 24);

        $this->assertCount(2, $booksPage1);
        $this->assertCount(2, $booksPage2);
        $this->assertCount(2, $booksPage24);
        $this->assertActivePageInLinks(1, (string) $booksPage1->links());
        $this->assertActivePageInLinks(2, (string) $booksPage2->links());
        $this->assertActivePageInLinks(24, (string) $booksPage24->links());
    }

    public function testPaginationWithOptionsReturnsCorrectLinks()
    {
        $booksPage1 = (new Book)
            ->paginate(2);
        $booksPage2 = (new Book)
            ->paginate(2, ['*'], null, 2);
        $booksPage24 = (new Book)
            ->paginate(2, ['*'], null, 24);

        $this->assertCount(2, $booksPage1);
        $this->assertCount(2, $booksPage2);
        $this->assertCount(2, $booksPage24);
        $this->assertActivePageInLinks(1, (string) $booksPage1->links());
        $this->assertActivePageInLinks(2, (string) $booksPage2->links());
        $this->assertActivePageInLinks(24, (string) $booksPage24->links());
    }

    public function testPaginationWithCustomOptionsReturnsCorrectLinks()
    {
        $booksPage1 = (new Book)
            ->paginate('2');
        $booksPage2 = (new Book)
            ->paginate('2', ['*'], 'pages', 2);
        $booksPage24 = (new Book)
            ->paginate('2', ['*'], 'pages', 24);

        $this->assertCount(2, $booksPage1);
        $this->assertCount(2, $booksPage2);
        $this->assertCount(2, $booksPage24);
        $this->assertActivePageInLinks(1, (string) $booksPage1->links());
        $this->assertActivePageInLinks(2, (string) $booksPage2->links());
        $this->assertActivePageInLinks(24, (string) $booksPage24->links());
    }

    private function assertActivePageInLinks(int $page, string $linksHtml): void
    {
        $this->assertMatchesRegularExpression(
            '/aria-current="page">\s*<span[^>]*>' . $page . '<\/span>/s',
            $linksHtml,
            "Expected page {$page} to be marked as the active page in pagination links."
        );
    }

    public function testCustomPageNamePagination()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-paginate_by_3_custom-page_1");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $authors = (new Author)
            ->paginate(3, ["*"], "custom-page");
        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->paginate(3, ["*"], "custom-page");

        $this->assertEquals($cachedResults, $authors);
        $this->assertEquals($liveResults->pluck("email"), $authors->pluck("email"));
        $this->assertEquals($liveResults->pluck("name"), $authors->pluck("name"));
    }

    public function testCustomPageNamePaginationFetchesCorrectPages()
    {
        $authors1 = (new Author)
            ->paginate(3, ["*"], "custom-page", 1);
        $authors2 = (new Author)
            ->paginate(3, ["*"], "custom-page", 2);

        $this->assertNotEquals($authors1->pluck("id"), $authors2->pluck("id"));
    }

    public function testPaginatorBaseUrlReflectsCurrentRequest()
    {
        // First request from domain A
        \Illuminate\Pagination\Paginator::currentPathResolver(function () {
            return "https://domain-a.com/authors";
        });

        $authorsFromDomainA = (new Author)->paginate(3);
        $this->assertStringContainsString(
            "domain-a.com",
            $authorsFromDomainA->url(1)
        );

        // Second request from domain B — should use domain B's URL, not cached domain A
        \Illuminate\Pagination\Paginator::currentPathResolver(function () {
            return "https://domain-b.com/authors";
        });

        $authorsFromDomainB = (new Author)->paginate(3);
        $this->assertStringContainsString(
            "domain-b.com",
            $authorsFromDomainB->url(1),
            "Cached paginator should use current request domain, not the domain that populated the cache"
        );
    }

    public function testCachedPaginatorPathIsReappliedFromCurrentRequest()
    {
        // Populate cache with a specific path
        \Illuminate\Pagination\Paginator::currentPathResolver(function () {
            return "https://original.com/users";
        });

        (new Author)->paginate(3);

        // Retrieve from cache with a different path
        \Illuminate\Pagination\Paginator::currentPathResolver(function () {
            return "https://different.com/users";
        });

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-paginate_by_3_page_1");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedRaw = $this->cache()->tags($tags)->get($key)['value'];

        // The cached raw paginator may have the old URL, but when retrieved
        // through the model caching layer, it should have the current URL
        $result = (new Author)->paginate(3);

        $this->assertStringContainsString(
            "different.com",
            $result->url(1),
            "Paginator path should be re-applied from current request after cache retrieval"
        );
        $this->assertStringNotContainsString(
            "original.com",
            $result->url(1),
            "Paginator should not retain the original cached domain"
        );
    }

    // disableModelCaching() cannot exercise this: it makes newQuery() return a
    // plain Eloquent builder, so Buildable::paginate() never runs and the test
    // measures Laravel's own paginate() instead. A cachable model eager-loading
    // an uncachable one is the construction that reaches Buildable::paginate()
    // with isCachable() false.
    public function testUncachedPaginationHonorsProvidedTotal()
    {
        $builder = (new StoreWithUncachedBooks)->with("books");

        $this->assertFalse(
            (new ReflectionMethod($builder, "isCachable"))->invoke($builder),
            "The builder must be a CachedBuilder that reports itself uncachable"
        );

        $countQueries = [];
        DB::listen(function ($query) use (&$countQueries) {
            if (str_contains(strtolower($query->sql), "count(")) {
                $countQueries[] = $query->sql;
            }
        });

        $stores = $builder->paginate(3, ["*"], "page", 1, 999);

        $this->assertEquals(999, $stores->total());
        $this->assertEmpty(
            $countQueries,
            "Passing a total is how a caller skips the count(*); running one anyway defeats it"
        );
    }

    // CachesOneOrManyThrough::paginate() lost its inert $total parameter and now
    // matches the four-parameter signature HasOneOrManyThrough declares. Author
    // has the only relation in the fixtures that reaches that trait
    // (hasManyThrough), so this is what covers the changed method: it still
    // paginates and still caches.
    public function testHasManyThroughPaginationIsCached()
    {
        $relation = (new Author)
            ->first()
            ->printers();
        $key = sha1(
            (new ReflectionMethod($relation, "makeCacheKey"))
                ->invoke($relation, ["*"], null, "-paginate_by_2_page_1")
        );
        $printers = $relation->paginate(2, ["*"], "page", 1);

        $cached = $this->cache()
            ->tags((new ReflectionMethod($relation, "makeCacheTags"))->invoke($relation))
            ->get($key);

        $this->assertCount(2, $printers);
        $this->assertNotNull($cached, "The relation pagination should have been cached");
        $this->assertEquals(
            $printers->pluck("id"),
            $cached["value"]->pluck("id")
        );
    }
}
