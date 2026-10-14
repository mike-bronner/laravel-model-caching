<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CacheTags;
use Illuminate\Database\Eloquent\Model;

function useArrayModelCacheStore(): void
{
    config([
        "cache.stores.ttl-array" => ["driver" => "array"],
        "laravel-model-caching.store" => "ttl-array",
    ]);
}

function expectRefreshAfterTtl(callable $read, callable $writeOutsideEloquent, int $ttl): void
{
    $cached = $read();
    $writeOutsideEloquent();

    test()->travel($ttl - 1)->seconds();

    expect($read())->toEqual($cached, "Expected a cache hit before the {$ttl} second TTL passed");

    test()->travel(2)->seconds();

    expect($read())->not->toEqual($cached, "Expected a fresh read after the {$ttl} second TTL passed");
}

function expectNoExpiry(callable $read, callable $writeOutsideEloquent): void
{
    $cached = $read();
    $writeOutsideEloquent();

    test()->travel(1)->years();

    expect($read())->toEqual($cached, "Expected the cached value to never expire");
}

function modelWithCacheTtl(mixed $ttl): Model
{
    $model = new class extends Model
    {
        public mixed $cacheTtlSeconds = null;
    };
    $model->cacheTtlSeconds = $ttl;

    return $model;
}

function resolvedCacheTtl(Model $model, array $eagerLoad = []): ?int
{
    return (new CacheTags($eagerLoad, $model, null))->ttl();
}

function redisModelCacheItemTtls(): array
{
    $token = (int) env("TEST_TOKEN", 1);
    $connection = app("redis")->connection("model-cache");

    return collect($connection->keys("lmc-test-{$token}:*"))
        ->reject(fn (string $key): bool => str_contains($key, ":tag:"))
        ->map(fn (string $key): int => (int) $connection->ttl($key))
        ->values()
        ->all();
}
