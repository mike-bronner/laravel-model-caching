<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ThrowingCacheStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedRole;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class_exists('Aws\DynamoDb\Exception\DynamoDbException') || eval(<<<'PHP'
namespace Aws\DynamoDb\Exception;

class DynamoDbException extends \RuntimeException
{
    protected bool $connectionError = true;

    public function isConnectionError(): bool
    {
        return $this->connectionError;
    }
}

class ConnectionException extends DynamoDbException
{
    protected bool $connectionError = true;
}

class NonConnectionException extends DynamoDbException
{
    protected bool $connectionError = false;
}
PHP);

class_exists('Aws\DynamoDb\Exception\ConnectionException') || eval(<<<'PHP'
namespace Aws\DynamoDb\Exception;

class ConnectionException extends DynamoDbException
{
    public function __construct(string $message = 'Connection refused')
    {
        $this->message = $message;
    }

    public function isConnectionError(): bool
    {
        return true;
    }
}
PHP);

class_exists('Aws\DynamoDb\Exception\NonConnectionException') || eval(<<<'PHP'
namespace Aws\DynamoDb\Exception;

class NonConnectionException extends DynamoDbException
{
    public function __construct(string $message = 'Something else')
    {
        $this->message = $message;
    }

    public function isConnectionError(): bool
    {
        return false;
    }
}
PHP);

use Aws\DynamoDb\Exception\ConnectionException as AwsDynamoDbConnectionException;
use Aws\DynamoDb\Exception\NonConnectionException as AwsDynamoDbNonConnectionException;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;

class DynamoDbCacheFallbackTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDynamoDbStore::reset();
        app('cache')->extend('dynamodb', function () {
            return new Repository(new FakeDynamoDbStore);
        });
        app('cache')->forgetDriver('dynamodb-model');

        config([
            'cache.stores.dynamodb-model' => ['driver' => 'dynamodb'],
            'laravel-model-caching.store' => 'dynamodb-model',
        ]);
    }

    private function breakCacheConnection(string $exceptionClass): void
    {
        $throwingStore = new ThrowingCacheStore($exceptionClass);
        $throwingRepo = new Repository($throwingStore);

        $this->app->extend('cache', function ($cache) use ($throwingRepo) {
            return new class($this->app, $throwingRepo) extends CacheManager
            {
                public function __construct($app, private Repository $throwingRepo)
                {
                    parent::__construct($app);
                }

                public function store($name = null)
                {
                    return $this->throwingRepo;
                }

                public function driver($driver = null)
                {
                    return $this->throwingRepo;
                }
            };
        });
    }

    public function test_dynamo_db_connection_failures_fall_back_to_database_when_enabled(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $authors = Author::all();

        $this->assertNotEmpty($authors);
    }

    public function test_non_connection_dynamo_db_exceptions_are_not_swallowed(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);
        $this->breakCacheConnection(AwsDynamoDbNonConnectionException::class);

        $this->expectException(AwsDynamoDbNonConnectionException::class);

        Author::all();
    }

    public function test_delete_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = Author::factory()->create(['name' => 'Dynamo Delete Test']);

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $result = Author::where('id', $author->id)->delete();

        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function test_force_delete_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = Author::factory()->create(['name' => 'Dynamo Force Delete Test']);

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $result = Author::where('id', $author->id)->forceDelete();

        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function test_increment_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $book = Book::first();
        $originalPrice = $book->price;

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        Book::where('id', $book->id)->increment('price', 10);

        $book->refresh();

        $this->assertEquals($originalPrice + 10, $book->price);
    }

    public function test_decrement_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $book = Book::first();
        $originalPrice = $book->price;

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        Book::where('id', $book->id)->decrement('price', 5);

        $book->refresh();

        $this->assertEquals($originalPrice - 5, $book->price);
    }

    public function test_model_save_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $author = Author::first();
        $author->name = 'Saved During Dynamo Outage';

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $this->assertTrue($author->save());
    }

    public function test_model_create_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $author = Author::create([
            'name' => 'Created During Dynamo Outage',
            'email' => 'dynamo-outage@test.com',
        ]);

        $this->assertNotNull($author->id);
    }

    public function test_pivot_attach_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $user = User::query()->first();
        $newRole = Role::factory()->create();

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $user->roles()->attach($newRole->id);

        $this->assertTrue($user->roles()->where('roles.id', $newRole->id)->exists());
    }

    public function test_pivot_sync_succeeds_when_dynamo_db_invalidation_fails(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $pivotRow = DB::table('role_user')->first();
        $user = (new User)->newQueryWithoutScopes()->find($pivotRow->user_id);
        $roleIds = DB::table('role_user')
            ->where('user_id', $user->id)
            ->pluck('role_id')
            ->toArray();

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $result = $user->roles()->sync($roleIds);

        $this->assertIsArray($result);
    }

    public function test_uncached_related_model_invalidation_succeeds_when_dynamo_db_is_unavailable(): void
    {
        config(['laravel-model-caching.fallback-to-database' => true]);

        $pivotRow = DB::table('role_user')->first();
        $user = (new User)->newQueryWithoutScopes()->find($pivotRow->user_id);
        $newRole = UncachedRole::create(['name' => 'uncached-dynamo-role']);

        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        Log::shouldReceive('warning')
            ->atLeast()
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

        $user->uncachedRolesWithCustomPivot()->attach($newRole->id);

        $this->assertTrue(
            $user->fresh()->uncachedRolesWithCustomPivot()->where('roles.id', $newRole->id)->exists(),
        );
    }

    public function test_clear_command_returns_non_zero_when_dynamo_db_is_unavailable(): void
    {
        $this->breakCacheConnection(AwsDynamoDbConnectionException::class);

        $this->artisan('modelCache:clear')
            ->assertExitCode(1);
    }
}
