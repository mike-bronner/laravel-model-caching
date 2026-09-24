<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorExtendingAliasedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class AliasedBuilderInheritanceTest extends IntegrationTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChildOfDocumentedOverrideReturnsCachedBuilderAndCaches()
    {
        $this->assertInstanceOf(CachedBuilder::class, (new AuthorExtendingAliasedBuilder)->newQuery());
        $this->assertCount(10, (new AuthorExtendingAliasedBuilder)->get());
    }
}
