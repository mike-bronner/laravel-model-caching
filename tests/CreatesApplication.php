<?php

namespace GeneaLabs\LaravelModelCaching\Tests;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Providers\Service as LaravelModelCachingService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Throwable;

trait CreatesApplication
{
    private static $baseLineDatabaseMigrated = false;

    protected $cache;
    public $testingSqlitePath;

    protected bool $redisIsAvailable = false;

    protected function cache(): object
    {
        $cache = app(abstract: "cache");

        if (config(key: "laravel-model-caching.store")) {
            $cache = $cache->store(name: config(key: "laravel-model-caching.store"));
        }

        return $this->makeCacheDeserializeProxy(store: $cache);
    }

    private function testDatabaseDirectory(): string
    {
        $token = env(key: "TEST_TOKEN");

        return $token
            ? __DIR__ . "/database/parallel-{$token}"
            : __DIR__ . "/database";
    }

    protected function deserializeCacheValue(mixed $value): mixed
    {
        $prefix = ModelCacheRepository::SERIALIZED_VALUE_PREFIX;

        if (
            ! is_string(value: $value)
            || ! str_starts_with(haystack: $value, needle: $prefix)
        ) {
            return $value;
        }

        return unserialize(
            data: substr(string: $value, offset: strlen(string: $prefix)),
            options: ["allowed_classes" => true],
        );
    }

    protected function makeCacheDeserializeProxy(object $store): object
    {
        $deserializer = fn (mixed $value): mixed => $this->deserializeCacheValue(value: $value);
        $workerFlush = function (): void {
            $this->flushWorkerCacheKeys();
        };

        return new class($store, $deserializer, $workerFlush)
        {
            public function __construct(
                private readonly object $store,
                private readonly \Closure $deserializer,
                private readonly \Closure $workerFlush,
            ) {}

            public function __call(string $name, array $arguments): mixed
            {
                if (
                    $name === "flush"
                    && ! ($this->store instanceof \Illuminate\Cache\TaggedCache)
                ) {
                    ($this->workerFlush)();

                    return true;
                }

                $result = $this->store->{$name}(...$arguments);

                if ($name === "get") {
                    return ($this->deserializer)($result);
                }

                if (
                    is_object(value: $result)
                    && ($result instanceof Repository || method_exists(object_or_class: $result, method: "get"))
                ) {
                    return new self(
                        store: $result,
                        deserializer: $this->deserializer,
                        workerFlush: $this->workerFlush,
                    );
                }

                return $result;
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipWithoutRedis();
        $this->setUpBaseLineSqlLiteDatabase();

        $databasePath = $this->testDatabaseDirectory();

        if (! is_dir(filename: $databasePath)) {
            mkdir(directory: $databasePath, permissions: 0755, recursive: true);
        }

        $this->testingSqlitePath = "{$databasePath}/";
        $baselinePath = "{$databasePath}/baseline.sqlite";
        $testingPath = "{$databasePath}/testing.sqlite";

        ! file_exists($testingPath)
            ?: unlink($testingPath);
        copy($baselinePath, $testingPath);

        require __DIR__ . '/routes/web.php';

        view()->addLocation(__DIR__ . '/resources/views', 'laravel-model-caching');

        $this->cache = $this->makeCacheDeserializeProxy(
            store: app(abstract: "cache")->store(name: config(key: "laravel-model-caching.store")),
        );
        $this->flushWorkerCacheKeys();
    }

    protected function skipWithoutRedis(): void
    {
        try {
            app(abstract: "redis")
                ->connection(name: "model-cache")
                ->ping();

            $this->redisIsAvailable = true;
        } catch (Throwable $exception) {
            $this->redisIsAvailable = false;
            $host = env(key: "REDIS_HOST", default: "127.0.0.1");

            $this->markTestSkipped(
                "No reachable Redis server at {$host}. This suite caches "
                . "against Redis, so every test needs one running. "
                . "Start one, or point REDIS_HOST at an existing server. "
                . "Reported: {$exception->getMessage()}"
            );
        }
    }

    protected function flushWorkerCacheKeys(): void
    {
        $token = (int) env(key: "TEST_TOKEN", default: 1);
        $connection = app(abstract: "redis")->connection(name: "model-cache");
        $keys = $this->scanRedisKeys($connection, "lmc-test-{$token}:*");

        if ($keys !== []) {
            $connection->del($keys);
        }
    }

    protected function scanRedisKeys(object $connection, string $pattern): array
    {
        $cursor = $connection->client() instanceof \Predis\ClientInterface ? 0 : null;
        $keys = [];

        do {
            $result = $connection->scan($cursor, ['match' => $pattern, 'count' => 1000]);

            if ($result === false) {
                break;
            }

            [$cursor, $found] = $result;

            if (is_array(value: $found) && $found !== []) {
                $keys = array_merge($keys, $found);
            }
        } while ((int) $cursor !== 0);

        return $keys;
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelModelCachingService::class,
        ];
    }

    public function setUpBaseLineSqlLiteDatabase()
    {
        if (self::$baseLineDatabaseMigrated) {
            return;
        }

        self::$baseLineDatabaseMigrated = true;
        $originalDefaultConnection = $this->app['config']
            ->get('database.default');

        $databasePath = $this->testDatabaseDirectory();

        if (! is_dir(filename: $databasePath)) {
            mkdir(directory: $databasePath, permissions: 0755, recursive: true);
        }

        $file = "{$databasePath}/baseline.sqlite";
        $this->app['config']->set('database.default', 'baseline');
        $this->app['config']->set('database.connections.baseline', [
            'driver' => 'sqlite',
            "url" => null,
            'database' => $file,
            'prefix' => '',
            "foreign_key_constraints" => false,
        ]);

        ! file_exists($file)
            ?: unlink($file);
        touch($file);

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Artisan::call('db:seed', [
            '--class' => 'DatabaseSeeder',
            '--database' => 'baseline',
        ]);

        $this->app['config']->set('database.default', $originalDefaultConnection);
    }

    protected function getEnvironmentSetUp($app)
    {
        $token = (int) env(key: "TEST_TOKEN", default: 1);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => $this->testDatabaseDirectory() . '/testing.sqlite',
            'prefix' => '',
            "foreign_key_constraints" => false,
        ]);
        $app['config']->set('database.redis.client', env('REDIS_CLIENT', 'phpredis'));
        $app['config']->set('database.redis.options', [
            'cluster' => 'redis',
            'prefix' => '',
            'persistent' => false,
        ]);
        $app['config']->set('database.redis.cache', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
        ]);
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
        ]);
        $app['config']->set('database.redis.model-cache', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 1,
        ]);
        $app['config']->set('cache.stores.model', [
            'driver' => 'redis',
            'connection' => 'model-cache',
            'prefix' => "lmc-test-{$token}:",
        ]);
        $app['config']->set('database.redis.model-cache-prefixed', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 2,
            'options' => ['prefix' => "tenant-{$token}:"],
        ]);
        $app['config']->set('cache.stores.model-prefixed', [
            'driver' => 'redis',
            'connection' => 'model-cache-prefixed',
            'prefix' => "lmc-test-{$token}:",
        ]);
        $app['config']->set('laravel-model-caching.store', 'model');
    }
}
