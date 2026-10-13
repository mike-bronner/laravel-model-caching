<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Cache;

use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\DynamoDbStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\RedisTagSet;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Predis\ClientInterface;
use Predis\Command\Processor\KeyPrefixProcessor;
use RuntimeException;
use Throwable;

class ModelCacheRepository
{
    protected const DYNAMODB_NAMESPACE_PREFIX = 'genealabs:laravel-model-caching:dynamodb:v1:';
    protected const KEY_PREFIX = 'genealabs:laravel-model-caching:';
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
        $itemKey = $this->itemKey(key: $key, tags: $tags, hash: $hash);

        return $this->unwrap(cached: $this->repositoryFor(tags: $tags)->get(key: $itemKey));
    }

    public function remember(
        string $key,
        array $tags,
        DateTimeInterface|DateInterval|int $ttl,
        callable $callback,
        bool $hash = false,
    ): mixed {
        $prefix = static::SERIALIZED_VALUE_PREFIX;

        return $this->unwrap(cached: $this->repositoryFor(tags: $tags)->remember(
            key: $this->itemKey(key: $key, tags: $tags, hash: $hash),
            ttl: $ttl,
            callback: static function () use ($callback, $prefix): string {
                return $prefix . serialize(value: $callback());
            },
        ));
    }

    public function rememberForever(
        string $key,
        array $tags,
        callable $callback,
        bool $hash = false,
    ): mixed {
        $prefix = static::SERIALIZED_VALUE_PREFIX;

        return $this->unwrap(cached: $this->repositoryFor(tags: $tags)->rememberForever(
            key: $this->itemKey(key: $key, tags: $tags, hash: $hash),
            callback: static function () use ($callback, $prefix): string {
                return $prefix . serialize(value: $callback());
            },
        ));
    }

    protected function unwrap(mixed $cached): mixed
    {
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
            $this->flushTaggedCache($this->repositoryFor($tags));

            return;
        }

        collect($this->normalizeTags($tags))
            ->each(function (string $tag): void {
                $this->repository->forever($this->tagVersionKey($tag), $this->freshVersion());
            });
    }

    protected function flushTaggedCache(Repository|TaggedCache $repository): void
    {
        try {
            $repository->flush();
        } catch (Throwable $exception) {
            if (
                ! $repository instanceof TaggedCache
                || ! $this->isCrossSlotException($exception)
            ) {
                throw $exception;
            }

            $this->flushTaggedCacheBySlot($repository);
        }
    }

    protected function isCrossSlotException(\Throwable $exception): bool
    {
        return stripos($exception->getMessage(), 'CROSSSLOT') !== false;
    }

    protected function flushTaggedCacheBySlot(TaggedCache $repository): void
    {
        $store = $repository->getStore();

        if (! $store instanceof RedisStore) {
            throw new RuntimeException(
                'Cannot recover from a cross-slot cache flush failure on a '
                . 'non-Redis store.',
            );
        }

        $tags = $repository->getTags();
        $prefix = $store->getPrefix();
        $connection = $store->connection();

        if ($tags instanceof RedisTagSet) {
            $tags->entries()
                ->each(function (string $key) use ($connection, $prefix): void {
                    $connection->del($prefix . $key);
                });
        }

        $tags->flush();
    }

    public function invalidateAll(): void
    {
        if ($this->usesDynamoDb) {
            $this->repository->forever($this->globalVersionKey(), $this->freshVersion());

            return;
        }

        $store = $this->repository->getStore();

        if ($store instanceof RedisStore) {
            $this->flushRedisStoreByPrefix($store);

            return;
        }

        $this->repository->flush();
    }

    protected function flushRedisStoreByPrefix(RedisStore $store): void
    {
        $connection = $store->connection();
        $client = $connection->client();

        [$clientPrefix, $restoreClientPrefix] = $this->neutralizeClientPrefix($client);

        try {
            $storePrefix = $clientPrefix . $store->getPrefix();
            $escapedStorePrefix = addcslashes($storePrefix, '*?[]^\\');
            $tagSetPattern = $escapedStorePrefix . 'tag:' . static::KEY_PREFIX . '*:entries';

            foreach ($this->scanRedisKeys($connection, $client, $tagSetPattern) as $tagSetKeys) {
                collect($tagSetKeys)
                    ->each(function (string $tagSetKey) use ($connection, $storePrefix): void {
                        $this->deleteTaggedEntries($connection, $tagSetKey, $storePrefix);
                    });
            }

            $packageKeyPattern = $escapedStorePrefix . '*' . static::KEY_PREFIX . '*';

            foreach ($this->scanRedisKeys($connection, $client, $packageKeyPattern) as $keys) {
                $this->deleteRedisKeys($connection, $keys);
            }
        } finally {
            $restoreClientPrefix();
        }
    }

    protected function scanRedisKeys($connection, object $client, string $pattern): \Generator
    {
        $cursor = $client instanceof ClientInterface ? 0 : null;

        do {
            $result = $connection->scan($cursor, ['match' => $pattern, 'count' => 1000]);

            if ($result === false) {
                break;
            }

            [$cursor, $keys] = $result;

            if (is_array($keys) && $keys !== []) {
                yield $keys;
            }
        } while ((int) $cursor !== 0);
    }

    protected function deleteTaggedEntries($connection, string $tagSetKey, string $storePrefix): void
    {
        $start = 0;

        do {
            $members = $connection->zrange($tagSetKey, $start, $start + 999);
            $members = is_array($members) ? $members : [];

            if ($members !== []) {
                $this->deleteRedisKeys(
                    $connection,
                    collect($members)
                        ->map(fn (string $member): string => $storePrefix . $member)
                        ->all(),
                );
            }

            $start += 1000;
        } while (count($members) === 1000);
    }

    protected function neutralizeClientPrefix(object $client): array
    {
        if ($client instanceof ClientInterface) {
            $processor = $client->getOptions()->prefix ?? null;

            if (! $processor instanceof KeyPrefixProcessor) {
                return ['', static fn (): null => null];
            }

            $original = $processor->getPrefix();
            $processor->setPrefix('');

            return [
                $original,
                static function () use ($processor, $original): void {
                    $processor->setPrefix($original);
                },
            ];
        }

        $option = \Redis::OPT_PREFIX;
        $original = (string) $client->getOption($option);
        $client->setOption($option, '');

        return [
            $original,
            static function () use ($client, $option, $original): void {
                $client->setOption($option, $original);
            },
        ];
    }

    protected function deleteRedisKeys($connection, array $keys): void
    {
        try {
            $connection->del($keys);
        } catch (Throwable $exception) {
            if (! $this->isCrossSlotException($exception)) {
                throw $exception;
            }

            collect($keys)
                ->each(function (string $key) use ($connection): void {
                    $connection->del($key);
                });
        }
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
        $versions = collect([$this->globalVersionKey()])
            ->merge(collect($this->normalizeTags($tags))
                ->map(fn (string $tag): string => $this->tagVersionKey($tag)))
            ->map(fn (string $versionKey): string => $this->currentVersion($versionKey))
            ->implode(':');

        return $key . ':versions:' . $versions;
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
        return collect($tags)
            ->filter()
            ->uniqueStrict()
            ->sort()
            ->values()
            ->all();
    }
}
