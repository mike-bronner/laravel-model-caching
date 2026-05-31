<?php

namespace GeneaLabs\LaravelModelCaching\Tests;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Providers\Service as LaravelModelCachingService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Artisan;

trait CreatesApplication
{
    private static $baseLineDatabaseMigrated = false;

    protected $cache;
    protected $testingSqlitePath;

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

    protected function flushWorkerCacheKeys(): void
    {
        $token = (int) env(key: "TEST_TOKEN", default: 1);
        $pattern = "lmc-test-{$token}:*";
        $client = app(abstract: "redis")->connection(name: "model-cache")->client();
        $cursor = null;

        do {
            $found = $client->scan($cursor, $pattern, 1000);

            if (is_array(value: $found) && $found !== []) {
                $client->del($found);
            }
        } while ($cursor > 0);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
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
        $app['config']->set('database.redis.client', "phpredis");
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
        $app['config']->set('laravel-model-caching.store', 'model');
    }

    public function appVersionEightAndNine(): bool
    {
        return version_compare(app()->version(), '8.0.0', '>=')
            && version_compare(app()->version(), '10.0.0', '<');
    }

    public function appVersionFiveBetweenSeven(): bool
    {
        return version_compare(app()->version(), '5.6.0', '>=')
            && version_compare(app()->version(), '8.0.0', '<');
    }

    public function appVersionOld(): bool
    {
        return version_compare(app()->version(), '5.4.0', '>=')
            && version_compare(app()->version(), '5.6.0', '<');
    }

    public function appVersionTen(): bool
    {
        return version_compare(app()->version(), '10.0.0', '>=')
            && version_compare(app()->version(), '11.0.0', '<');
    }

    public function appVersionEleven(): bool
    {
        return version_compare(app()->version(), '11.0.0', '>=')
            && version_compare(app()->version(), '12.0.0', '<');
    }

    public function appVersionTwelve(): bool
    {
        return version_compare(app()->version(), '12.0.0', '>=');
    }
}
