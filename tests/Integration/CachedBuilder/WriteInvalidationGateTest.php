<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class WriteInvalidationGateTest extends IntegrationTestCase
{
    private function cachedTitles()
    {
        return (new Book)->orderBy("id")->pluck("title");
    }

    private function liveTitles()
    {
        return (new UncachedBook)->orderBy("id")->pluck("title");
    }

    public function testWriteDoesNotFlushWhileCachingIsDisabled()
    {
        $before = $this->cachedTitles();
        $query = (new Book)->newQuery()->where("id", 1);

        app("model-cache")->runDisabled(function () use ($query) {
            $query->update(["title" => "written while disabled"]);
        });

        $this->assertNotEquals($before, $this->liveTitles());
        $this->assertEquals($before, $this->cachedTitles());
    }

    public function testWriteThroughDisabledCacheQueryStillFlushes()
    {
        $this->cachedTitles();

        (new Book)->disableCache()->where("id", 1)->update(["title" => "written uncached"]);

        $this->assertEquals($this->liveTitles(), $this->cachedTitles());
    }

    public function testWriteThroughLockedQueryStillFlushes()
    {
        $this->cachedTitles();

        (new Book)->lockForUpdate()->where("id", 1)->update(["title" => "written under lock"]);

        $this->assertEquals($this->liveTitles(), $this->cachedTitles());
    }
}
