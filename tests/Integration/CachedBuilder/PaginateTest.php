<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StoreWithUncachedBooks;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Support\Facades\DB;

test('pagination is cached', function () {
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

    expect($authors)->toEqual($cachedResults);
    expect($authors->pluck("email"))->toEqual($liveResults->pluck("email"));
    expect($authors->pluck("name"))->toEqual($liveResults->pluck("name"));
});

test('pagination returns correct links', function () {
    $booksPage1 = (new Book)
        ->paginate(2);
    $booksPage2 = (new Book)
        ->paginate(2, ['*'], null, 2);
    $booksPage24 = (new Book)
        ->paginate(2, ['*'], null, 24);

    expect($booksPage1)->toHaveCount(2);
    expect($booksPage2)->toHaveCount(2);
    expect($booksPage24)->toHaveCount(2);
    assertActivePageInLinks(1, (string) $booksPage1->links());
    assertActivePageInLinks(2, (string) $booksPage2->links());
    assertActivePageInLinks(24, (string) $booksPage24->links());
});

test('pagination with options returns correct links', function () {
    $booksPage1 = (new Book)
        ->paginate(2);
    $booksPage2 = (new Book)
        ->paginate(2, ['*'], null, 2);
    $booksPage24 = (new Book)
        ->paginate(2, ['*'], null, 24);

    expect($booksPage1)->toHaveCount(2);
    expect($booksPage2)->toHaveCount(2);
    expect($booksPage24)->toHaveCount(2);
    assertActivePageInLinks(1, (string) $booksPage1->links());
    assertActivePageInLinks(2, (string) $booksPage2->links());
    assertActivePageInLinks(24, (string) $booksPage24->links());
});

test('pagination with custom options returns correct links', function () {
    $booksPage1 = (new Book)
        ->paginate('2');
    $booksPage2 = (new Book)
        ->paginate('2', ['*'], 'pages', 2);
    $booksPage24 = (new Book)
        ->paginate('2', ['*'], 'pages', 24);

    expect($booksPage1)->toHaveCount(2);
    expect($booksPage2)->toHaveCount(2);
    expect($booksPage24)->toHaveCount(2);
    assertActivePageInLinks(1, (string) $booksPage1->links());
    assertActivePageInLinks(2, (string) $booksPage2->links());
    assertActivePageInLinks(24, (string) $booksPage24->links());
});

test('custom page name pagination', function () {
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

    expect($authors)->toEqual($cachedResults);
    expect($authors->pluck("email"))->toEqual($liveResults->pluck("email"));
    expect($authors->pluck("name"))->toEqual($liveResults->pluck("name"));
});

test('custom page name pagination fetches correct pages', function () {
    $authors1 = (new Author)
        ->paginate(3, ["*"], "custom-page", 1);
    $authors2 = (new Author)
        ->paginate(3, ["*"], "custom-page", 2);

    expect($authors2->pluck("id"))->not->toEqual($authors1->pluck("id"));
});

test('paginator base url reflects current request', function () {
    \Illuminate\Pagination\Paginator::currentPathResolver(function () {
        return "https://domain-a.com/authors";
    });

    $authorsFromDomainA = (new Author)->paginate(3);
    expect($authorsFromDomainA->url(1))->toContain("domain-a.com");

    \Illuminate\Pagination\Paginator::currentPathResolver(function () {
        return "https://domain-b.com/authors";
    });

    $authorsFromDomainB = (new Author)->paginate(3);
    expect($authorsFromDomainB->url(1))->toContain("domain-b.com");
});

test('cached paginator path is reapplied from current request', function () {
    \Illuminate\Pagination\Paginator::currentPathResolver(function () {
        return "https://original.com/users";
    });

    (new Author)->paginate(3);

    \Illuminate\Pagination\Paginator::currentPathResolver(function () {
        return "https://different.com/users";
    });

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-paginate_by_3_page_1");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedRaw = $this->cache()->tags($tags)->get($key)['value'];

    $result = (new Author)->paginate(3);

    expect($result->url(1))->toContain("different.com");
    expect($result->url(1))->not->toContain("original.com");
});

test('uncached pagination honors provided total', function () {
    $builder = (new StoreWithUncachedBooks)->with("books");

    expect((new ReflectionMethod($builder, "isCachable"))->invoke($builder))->toBeFalse(
        "The builder must be a CachedBuilder that reports itself uncachable",
    );

    $countQueries = [];
    DB::listen(function ($query) use (&$countQueries) {
        if (str_contains(strtolower($query->sql), "count(")) {
            $countQueries[] = $query->sql;
        }
    });

    $stores = $builder->paginate(3, ["*"], "page", 1, 999);

    expect($stores->total())->toEqual(999);
    expect($countQueries)->toBeEmpty(
        "Passing a total is how a caller skips the count(*); running one anyway defeats it",
    );
});

test('has many through pagination is cached', function () {
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

    expect($printers)->toHaveCount(2);
    expect($cached)->not->toBeNull("The relation pagination should have been cached");
    expect($cached["value"]->pluck("id"))->toEqual($printers->pluck("id"));
});

test('array page is written into the key as key value pairs', function () {
    $builder = (new Author)->newQuery();
    $segment = (new ReflectionMethod($builder, "recursiveImplodeWithKey"))
        ->invoke($builder, ["size" => 1, "number" => 2]);

    expect($segment)->toBe("_size_1_number_2");
});

function assertActivePageInLinks(int $page, string $linksHtml): void
{
    expect($linksHtml)->toMatch(
        '/aria-current="page">\s*<span[^>]*>' . $page . '<\/span>/s',
        "Expected page {$page} to be marked as the active page in pagination links.",
    );
}

test('pagination with a given total is cached per total', function () {
    $firstPage = (new Author)->paginate(3, ['*'], 'page', 1, 50);
    $sameTotal = (new Author)->paginate(3, ['*'], 'page', 1, fn () => 50);
    $otherTotal = (new Author)->paginate(3, ['*'], 'page', 1, 60);

    expect($firstPage->total())->toBe(50)
        ->and($sameTotal->total())->toBe(50)
        ->and($otherTotal->total())->toBe(60)
        ->and($otherTotal->pluck('id'))->toEqual($firstPage->pluck('id'));
});
