<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Cache;

use Illuminate\Cache\DynamoDbStore;
use Illuminate\Cache\TaggableStore;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

class ModelCacheRepository
{
    protected const DYNAMODB_NAMESPACE_PREFIX = 'genealabs:laravel-model-caching:dynamodb:v1:';

    public function __construct(
        protected $repository,
        protected bool $usesDynamoDb = false,
    ) {}

    public static function make(): static
    {
        $container = Container::getInstance();
        $cache = $container->make('cache');
        $config = $container->make('config');
        $storeName = $config->get('laravel-model-caching.store');
        $repository = $storeName
            ? $cache->store($storeName)
            : $cache->store();
        $driver = $storeName
            ? $config->get("cache.stores.{$storeName}.driver")
            : $config->get('cache.default');
        $usesDynamoDb = $driver === 'dynamodb';

        if (
            ! $usesDynamoDb
            && class_exists('\Illuminate\Cache\DynamoDbStore')
        ) {
            $usesDynamoDb = $repository->getStore() instanceof DynamoDbStore;
        }

        return new static($repository, $usesDynamoDb);
    }

    public function usesDynamoDb(): bool
    {
        return $this->usesDynamoDb;
    }

    public function get(string $key, array $tags = [], bool $hash = false): mixed
    {
        return $this->repositoryFor($tags)->get($this->itemKey($key, $tags, $hash));
    }

    public function rememberForever(
        string $key,
        array $tags,
        callable $callback,
        bool $hash = false,
    ): mixed {
        return $this->repositoryFor($tags)->rememberForever(
            $this->itemKey($key, $tags, $hash),
            $callback,
        );
    }

    public function forever(string $key, mixed $value, array $tags = [], bool $hash = false): bool
    {
        return $this->repositoryFor($tags)->forever($this->itemKey($key, $tags, $hash), $value);
    }

    public function forget(string $key, array $tags = [], bool $hash = false): bool
    {
        return $this->repositoryFor($tags)->forget($this->itemKey($key, $tags, $hash));
    }

    public function invalidateTags(array $tags): void
    {
        if (! $this->usesDynamoDb) {
            $this->repositoryFor($tags)->flush();

            return;
        }

        foreach ($this->normalizeTags($tags) as $tag) {
            $this->repository->forever($this->tagVersionKey($tag), $this->freshVersion());
        }
    }

    public function invalidateAll(): void
    {
        if (! $this->usesDynamoDb) {
            $this->repository->flush();

            return;
        }

        $this->repository->forever($this->globalVersionKey(), $this->freshVersion());
    }

    protected function repositoryFor(array $tags)
    {
        if (
            ! $this->usesDynamoDb
            && is_subclass_of($this->repository->getStore(), TaggableStore::class)
        ) {
            return $this->repository->tags($tags);
        }

        return $this->repository;
    }

    protected function itemKey(string $key, array $tags, bool $hash): string
    {
        $key = $this->usesDynamoDb
            ? $this->versionedKey($key, $tags)
            : $key;

        return $hash
            ? sha1($key)
            : $key;
    }

    protected function versionedKey(string $key, array $tags): string
    {
        $versions = [$this->currentVersion($this->globalVersionKey())];

        foreach ($this->normalizeTags($tags) as $tag) {
            $versions[] = $this->currentVersion($this->tagVersionKey($tag));
        }

        return $key . ':versions:' . implode(':', $versions);
    }

    protected function currentVersion(string $versionKey): string
    {
        return (string) $this->repository->rememberForever(
            $versionKey,
            fn () => $this->freshVersion(),
        );
    }

    protected function freshVersion(): string
    {
        return (string) Str::uuid();
    }

    protected function globalVersionKey(): string
    {
        return static::DYNAMODB_NAMESPACE_PREFIX . 'global-version';
    }

    protected function tagVersionKey(string $tag): string
    {
        return static::DYNAMODB_NAMESPACE_PREFIX . 'tag-version:' . sha1($tag);
    }

    protected function normalizeTags(array $tags): array
    {
        $tags = array_values(array_unique(array_filter($tags)));
        sort($tags);

        return $tags;
    }
}
