<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Throwable;

class WhereJsonContainsTest extends IntegrationTestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql.host', env("PGSQL_HOST", "pgsql"));
        $app['config']->set('database.connections.pgsql.database', env("PGSQL_DATABASE", "testing"));
        $app['config']->set('database.connections.pgsql.username', env("PGSQL_USERNAME", "forge"));
        $app['config']->set('database.connections.pgsql.password', env("PGSQL_PASSWORD", "secret"));
    }

    public function setUp() : void
    {
        // This class runs against PostgreSQL, which SQLite cannot stand in for:
        // its grammar refuses to compile a JSON "contains" predicate at all.
        // The guard runs before parent::setUp() because RefreshDatabase
        // migrates the default connection there, which is the first thing to
        // open it — a guard placed after that never gets to speak.
        $this->skipWithoutPostgres();

        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        Author::factory()->count(10)->create();
    }

    // Probed with a bare PDO rather than through the container, because nothing
    // is booted yet at this point in setUp(). A missing pdo_pgsql driver throws
    // here too, which is the same answer: this suite cannot run.
    private function skipWithoutPostgres() : void
    {
        $host = env("PGSQL_HOST", "pgsql");
        $database = env("PGSQL_DATABASE", "testing");

        try {
            new PDO(
                "pgsql:host={$host};dbname={$database}",
                env("PGSQL_USERNAME", "forge"),
                env("PGSQL_PASSWORD", "secret"),
                [PDO::ATTR_TIMEOUT => 3],
            );
        } catch (Throwable $exception) {
            $this->markTestSkipped(
                "No reachable PostgreSQL server: {$exception->getMessage()}"
            );
        }
    }

    private function keyPrefix() : string
    {
        return "genealabs:laravel-model-caching:pgsql:testing"
            . ":authors:genealabslaravelmodelcachingtestsfixturesauthor";
    }

    private function authorTags() : array
    {
        return [
            'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
            'genealabs:laravel-model-caching:pgsql:testing:authors',
        ];
    }

    private function cachedValueFor(string $key)
    {
        return $this->cache()
            ->tags($this->authorTags())
            ->get($key);
    }

    // The key-level twin of this lives in WhereNotTest and runs on SQLite,
    // because building a key touches no database. This is the half that needs a
    // server: whereJsonContains() and whereJsonDoesntContain() shared one cache
    // key, so the negated query was served the rows it exists to exclude.
    public function testWhereJsonDoesntContainReturnsItsOwnResultsAfterWhereJsonContainsWasCached()
    {
        $containsKey = sha1($this->keyPrefix() . "-finances->total_jsoncontains_5000-authors.deleted_at_null");
        $doesntContainKey = sha1($this->keyPrefix() . "-finances->total_not_jsoncontains_5000-authors.deleted_at_null");

        $containsResults = (new Author)
            ->whereJsonContains("finances->total", 5000)
            ->get();

        $this->assertCount(10, $containsResults);
        $this->assertNotNull(
            $this->cachedValueFor($containsKey),
            "The first query must populate the cache, or the second query has nothing to collide with"
        );

        $results = (new Author)
            ->whereJsonDoesntContain("finances->total", 5000)
            ->get();
        $liveResults = (new UncachedAuthor)
            ->whereJsonDoesntContain("finances->total", 5000)
            ->get();
        $cachedResults = $this->cachedValueFor($doesntContainKey)['value'];

        $this->assertEmpty($liveResults);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }

    public function testWithInUsingCollectionQuery()
    {
        $key = sha1("genealabs:laravel-model-caching:pgsql:testing:authors:genealabslaravelmodelcachingtestsfixturesauthor-finances->total_jsoncontains_5000-authors.deleted_at_null");
        $tags = [
            'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
            'genealabs:laravel-model-caching:pgsql:testing:authors',
        ];

        $authors = (new Author)
            ->whereJsonContains("finances->total", 5000)
            ->get();

        $liveResults = (new UncachedAuthor)
            ->whereJsonContains("finances->total", 5000)
            ->get();

        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertCount(10, $cachedResults);
        $this->assertCount(10, $liveResults);
        $this->assertEquals($liveResults->pluck("id"), $authors->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }

    public function testWithInUsingCollectionQueryWithArrayValues()
    {
        $key = sha1("genealabs:laravel-model-caching:pgsql:testing:authors:genealabslaravelmodelcachingtestsfixturesauthor-finances->tags_jsoncontains_[\"foo\",\"bar\"]-authors.deleted_at_null");
        $tags = [
            'genealabs:laravel-model-caching:pgsql:testing:genealabslaravelmodelcachingtestsfixturesauthor',
            'genealabs:laravel-model-caching:pgsql:testing:authors',
        ];

        $authors = (new Author)
            ->whereJsonContains("finances->tags", ['foo', 'bar'])
            ->get();
        $liveResults = (new UncachedAuthor)
            ->whereJsonContains("finances->tags", ['foo', 'bar'])
            ->get();

        $cachedResults = $this
            ->cache()
            ->tags($tags)
            ->get($key)['value'];

        $this->assertCount(10, $liveResults);
        $this->assertCount(10, $cachedResults);
        $this->assertEquals($liveResults->pluck("id"), $authors->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
    }
}
