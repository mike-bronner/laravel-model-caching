<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Facades\ModelCache;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\Repository;

class DynamoDbModelCachingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDynamoDbStore::reset();
        app('cache')->extend('dynamodb', function () {
            return new Repository(new FakeDynamoDbStore);
        });
        config([
            'cache.stores.dynamodb-model' => ['driver' => 'dynamodb'],
            'laravel-model-caching.store' => 'dynamodb-model',
        ]);
    }

    public function test_repeated_queries_reuse_cached_results_on_dynamo_db(): void
    {
        $first = Author::query()->get();
        $writesAfterFirstQuery = FakeDynamoDbStore::writeCount();
        $second = Author::query()->get();

        $this->assertEquals($first, $second);
        $this->assertSame($writesAfterFirstQuery, FakeDynamoDbStore::writeCount());
    }

    public function test_model_invalidation_refreshes_only_the_target_model_namespace(): void
    {
        $cachedAuthors = Author::query()->get();
        $cachedBooks = Book::query()->get();
        $authorId = $cachedAuthors->first()->id;
        $bookId = $cachedBooks->first()->id;

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'DYNAMODB_UPDATED_AUTHOR']);
        UncachedBook::query()
            ->where('id', $bookId)
            ->update(['title' => 'DYNAMODB_UPDATED_BOOK']);

        ModelCache::invalidate(Author::class);

        $freshAuthors = Author::query()->get();
        $staleBooks = Book::query()->get();

        $this->assertSame(
            'DYNAMODB_UPDATED_AUTHOR',
            $freshAuthors->firstWhere('id', $authorId)->name,
        );
        $this->assertNotSame(
            'DYNAMODB_UPDATED_BOOK',
            $staleBooks->firstWhere('id', $bookId)->title,
        );
    }

    public function test_clear_command_logically_invalidates_all_entries_without_flushing_store(): void
    {
        $cachedAuthors = Author::query()->get();
        $cachedBooks = Book::query()->get();
        $authorId = $cachedAuthors->first()->id;
        $bookId = $cachedBooks->first()->id;

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'CLEARED_AUTHOR']);
        UncachedBook::query()
            ->where('id', $bookId)
            ->update(['title' => 'CLEARED_BOOK']);

        $this->artisan('modelCache:clear')
            ->assertExitCode(0);

        $freshAuthors = Author::query()->get();
        $freshBooks = Book::query()->get();

        $this->assertSame(0, FakeDynamoDbStore::flushCount());
        $this->assertSame('CLEARED_AUTHOR', $freshAuthors->firstWhere('id', $authorId)->name);
        $this->assertSame('CLEARED_BOOK', $freshBooks->firstWhere('id', $bookId)->title);
    }

    public function test_cooldown_metadata_remains_unversioned_on_dynamo_db(): void
    {
        AuthorWithCooldown::query()
            ->withCacheCooldownSeconds(60)
            ->get();

        $keys = FakeDynamoDbStore::keys();

        $this->assertTrue(
            collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:seconds')),
        );
        $this->assertTrue(
            collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:invalidated-at')),
        );
        $this->assertFalse(
            collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:seconds:versions:')),
        );
    }

    public function test_pivot_operations_invalidate_cached_relations_without_flushing_store(): void
    {
        $user = User::query()->first();
        $initialCount = $user->roles()->count();
        $newRole = Role::factory()->create();

        $user->roles()->attach($newRole->id);

        $freshCount = User::query()
            ->find($user->id)
            ->roles()
            ->count();

        $this->assertSame($initialCount + 1, $freshCount);
        $this->assertSame(0, FakeDynamoDbStore::flushCount());
    }
}
