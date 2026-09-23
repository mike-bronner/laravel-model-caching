<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class PolymorphicManyToManyTest extends IntegrationTestCase
{
    public function testEagerloadedRelationship()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-testing:{$this->testingSqlitePath}testing.sqlite:tags-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturestag",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:taggables",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
        ];

        $result = (new Post)
            ->with("tags")
            ->first()
            ->tags;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedPost)
            ->with("tags")
            ->first()
            ->tags;

        $this->assertEquals($liveResults->pluck("id")->toArray(), $result->pluck("id")->toArray());
        $this->assertEquals($liveResults->pluck("id")->toArray(), $cachedResults->pluck("id")->toArray());
        $this->assertNotEmpty($result);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }

    public function testLazyloadedRelationship()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-testing:{$this->testingSqlitePath}testing.sqlite:tags-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturestag",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:taggables",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
        ];

        $result = (new Post)
            ->with("tags")
            ->first()
            ->tags;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedPost)
            ->with("tags")
            ->first()
            ->tags;

        $this->assertEquals($liveResults->pluck("id")->toArray(), $result->pluck("id")->toArray());
        $this->assertEquals($liveResults->pluck("id")->toArray(), $cachedResults->pluck("id")->toArray());
        $this->assertNotEmpty($result);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }
}
