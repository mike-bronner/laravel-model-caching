<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedRole;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbConnectionException;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbNonConnectionException;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;

beforeEach(function () {
    FakeDynamoDbStore::reset();
    app('cache')->extend('dynamodb', function () {
        return new Repository(new FakeDynamoDbStore);
    });
    app('cache')->forgetDriver('dynamodb-model');

    config([
        'cache.stores.dynamodb-model' => ['driver' => 'dynamodb'],
        'laravel-model-caching.store' => 'dynamodb-model',
    ]);
});

test('dynamo db connection failures fall back to database when enabled', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $authors = Author::all();

    expect($authors)->not->toBeEmpty();
});

test('non connection dynamo db exceptions are not swallowed', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection(FakeDynamoDbNonConnectionException::class);

    expect(fn () => Author::all())->toThrow(FakeDynamoDbNonConnectionException::class);
});

test('delete succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = Author::factory()->create(['name' => 'Dynamo Delete Test']);

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $result = Author::where('id', $author->id)->delete();

    expect($result)->toBeGreaterThanOrEqual(1);
});

test('force delete succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = Author::factory()->create(['name' => 'Dynamo Force Delete Test']);

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $result = Author::where('id', $author->id)->forceDelete();

    expect($result)->toBeGreaterThanOrEqual(1);
});

test('increment succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $book = Book::first();
    $originalPrice = $book->price;

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    Book::where('id', $book->id)->increment('price', 10);

    $book->refresh();

    expect($book->price)->toEqual($originalPrice + 10);
});

test('decrement succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $book = Book::first();
    $originalPrice = $book->price;

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    Book::where('id', $book->id)->decrement('price', 5);

    $book->refresh();

    expect($book->price)->toEqual($originalPrice - 5);
});

test('model save succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = Author::first();
    $author->name = 'Saved During Dynamo Outage';

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    expect($author->save())->toBeTrue();
});

test('model create succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $author = Author::create([
        'name' => 'Created During Dynamo Outage',
        'email' => 'dynamo-outage@test.com',
    ]);

    expect($author->id)->not->toBeNull();
});

test('pivot attach succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $user = User::query()->first();
    $newRole = Role::factory()->create();

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $user->roles()->attach($newRole->id);

    expect($user->roles()->where('roles.id', $newRole->id)->exists())->toBeTrue();
});

test('pivot sync succeeds when dynamo db invalidation fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $pivotRow = DB::table('role_user')->first();
    $user = (new User)->newQueryWithoutScopes()->find($pivotRow->user_id);
    $roleIds = DB::table('role_user')
        ->where('user_id', $user->id)
        ->pluck('role_id')
        ->toArray();

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $result = $user->roles()->sync($roleIds);

    expect($result)->toBeArray();
});

test('uncached related model invalidation succeeds when dynamo db is unavailable', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $pivotRow = DB::table('role_user')->first();
    $user = (new User)->newQueryWithoutScopes()->find($pivotRow->user_id);
    $newRole = UncachedRole::create(['name' => 'uncached-dynamo-role']);

    breakCacheConnection(FakeDynamoDbConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'laravel-model-caching'));

    $user->uncachedRolesWithCustomPivot()->attach($newRole->id);

    expect($user->fresh()->uncachedRolesWithCustomPivot()->where('roles.id', $newRole->id)->exists())->toBeTrue();
});

test('clear command returns non zero when dynamo db is unavailable', function () {
    breakCacheConnection(FakeDynamoDbConnectionException::class);

    $this->artisan('modelCache:clear')
        ->assertExitCode(1);
});
