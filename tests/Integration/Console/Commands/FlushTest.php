<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Console\Commands;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Str;

class FlushTest extends IntegrationTestCase
{
    public function setUp() : void
    {
        parent::setUp();

        if (Str::startsWith($this->app->version(), '5.7')) {
            $this->withoutMockingConsoleOutput();
        }
    }

    public function testGivenModelIsFlushed()
    {
        $authors = (new Author)->all();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this
            ->cache
            ->tags($tags)
            ->get($key)['value'];
        $result = $this
            ->artisan('modelCache:clear', ['--model' => Author::class])
            ->execute();
        $flushedResults = $this
            ->cache
            ->tags($tags)
            ->get($key)['value']
            ?? null;

        $this->assertEquals($authors, $cachedResults);
        $this->assertEmpty($flushedResults);
        $this->assertEquals($result, 0);
    }

    public function testExtendedModelIsFlushed()
    {
        $authors = (new PrefixedAuthor)
            ->get();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors:genealabslaravelmodelcachingtestsfixturesprefixedauthor-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors",
        ];

        $cachedResults = $this
            ->cache
            ->tags($tags)
            ->get($key)['value'];
        $result = $this
            ->artisan('modelCache:clear', ['--model' => PrefixedAuthor::class])
            ->execute();
        $flushedResults = $this
            ->cache
            ->tags($tags)
            ->get($key)['value']
            ?? null;

        $this->assertEquals($authors, $cachedResults);
        $this->assertEmpty($flushedResults);
        $this->assertEquals($result, 0);
    }

    public function testGivenModelWithRelationshipIsFlushed()
    {
        $authors = (new Author)->with('books')->get();
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:books");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $cachedResults = $this->cache
            ->tags($tags)
            ->get($key)['value'];
        $result = $this
            ->artisan(
                'modelCache:clear',
                ['--model' => Author::class]
            )
            ->execute();
        $flushedResults = $this->cache
            ->tags($tags)
            ->get($key)['value']
            ?? null;

        $this->assertEquals($authors, $cachedResults);
        $this->assertEmpty($flushedResults);
        $this->assertEquals($result, 0);
    }

    public function testNonCachedModelsCannotBeFlushed()
    {
        $result = $this->artisan(
                'modelCache:clear',
                ['--model' => UncachedAuthor::class]
            )
            ->execute();

        $this->assertEquals($result, 1);
    }

    public function testAllModelsAreFlushed()
    {
        (new Author)->all();
        (new Book)->all();
        (new Store)->all();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
        $cachedAuthors = $this->cache
            ->tags($tags)
            ->get($key)['value'];
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];
        $cachedBooks = $this->cache
            ->tags($tags)
            ->get($key)['value'];
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
        ];
        $cachedStores = $this->cache
            ->tags($tags)
            ->get($key)['value'];

        $this->assertNotEmpty($cachedAuthors);
        $this->assertNotEmpty($cachedBooks);
        $this->assertNotEmpty($cachedStores);

        $this->artisan('modelCache:clear')
            ->execute();

        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
        $cachedAuthors = $this->cache
            ->tags($tags)
            ->get($key)['value']
            ?? null;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];
        $cachedBooks = $this->cache
            ->tags($tags)
            ->get($key)['value']
            ?? null;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
        ];
        $cachedStores = $this->cache
            ->tags($tags)
            ->get($key)['value']
            ?? null;

        $this->assertEmpty($cachedAuthors);
        $this->assertEmpty($cachedBooks);
        $this->assertEmpty($cachedStores);
    }

    public function testInvalidateAllOnlyClearsModelCachePrefix()
    {
        $modelCacheStore = $this->app['cache']->store('model')->getStore();
        $prefix = $modelCacheStore->getPrefix();
        $connection = $modelCacheStore->connection();

        $foreignKey = 'foreign-tenant:keep-me:' . uniqid();
        $connection->set($foreignKey, 'should-survive');

        (new Author)->all();
        (new Book)->all();

        $this->artisan('modelCache:clear')->execute();

        $this->assertSame(
            'should-survive',
            $connection->get($foreignKey),
            'invalidateAll() must not delete keys outside the model cache prefix',
        );

        $remainingPrefixed = $connection->scan(null, ['match' => $prefix . '*', 'count' => 100]);
        $remainingKeys = is_array($remainingPrefixed) ? ($remainingPrefixed[1] ?? []) : [];
        $this->assertEmpty(
            $remainingKeys,
            'invalidateAll() must remove all keys with the model cache prefix',
        );

        $connection->del($foreignKey);
    }
}
