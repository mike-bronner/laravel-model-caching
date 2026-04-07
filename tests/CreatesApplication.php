<?php

namespace GeneaLabs\LaravelModelCaching\Tests;

use GeneaLabs\LaravelModelCaching\Providers\Service as LaravelModelCachingService;
use Illuminate\Support\Facades\Artisan;

trait CreatesApplication
{
    private static $baseLineDatabaseMigrated = false;

    protected $cache;
    protected $testingSqlitePath;

    protected function cache()
    {
        $cache = app('cache');

        if (config('laravel-model-caching.store')) {
            $cache = $cache->store(config('laravel-model-caching.store'));
        }

        return $cache;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBaseLineSqlLiteDatabase();

        $databasePath = __DIR__ . "/database";
        $this->testingSqlitePath = "{$databasePath}/";
        $baselinePath = "{$databasePath}/baseline.sqlite";
        $testingPath = "{$databasePath}/testing.sqlite";

        ! file_exists($testingPath)
            ?: unlink($testingPath);
        copy($baselinePath, $testingPath);

        require __DIR__ . '/routes/web.php';

        view()->addLocation(__DIR__ . '/resources/views', 'laravel-model-caching');

        $this->cache = app('cache')
            ->store(config('laravel-model-caching.store'));
        $this->cache()->flush();
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

        $file = __DIR__ . '/database/baseline.sqlite';
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

        $this->app['config']->set('database.default', 'testing');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/database/testing.sqlite',
            'prefix' => '',
            "foreign_key_constraints" => false,
        ]);
        $app['config']->set('database.redis.client', "phpredis");
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
