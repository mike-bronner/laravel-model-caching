<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use DateInterval;
use DateTime;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class DateTimeTest extends IntegrationTestCase
{
    public function testWhereClauseWorksWithCarbonDate()
    {
        $dateTime = now()->subYears(10);
        // The "-" separating the date parts is a key separator, so the value
        // segment carries it percent-encoded.
        $encodedDateTime = str_replace("-", "%2D", (string) $dateTime);
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
