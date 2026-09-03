<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Http\Resources\Author as AuthorResource;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Str;

/**
* @SuppressWarnings(PHPMD.TooManyPublicMethods)
* @SuppressWarnings(PHPMD.TooManyMethods)
 */
class CachedBuilderTest extends IntegrationTestCase
{
    public function testCacheIsEmptyBeforeLoadingModels()
    {
        $results = $this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books");

        $this->assertNull($results);
    }

    public function testCacheIsNotEmptyAfterLoadingModels()
    {
        (new Author)->with('books')->get();

        $results = $this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books"));

        $this->assertNotNull($results);
    }

    public function testCreatingModelClearsCache()
    {
        (new Author)->with('books')->get();

        Author::factory()->create();

        $results = $this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get(sha1(
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1_2_3_4_5_6_" .
                "7_8_9_10-genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbooks"
            ));

        $this->assertNull($results);
    }

    public function testUpdatingModelClearsCache()
    {
        $author = (new Author)->with('books')->get()->first();
        $author->name = "John Jinglheimer";
        $author->save();

        $results = $this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get(sha1(
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1_2_3_4_5_6_" .
                "7_8_9_10-genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbooks"
            ));

        $this->assertNull($results);
    }

    public function testDeletingModelClearsCache()
    {
        $author = (new Author)->with('books')->get()->first();
        $author->delete();

        $results = $this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get(sha1(
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_1_2_3_4_5_6_" .
                "7_8_9_10-genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbooks"
            ));

        $this->assertNull($results);
    }

    public function testHasManyRelationshipIsCached()
    {
        $authors = (new Author)->with('books')->get();

        $results = collect($this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books"))['value']);

        $this->assertNotNull($results);
        $this->assertEmpty($authors->diffKeys($results));
        $this->assertNotEmpty($authors);
        $this->assertNotEmpty($results);
        $this->assertEquals($authors->count(), $results->count());
    }

    public function testBelongsToRelationshipIsCached()
    {
        $books = (new Book)->with('author')->get();

        $results = collect($this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            ])
            ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-testing:{$this->testingSqlitePath}testing.sqlite:author"))['value']);

        $this->assertNotNull($results);
        $this->assertEmpty($books->diffKeys($results));
        $this->assertNotEmpty($books);
        $this->assertNotEmpty($results);
        $this->assertEquals($books->count(), $results->count());
    }

    public function testBelongsToManyRelationshipIsCached()
    {
        $books = (new Book)->with('stores')->get();

        $results = collect($this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            ])
            ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-testing:{$this->testingSqlitePath}testing.sqlite:stores"))['value']);

        $this->assertNotNull($results);
        $this->assertEmpty($books->diffKeys($results));
        $this->assertNotEmpty($books);
        $this->assertNotEmpty($results);
        $this->assertEquals($books->count(), $results->count());
    }

    public function testHasOneRelationshipIsCached()
    {
        $authors = (new Author)->with('profile')->get();

        $results = collect($this->cache()
            ->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
            ])
            ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:profile"))['value']);

        $this->assertNotNull($results);
        $this->assertEmpty($authors->diffKeys($results));
        $this->assertNotEmpty($authors);
        $this->assertNotEmpty($results);
        $this->assertEquals($authors->count(), $results->count());
    }

    public function testAvgModelResultsCreatesCache()
    {
        $authorId = (new Author)->with('books', 'profile')
            ->avg('id');
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-avg_id");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResult = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResult = (new UncachedAuthor)->with('books', 'profile')
            ->avg('id');

        $this->assertEquals($authorId, $cachedResult);
        $this->assertEquals($liveResult, $cachedResult);
    }

    public function testChunkModelResultsCreatesCache()
    {
        $chunkedAuthors = [];
        $chunkedKeys = [];
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
        (new Author)
            ->chunk(3, function ($authors) use (&$chunkedAuthors, &$chunkedKeys) {
                $offset = "";

                if (count($chunkedKeys)) {
                    $offset = "-offset_" . (count($chunkedKeys) * 3);
                }

                $key = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null_orderBy_authors.id_asc{$offset}-limit_3";
                array_push($chunkedAuthors, $authors);
                array_push($chunkedKeys, $key);
            });


        for ($index = 0; $index < count($chunkedAuthors); $index++) {
            $cachedAuthors = $this
                ->cache()
                ->tags($tags)
                ->get(sha1($chunkedKeys[$index]))['value'];

            $this->assertEquals(count($chunkedAuthors[$index]), count($cachedAuthors));
            $this->assertEquals($chunkedAuthors[$index], $cachedAuthors);
        }

        $this->assertCount(4, $chunkedAuthors);
    }

    public function testCountModelResultsCreatesCache()
    {
        $authors = (new Author)
            ->with('books', 'profile')
            ->count();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-count");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->with('books', 'profile')
            ->count();

        $this->assertEquals($authors, $cachedResults);
        $this->assertEquals($liveResults, $cachedResults);
    }

    public function testCountWithStringCreatesCache()
    {
        $authors = (new Author)
            ->with('books', 'profile')
            ->count("id");
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_id-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-count");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->with('books', 'profile')
            ->count("id");

        $this->assertEquals($authors, $cachedResults);
        $this->assertEquals($liveResults, $cachedResults);
    }

    public function testFirstModelResultsCreatesCache()
    {
        $author = (new Author)
            ->first();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResult = $this->cache()->tags($tags)
            ->get($key)['value'];

        $liveResult = (new UncachedAuthor)
            ->with('books', 'profile')
            ->first();

        $this->assertEquals($cachedResult->id, $author->id);
        $this->assertEquals($liveResult->id, $author->id);
    }

    public function testMaxModelResultsCreatesCache()
    {
        $authorId = (new Author)->with('books', 'profile')
            ->max('id');
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-max_id");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResult = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResult = (new UncachedAuthor)->with('books', 'profile')
            ->max('id');

        $this->assertEquals($authorId, $cachedResult);
        $this->assertEquals($liveResult, $cachedResult);
    }

    public function testMinModelResultsCreatesCache()
    {
        $authorId = (new Author)->with('books', 'profile')
            ->min('id');
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-min_id");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResult = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResult = (new UncachedAuthor)->with('books', 'profile')
            ->min('id');

        $this->assertEquals($authorId, $cachedResult);
        $this->assertEquals($liveResult, $cachedResult);
    }

    public function testPluckModelResultsCreatesCache()
    {
        $authors = (new Author)->with('books', 'profile')
            ->pluck('name', 'id');
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor_name-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-pluck_name_id");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)->with('books', 'profile')
            ->pluck('name', 'id');

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testSumModelResultsCreatesCache()
    {
        $authorId = (new Author)->with('books', 'profile')
            ->sum('id');
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-sum_id");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResult = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResult = (new UncachedAuthor)->with('books', 'profile')
            ->sum('id');

        $this->assertEquals($authorId, $cachedResult);
        $this->assertEquals($liveResult, $cachedResult);
    }

    public function testValueModelResultsCreatesCache()
    {
        $authorName = (new Author)->with('books', 'profile')
            ->value('name');
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-testing:{$this->testingSqlitePath}testing.sqlite:profile-value_name");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprofile",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResult = $this->cache()->tags($tags)
            ->get($key)['value'];
        $liveResult = (new UncachedAuthor)->with('books', 'profile')
            ->value('name');

        $this->assertEquals($authorName, $cachedResult);
        $this->assertEquals($authorName, $liveResult);
    }

    public function testNestedRelationshipEagerLoading()
    {
        $authors = collect([(new Author)->with('books.publisher')
                ->first()]);

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-books.publisher-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespublisher",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = collect([$this->cache()->tags($tags)
                ->get($key)['value']]);
        $liveResults = collect([(new UncachedAuthor)->with('books.publisher')
                ->first()]);

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testLazyLoadedRelationshipResolvesThroughCachedBuilder()
    {
        $books = (new Author)->first()->books;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)->first()->books;

        $this->assertEmpty($books->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testLazyLoadingOnResourceIsCached()
    {
        if (Str::startsWith(app()->version(), "5.4")) {
            $this->markTestIncomplete("Resources don't exist in Laravel 5.4.");
        }

        $books = (new AuthorResource((new Author)->first()))->books;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)->first()->books;

        $this->assertEmpty($books->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testOrderByClauseParsing()
    {
        $authors = (new Author)->orderBy('name')->get();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null_orderBy_name_asc");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)->orderBy('name')->get();

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testNestedRelationshipWhereClauseParsing()
    {
        $authors = (new Author)
            ->with('books.publisher')
            ->get();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books-books.publisher");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespublisher",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];

        $liveResults = (new UncachedAuthor)->with('books.publisher')
            ->get();

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testExistsRelationshipWhereClauseParsing()
    {
        $authors = (new Author)->whereHas('books')
            ->get();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-exists-authors.id_=_books.author_id-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)->whereHas('books')
            ->get();

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testDoesntHaveWhereClauseParsing()
    {
        $authors = (new Author)
            ->doesntHave('books')
            ->get();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-notexists-authors.id_=_books.author_id-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->doesntHave('books')
            ->get();

        $this->assertEmpty($authors->diffKeys($cachedResults));
        $this->assertEmpty($liveResults->diffKeys($cachedResults));
    }

    public function testRelationshipQueriesAreCached()
    {
        $books = (new Author)
            ->first()
            ->books()
            ->get();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-books.author_id_=_1-books.author_id_notnull");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->first()
            ->books()
            ->get();

        $this->assertTrue($cachedResults->diffKeys($books)->isEmpty());
        $this->assertTrue($liveResults->diffKeys($books)->isEmpty());
    }

    public function testRawOrderByWithoutColumnReference()
    {
        $authors = (new Author)
            ->orderByRaw('DATE()')
            ->get();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null_orderByRaw_date");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];

        $liveResults = (new UncachedAuthor)
            ->orderByRaw('DATE()')
            ->get();

        $this->assertTrue($cachedResults->diffKeys($authors)->isEmpty());
        $this->assertTrue($liveResults->diffKeys($authors)->isEmpty());
    }

    public function testDelete()
    {
        $author = (new Author)
            ->first();
        $liveResult = (new UncachedAuthor)
            ->first();
        $authorId = $author->id;
        $liveResultId = $liveResult->id;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $author->delete();
        $liveResult->delete();
        $cachedResult = $this->cache()
            ->tags($tags)
            ->get($key)['value']
            ?? null;
        $deletedAuthor = (new Author)->find($authorId);

        $this->assertEquals($liveResultId, $authorId);
        $this->assertNull($cachedResult);
        $this->assertNull($deletedAuthor);
    }

    public function testWhereBetweenIdsResults()
    {
        $books = (new Book)
            ->whereBetween('price', [5, 10])
            ->get();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-price_between_5_10");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereBetween('price', [5, 10])
            ->get();

        $this->assertTrue($cachedResults->diffKeys($books)->isEmpty());
        $this->assertTrue($liveResults->diffKeys($books)->isEmpty());
    }

    public function testWhereBetweenDatesResults()
    {
        $books = (new Book)
            ->whereBetween('created_at', ['2018-01-01', '2018-12-31'])
            ->get();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-created_at_between_2018%2D01%2D01_2018%2D12%2D31");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereBetween('price', [5, 10])
            ->get();

        $this->assertTrue($cachedResults->diffKeys($books)->isEmpty());
        $this->assertTrue($liveResults->diffKeys($books)->isEmpty());
    }

    public function testWhereDatesResults()
    {
        $books = (new Book)
            ->whereDate('created_at', '>=', '2018-01-01')
            ->get();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-created_at_date_>=_2018%2D01%2D01");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->whereBetween('price', [5, 10])
            ->get();

        $this->assertTrue($cachedResults->diffKeys($books)->isEmpty());
        $this->assertTrue($liveResults->diffKeys($books)->isEmpty());
    }

    public function testHashCollision()
    {
        $key1 = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-id_notin_1_2");
        $tags1 = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];
        $books = (new Book)
            ->whereNotIn('id', [1, 2])
            ->get();
        $this->cache()->tags($tags1)->flush();

        $authors = (new Author)
            ->disableCache()
            ->get();
        $key2 = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor";
        $this->cache()
            ->tags($tags1)
            ->rememberForever(
                $key1,
                function () use ($key2, $authors) {
                    return [
                        'key' => $key2,
                        'value' => $authors,
                    ];
                }
            );
        $cachedBooks = (new Book)
            ->whereNotIn('id', [1, 2])
            ->get();
        $cachedResults = $this->cache()
            ->tags($tags1)
            ->get($key1)['value'];

        $this->assertTrue($cachedResults->keyBy('id')->diffKeys($books->keyBy('id'))->isEmpty());
        $this->assertTrue($cachedResults->diff($authors)->isNotEmpty());
    }

    public function testSubsequentDisabledCacheQueriesDoNotCache()
    {
        (new Author)->disableCache()->get();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
        $cachedAuthors1 = $this->cache()
            ->tags($tags)
            ->get($key)['value']
            ?? null;

        (new Author)->disableCache()->get();
        $cachedAuthors2 = $this->cache()
            ->tags($tags)
            ->get($key)['value']
            ?? null;

        $this->assertEmpty($cachedAuthors1);
        $this->assertEmpty($cachedAuthors2);
    }

    public function testInsertInvalidatesCache()
    {
        $authors = (new Author)
            ->get();

        (new Author)
            ->insert([
                'name' => 'Test Insert',
                'email' => 'none@noemail.com',
            ]);
        $authorsAfterInsert = (new Author)
            ->get();
        $uncachedAuthors = (new UncachedAuthor)
            ->get();

        $this->assertCount(10, $authors);
        $this->assertCount(11, $authorsAfterInsert);
        $this->assertCount(11, $uncachedAuthors);
    }

    public function testUpdateInvalidatesCache()
    {
        $originalAuthor = (new Author)
            ->first();
        $author = (new Author)
            ->first();

        $author->update([
            "name" => "Updated Name",
        ]);
        $authorAfterUpdate = (new Author)
            ->find($author->id);
        $uncachedAuthor = (new UncachedAuthor)
            ->find($author->id);

        $this->assertNotEquals($originalAuthor->name, $authorAfterUpdate->name);
        $this->assertEquals("Updated Name", $authorAfterUpdate->name);
        $this->assertEquals($authorAfterUpdate->name, $uncachedAuthor->name);
    }

    public function testAttachInvalidatesCache()
    {
        $book = (new Book)
            ->find(1);

        $book->stores()->attach(1);
        $cachedBook = (new Book)
            ->find(1);

        $this->assertTrue($book->stores->keyBy('id')->has(1));
    }
}
