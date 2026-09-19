<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Carbon;
use ReflectionMethod;

class DateTimeTest extends IntegrationTestCase
{
    private function valuesFromWhere(array $where): string
    {
        $query = (new Book)->newQuery();
        $cacheKey = new CacheKey([], new Book, $query->getQuery(), "", [], false);

        return (new ReflectionMethod($cacheKey, "getValuesFromWhere"))
            ->invoke($cacheKey, $where);
    }

    public function testGetValuesFromWhereFormatsEveryDateTimeTypeIdentically()
    {
        $expected = "2019-03-17-13-05-42";
        $whereFor = fn ($value) => [
            "type" => "Basic",
            "column" => "publish_at",
            "operator" => ">",
            "value" => $value,
            "boolean" => "and",
        ];

        $this->assertSame(
            $expected,
            $this->valuesFromWhere($whereFor(new DateTime("2019-03-17 13:05:42")))
        );
        $this->assertSame(
            $expected,
            $this->valuesFromWhere($whereFor(new DateTimeImmutable("2019-03-17 13:05:42")))
        );
        $this->assertSame(
            $expected,
            $this->valuesFromWhere($whereFor((new Carbon)->parse("2019-03-17 13:05:42")))
        );
    }

    private function rawCacheKeyFor(object $value): string
    {
        $query = (new Book)
            ->newQuery()
            ->where("published_at", ">", $value);

        return (new CacheKey([], new Book, $query->getQuery(), "", [], false))
            ->make();
    }

    public function testCarbonBindingIsKeyedByOurFormatNotItsOwnCast()
    {
        $key = $this->rawCacheKeyFor((new Carbon)->parse("2019-03-17 13:05:42"));
        $this->assertStringContainsString("published_at_>_2019%2D03%2D17%2D13%2D05%2D42", $key);
    }

    public function testCarbonKeySegmentIgnoresTheGlobalToStringFormat()
    {
        $dateTime = (new Carbon)->parse("2019-03-17 13:05:42");
        $before = $this->rawCacheKeyFor($dateTime);

        Carbon::setToStringFormat("D, d M Y H:i:s");

        try {
            $this->assertSame($before, $this->rawCacheKeyFor($dateTime));
        } finally {
            Carbon::resetToStringFormat();
        }
    }

    public function testWhereClauseWorksWithCarbonDate()
    {
        $dateTime = now()->subYears(10);
        $encodedDateTime = str_replace("-", "%2D", $dateTime->format("Y-m-d-H-i-s"));
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-publish_at_>_{$encodedDateTime}");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $results = (new Book)
            ->where("publish_at", ">", $dateTime)
            ->get();
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedBook)
            ->where("publish_at", ">", $dateTime)
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
        $this->assertNotEmpty($results);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }

    public function testWhereClauseWorksWithDateTimeImmutableObject()
    {
        $dateTime = (new DateTimeImmutable('@' . time()))
            ->sub(new DateInterval("P10Y"));
        $dateTimeString = str_replace("-", "%2D", $dateTime->format("Y-m-d-H-i-s"));
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-publish_at_>_{$dateTimeString}");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $results = (new Book)
            ->where("publish_at", ">", $dateTime)
            ->get();
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedBook)
            ->where("publish_at", ">", $dateTime)
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
        $this->assertNotEmpty($results);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }

    public function testWhereClauseWorksWithDateTimeObject()
    {
        $dateTime = (new DateTime('@' . time()))
            ->sub(new DateInterval("P10Y"));
        $dateTimeString = str_replace("-", "%2D", $dateTime->format("Y-m-d-H-i-s"));
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-publish_at_>_{$dateTimeString}");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $results = (new Book)
            ->where("publish_at", ">", $dateTime)
            ->get();
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedBook)
            ->where("publish_at", ">", $dateTime)
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertEquals($liveResults->pluck("id"), $cachedResults->pluck("id"));
        $this->assertNotEmpty($results);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }
}
