<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTypedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class TypedBuilderAnnotationTest extends IntegrationTestCase
{
    public function testDocumentedOverrideStillReturnsCachedBuilder()
    {
        $this->assertInstanceOf(CachedBuilder::class, AuthorWithTypedBuilder::query());
    }

    public function testDocumentedOverrideStillCachesAndFlushes()
    {
        $before = (new AuthorWithTypedBuilder)->orderBy("id")->pluck("name");
        (new UncachedAuthor)->where("id", 1)->update(["name" => "renamed behind the cache"]);

        $this->assertEquals($before, (new AuthorWithTypedBuilder)->orderBy("id")->pluck("name"));

        AuthorWithTypedBuilder::flushCacheNamed("renamed behind the cache");

        $this->assertEquals(
            (new UncachedAuthor)->orderBy("id")->pluck("name"),
            (new AuthorWithTypedBuilder)->orderBy("id")->pluck("name"),
        );
    }
}
