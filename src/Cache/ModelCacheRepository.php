<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Cache;

use Illuminate\Cache\DynamoDbStore;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

class ModelCacheRepository
{
    protected const DYNAMODB_NAMESPACE_PREFIX = 'genealabs:laravel-model-caching:dynamodb:v1:';

    public const SERIALIZED_VALUE_PREFIX = 'genealabs:lmc:v1:serialized:';

    public function __construct(
        protected Repository $repository,
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
        $cached = $this->repositoryFor(tags: $tags)->get(key: $this->itemKey(key: $key, tags: $tags, hash: $hash));

        if (! is_string(value: $cached)) {
            return $cached;
        }

        if (! str_starts_with(haystack: $cached, needle: static::SERIALIZED_VALUE_PREFIX)) {
            return $cached;
        }

        return unserialize(
            data: substr(string: $cached, offset: strlen(string: static::SERIALIZED_VALUE_PREFIX)),
            options: ["allowed_classes" => true],
        );
    }

    public function rememberForever(
        string $key,
        array $tags,
        callable $callback,
        bool $hash = false,
    ): mixed {
        $prefix = static::SERIALIZED_VALUE_PREFIX;
        $cached = $this->repositoryFor(tags: $tags)->rememberForever(
            key: $this->itemKey(key: $key, tags: $tags, hash: $hash),
            callback: static function () use ($callback, $prefix): string {
                return $prefix . serialize(value: $callback());
            },
        );

        if (! is_string(value: $cached)) {
            return $cached;
        }

        if (! str_starts_with(haystack: $cached, needle: $prefix)) {
            return $cached;
        }

        return unserialize(
            data: substr(string: $cached, offset: strlen(string: $prefix)),
            options: ["allowed_classes" => true],
        );
    }

    public function forever(string $key, mixed $value, array $tags = [], bool $hash = false): bool
    {
        return $this->repositoryFor(tags: $tags)->forever(
            key: $this->itemKey(key: $key, tags: $tags, hash: $hash),
            value: static::SERIALIZED_VALUE_PREFIX . serialize(value: $value),
        );
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

    protected function repositoryFor(array $tags): Repository|TaggedCache
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
        // DynamoDB control keys are bounded: one global namespace key plus one
        // key per normalized tag hash. Query entries are the only records that
        // accumulate until TTL removes them.
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
        // Hashing keeps control keys short and stable even for long or
        // punctuation-heavy tag strings. Namespace collisions are therefore
        // limited to theoretical SHA-1 collisions.
        return static::DYNAMODB_NAMESPACE_PREFIX . 'tag-version:' . sha1($tag);
    }

    protected function normalizeTags(array $tags): array
    {
        $tags = array_values(array_unique(array_filter($tags)));
        sort($tags);

        return $tags;
    }
}
