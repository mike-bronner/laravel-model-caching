<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

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

    public function testUncachedPaginationHonorsProvidedTotal()
    {
        $authors = (new Author)
            ->disableModelCaching()
            ->paginate(3, ["*"], "page", 1, 999);

        $this->assertEquals(999, $authors->total());
    }
}
