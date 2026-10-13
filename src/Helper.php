<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Illuminate\Container\Container;
use InvalidArgumentException;

class Helper
{
    public function runDisabled(callable $closure): mixed
    {
        $originalSetting = Container::getInstance()
            ->make("config")
            ->get('laravel-model-caching.enabled');

        Container::getInstance()
            ->make("config")
            ->set(['laravel-model-caching.enabled' => false]);

        try {
            return $closure();
        } finally {
            Container::getInstance()
                ->make("config")
                ->set(['laravel-model-caching.enabled' => $originalSetting]);
        }
    }

    public function invalidate($modelClasses): void
    {
        $modelClasses = is_array($modelClasses) ? $modelClasses : [$modelClasses];

        collect($modelClasses)
            ->each(function ($modelClass): void {
                $this->invalidateModel($modelClass);
            });
    }

    protected function invalidateModel(string $modelClass): void
    {
        $usesCachable = in_array(
            "GeneaLabs\LaravelModelCaching\Traits\Cachable",
            class_uses_recursive($modelClass)
        );

        if (! $usesCachable) {
            throw new InvalidArgumentException(
                "'{$modelClass}' does not use the Cachable trait and cannot be cache-invalidated."
            );
        }

        (new $modelClass)->flushCache();
    }
}
