<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Throwable;

class UpdateFromInvalidationTest extends IntegrationTestCase
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
        $this->skipWithoutPostgres();

        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        Author::factory()->count(2)->create();
        Publisher::factory()->create();
        Book::factory()->count(3)->create();
    }

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

    public function testUpdateFromInvalidatesCache()
    {
        $before = (new Book)->orderBy("id")->pluck("title");

        Book::query()
            ->join("authors", "authors.id", "=", "books.author_id")
            ->where("authors.id", 1)
            ->updateFrom(["title" => "updated from"]);

        $after = (new Book)->orderBy("id")->pluck("title");
        $live = (new UncachedBook)->orderBy("id")->pluck("title");

        $this->assertNotEquals($before, $live, "The write must change what the database returns");
        $this->assertEquals($live, $after);
    }
}
