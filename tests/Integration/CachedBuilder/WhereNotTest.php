<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

class WhereNotTest extends IntegrationTestCase
{
    public function testWhereNotProducesDifferentCacheKeyThanWhere()
    {
        $query1 = (new Author)
            ->where('id', 1);

        $query2 = (new Author)
            ->whereNot('id', 1);

        $cacheKey1 = (new ReflectionMethod($query1, 'makeCacheKey'))
            ->invoke($query1);
        $cacheKey2 = (new ReflectionMethod($query2, 'makeCacheKey'))
            ->invoke($query2);

        $this->assertNotEquals($cacheKey1, $cacheKey2);
    }

    public function testWhereNotReturnsItsOwnResultsAfterWhereWasCached()
    {
        (new Author)
            ->where('id', 1)
            ->get();

        $results = (new Author)
            ->whereNot('id', 1)
            ->get();
        $liveResults = (new UncachedAuthor)
            ->whereNot('id', 1)
            ->get();

        $this->assertEquals($liveResults->pluck('id'), $results->pluck('id'));
        $this->assertNotContains(1, $results->pluck('id')->toArray());
    }

    public function testOrWhereProducesDifferentCacheKeyThanWhere()
    {
        $query1 = (new Author)
            ->where('id', 1)
            ->where('id', 2);

        $query2 = (new Author)
            ->where('id', 1)
            ->orWhere('id', 2);

        $cacheKey1 = (new ReflectionMethod($query1, 'makeCacheKey'))
            ->invoke($query1);
        $cacheKey2 = (new ReflectionMethod($query2, 'makeCacheKey'))
            ->invoke($query2);

        $this->assertNotEquals($cacheKey1, $cacheKey2);
    }
}
