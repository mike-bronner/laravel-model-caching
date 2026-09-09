<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Traits;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CacheTagsWithOverriddenConnectionName;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

// getConnectionName() and getDatabaseName() are protected on a published trait,
// so overriding them is a supported way to change the connection and database
// segments of every prefix a tag builder writes. These pin that the override is
// still consulted, and that it reaches only the model it belongs to.
class CachePrefixOverrideTest extends IntegrationTestCase
{
    private function tagsFor($model, $query) : array
    {
        return (new CacheTagsWithOverriddenConnectionName([], $model, $query))
            ->make();
    }

    private function prefix(string $connection) : string
    {
        return "genealabs:laravel-model-caching:{$connection}:"
            . "{$this->testingSqlitePath}testing.sqlite:";
    }

    // The querying model's own tags take the override. Reading the connection
    // off the model instead would spell them "testing:", and the override would
    // be dead code that still looks live.
    public function testAnOverriddenConnectionNameReachesTheQueryingModelsTags()
    {
        $model = new Author;

        $this->assertEquals(
            [
                $this->prefix("overridden-connection")
                    . "genealabslaravelmodelcachingtestsfixturesauthor",
                $this->prefix("overridden-connection") . "authors",
            ],
            $this->tagsFor($model, $model->newQuery()),
        );
    }

    // A related model is a different object, so it keeps its own prefix rather
    // than inheriting the override. Its write flushes "testing:books", and a
    // tag reading "overridden-connection:books" is one nothing ever flushes.
    public function testAnOverriddenConnectionNameDoesNotReachARelatedModelsTag()
    {
        $model = new Author;

        $this->assertEquals(
            [
                $this->prefix("overridden-connection")
                    . "genealabslaravelmodelcachingtestsfixturesauthor",
                $this->prefix("overridden-connection") . "authors",
                $this->prefix("testing") . "books",
            ],
            $this->tagsFor($model, $model->has("books", ">", 1)),
        );
    }
}
