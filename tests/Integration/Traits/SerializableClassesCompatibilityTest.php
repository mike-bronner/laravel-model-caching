<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Traits;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Collection;

class SerializableClassesCompatibilityTest extends IntegrationTestCase
{
    public function test_models_are_cached_and_retrieved_when_serializable_classes_is_false(): void
    {
        config(['cache.serializable_classes' => false]);

        $cachedAuthors = Author::all();
        $liveAuthors = UncachedAuthor::all();

        config(['cache.serializable_classes' => true]);

        $this->assertInstanceOf(Collection::class, $cachedAuthors);
        $this->assertCount($liveAuthors->count(), $cachedAuthors);
    }

    public function test_raw_stored_value_is_serialized_string_not_php_object(): void
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];

        Author::all();

        $rawValue = app("cache")
            ->store(config("laravel-model-caching.store"))
            ->tags($tags)
            ->get($key);

        $this->assertIsString($rawValue);
        $this->assertStringStartsWith(ModelCacheRepository::SERIALIZED_VALUE_PREFIX, $rawValue);
    }

    public function test_cached_models_match_live_results_under_serializable_classes_restriction(): void
    {
        config(['cache.serializable_classes' => false]);

        $firstCall = Author::all();
        $secondCall = Author::all();
        $liveAuthors = UncachedAuthor::all();

        config(['cache.serializable_classes' => true]);

        $this->assertEquals($liveAuthors->pluck("id")->toArray(), $firstCall->pluck("id")->toArray());
        $this->assertEquals($firstCall->pluck("id")->toArray(), $secondCall->pluck("id")->toArray());
    }
}
