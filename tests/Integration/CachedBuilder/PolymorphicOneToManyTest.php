<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class PolymorphicOneToManyTest extends IntegrationTestCase
{
    public function testEagerloadedRelationship()
    {
        $postId = (new Post)
            ->disableModelCaching()
            ->whereHas("comments")
            ->first()
            ->id;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-comments.commentable_id_inraw_{$postId}-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
        ];

        $result = (new Post)
            ->with("comments")
            ->whereHas("comments")
            ->first()
            ->comments;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value']
            ->first();
        $liveResults = (new UncachedPost)
            ->with("comments")
            ->whereHas("comments")
            ->first()
            ->comments;

        $this->assertEquals($liveResults->first()->description, $result->first()->description);
        $this->assertEquals($liveResults->first()->description, $cachedResults->first()->description);
        $this->assertNotEmpty($result);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }

    public function testLazyloadedRelationship()
    {
        $postId = (new Post)
            ->disableModelCaching()
            ->first()
            ->id;
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments:genealabslaravelmodelcachingtestsfixturescomment-comments.commentable_type_=_GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post-comments.commentable_id_=_{$postId}-comments.commentable_id_notnull");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturescomment",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:comments",
        ];

        $result = (new Post)
            ->first()
            ->comments;

        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedPost)
            ->first()
            ->comments;

        $this->assertEquals($liveResults->pluck("commentable_id")->values()->toArray(), $result->pluck("commentable_id")->values()->toArray());
        $this->assertEquals($liveResults->pluck("commentable_id")->values()->toArray(), $cachedResults->pluck("commentable_id")->values()->toArray());
        $this->assertNotEmpty($result);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }
}
