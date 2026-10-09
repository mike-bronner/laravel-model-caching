<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakePredisConnectionException;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->cache()->flush();
});

test('cache read failure falls through to database when enabled', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $authors = Author::all();

    expect($authors)->not->toBeNull();
    expect($authors)->not->toBeEmpty();
});

test('cache read failure falls through with redis exception', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection(\RedisException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $authors = Author::all();

    expect($authors)->not->toBeNull();
    expect($authors)->not->toBeEmpty();
});

test('cache read failure falls through with predis exception', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection(FakePredisConnectionException::class);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $authors = Author::all();

    expect($authors)->not->toBeNull();
    expect($authors)->not->toBeEmpty();
});

test('is cache connection exception recognizes predis exception', function () {
    $instance = new Author;
    $predisException = new FakePredisConnectionException('Connection refused');

    expect($instance->isCacheConnectionException($predisException))->toBeTrue();
});

test('is cache connection exception recognizes redis exception', function () {
    $instance = new Author;
    $redisException = new \RedisException('Connection refused');

    expect($instance->isCacheConnectionException($redisException))->toBeTrue();
});

test('is cache connection exception rejects unrelated exceptions', function () {
    $instance = new Author;
    $runtimeException = new \RuntimeException('Something else');

    expect($instance->isCacheConnectionException($runtimeException))->toBeFalse();
});

test('cache read failure throws when fallback disabled', function () {
    config(['laravel-model-caching.fallback-to-database' => false]);
    breakCacheConnection();

    expect(fn () => Author::all())->toThrow(\RedisException::class);
});

test('non connection exception is not swallowed', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection(\RuntimeException::class);

    expect(fn () => Author::all())->toThrow(\RuntimeException::class);
});

test('cache flush failure logs warning when fallback enabled', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = (new Author)->newQueryWithoutScopes()->first();
    expect($author)->not->toBeNull();

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $author->flushCache();
});

test('cache flush failure throws when fallback disabled', function () {
    config(['laravel-model-caching.fallback-to-database' => false]);

    $author = (new Author)->newQueryWithoutScopes()->first();
    expect($author)->not->toBeNull();

    breakCacheConnection();

    expect(fn () => $author->flushCache())->toThrow(\RedisException::class);
});

test('fallback config defaults to false', function () {
    expect(config('laravel-model-caching.fallback-to-database', false))->toBeFalse();
});

test('mock cache store end to end fallback', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);
    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $authors = Author::query()->get();

    expect($authors)->not->toBeEmpty();
    expect($authors)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('delete succeeds when cache flush fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = Author::factory()->create(['name' => 'DeleteTest']);
    $authorId = $author->id;

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $result = Author::where('id', $authorId)->delete();

    expect($result)->toBeGreaterThanOrEqual(1);
});

test('force delete succeeds when cache flush fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = Author::factory()->create(['name' => 'ForceDeleteTest']);
    $authorId = $author->id;

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $result = Author::where('id', $authorId)->forceDelete();

    expect($result)->toBeGreaterThanOrEqual(1);
});

test('increment succeeds when cache flush fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $book = Book::first();
    $originalPrice = $book->price;

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    Book::where('id', $book->id)->increment('price', 10);

    $book->refresh();
    expect($book->price)->toEqual($originalPrice + 10);
});

test('decrement succeeds when cache flush fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $book = Book::first();
    $originalPrice = $book->price;

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    Book::where('id', $book->id)->decrement('price', 5);

    $book->refresh();
    expect($book->price)->toEqual($originalPrice - 5);
});

test('model save flushes gracefully when cache fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $author = Author::first();
    $author->name = 'Updated via Save';

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $result = $author->save();

    expect($result)->toBeTrue();
});

test('model create flushes gracefully when cache fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $author = Author::create([
        'name' => 'Created During Outage',
        'email' => 'outage@test.com',
    ]);

    expect($author)->not->toBeNull();
    expect($author->id)->not->toBeNull();
});

test('pivot sync succeeds when cache flush fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    $pivotRow = \Illuminate\Support\Facades\DB::table('role_user')->first();
    expect($pivotRow)->not->toBeNull('Pivot table should have seeded data');

    $user = (new User)->newQueryWithoutScopes()->find($pivotRow->user_id);
    expect($user)->not->toBeNull();

    $roleIds = \Illuminate\Support\Facades\DB::table('role_user')
        ->where('user_id', $user->id)
        ->pluck('role_id')
        ->toArray();

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $result = $user->roles()->sync($roleIds);

    expect($result)->toBeArray();
});

test('has many through falls back when cache fails', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $author = (new Author)->newQueryWithoutScopes()->first();
    $printers = $author->printers()->get();

    expect($printers)->not->toBeNull();
    expect($printers)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('query get falls back with broken cache and cooldown', function () {
    config(['laravel-model-caching.fallback-to-database' => true]);

    breakCacheConnection();

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'laravel-model-caching');
        });

    $authors = Author::all();

    expect($authors)->not->toBeNull();
    expect($authors)->not->toBeEmpty();
});

test('flush cache is no op when caching disabled', function () {
    config(['laravel-model-caching.enabled' => false]);
    config(['laravel-model-caching.fallback-to-database' => false]);
    breakCacheConnection();

    (new Author)->flushCache();
})->throwsNoExceptions();
