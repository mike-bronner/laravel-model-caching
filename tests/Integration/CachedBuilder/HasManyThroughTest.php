<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class HasManyThroughTest extends IntegrationTestCase
{
    public function testEagerloadedHasManyThrough()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-testing:{$this->testingSqlitePath}testing.sqlite:printers-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprinter",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        $printers = (new Author)
            ->with("printers")
            ->first()
            ->printers;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value']
            ->first()
            ->printers;
        $liveResults = (new UncachedAuthor)
            ->with("printers")
            ->first()
            ->printers;

        $this->assertEquals($liveResults->pluck("id")->toArray(), $printers->pluck("id")->toArray());
        $this->assertEquals($liveResults->pluck("id")->toArray(), $cachedResults->pluck("id")->toArray());
        $this->assertNotEmpty($printers);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }

    public function testLazyloadedHasManyThrough()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:printers:genealabslaravelmodelcachingtestsfixturesprinter-join_inner_books_bca1a55e5c9c-books.author_id_=_1");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesprinter",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:printers",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        ];

        $printers = (new Author)
            ->find(1)
            ->printers;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedAuthor)
            ->find(1)
            ->printers;

        $this->assertEquals($liveResults->pluck("id")->toArray(), $printers->pluck("id")->toArray());
        $this->assertEquals($liveResults->pluck("id")->toArray(), $cachedResults->pluck("id")->toArray());
        $this->assertNotEmpty($printers);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }
}