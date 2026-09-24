<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class HelperTest extends IntegrationTestCase
{
    public function testClosureRunsWithCacheDisabled()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $authors = app("model-cache")->runDisabled(function () {
            return (new Author)
                ->get();
        });

        $cachedResults1 = $this->cache()
            ->tags($tags)
            ->get($key)["value"]
            ?? null;
        (new Author)
            ->get();
        $cachedResults2 = $this->cache()
            ->tags($tags)
            ->get($key)["value"]
            ?? null;
        $liveResults = (new UncachedAuthor)
            ->get();

        $this->assertEquals($liveResults->toArray(), $authors->toArray());
        $this->assertNull($cachedResults1);
        $this->assertEquals($authors->toArray(), $cachedResults2->toArray());
    }

    public function testCachingIsReEnabledWhenTheClosureThrows()
    {
        $thrown = null;

        try {
            app("model-cache")->runDisabled(function () {
                throw new \RuntimeException("closure failed");
            });
        } catch (\RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertSame("closure failed", $thrown?->getMessage());
        $this->assertTrue(config("laravel-model-caching.enabled"));
        $this->assertTrue((new Author)->isCachable());
    }

    public function testWritesInsideTheClosureNeedInvalidatingAfterwards()
    {
        $before = (new Author)->orderBy("id")->pluck("name");

        app("model-cache")->runDisabled(function () {
            $author = (new Author)->findOrFail(1);
            $author->name = "renamed while disabled";
            $author->save();
        });

        $staleNames = (new Author)->orderBy("id")->pluck("name");
        app("model-cache")->invalidate(Author::class);
        $freshNames = (new Author)->orderBy("id")->pluck("name");
        $liveNames = (new UncachedAuthor)->orderBy("id")->pluck("name");

        $this->assertEquals($before, $staleNames);
        $this->assertNotEquals($before, $liveNames);
        $this->assertEquals($liveNames, $freshNames);
    }
}
