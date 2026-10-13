<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Providers;

use GeneaLabs\LaravelModelCaching\Console\Commands\Clear;
use GeneaLabs\LaravelModelCaching\Console\Commands\Publish;
use GeneaLabs\LaravelModelCaching\Helper;
use GeneaLabs\LaravelModelCaching\ModelCaching;
use Illuminate\Support\ServiceProvider;

class Service extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $configPath = __DIR__ . '/../../config/laravel-model-caching.php';
        $this->mergeConfigFrom($configPath, 'laravel-model-caching');
        $this->commands([
            Clear::class,
            Publish::class,
        ]);
        $this->publishes([
            $configPath => config_path('laravel-model-caching.php'),
        ], "config");
    }

    public function register(): void
    {
        $this->app->bind("model-cache", Helper::class);
    }
}
