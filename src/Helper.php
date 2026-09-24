<?php

namespace GeneaLabs\LaravelModelCaching;

use Illuminate\Container\Container;
use InvalidArgumentException;

class Helper
{
    public function runDisabled(callable $closure)
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

    /**
     * Invalidate the cache for one or more model classes.
     *
     * Works equivalently to `php artisan modelCache:clear --model=`.
     * Does not require booting the Artisan kernel.
     *
     * @param  string|array  $modelClasses  A single model FQCN or an array of model FQCNs.
     * @return void
     *
     * @throws \InvalidArgumentException If a class does not use the Cachable trait.
     */
    public function invalidate($modelClasses): void
    {
        $modelClasses = is_array($modelClasses) ? $modelClasses : [$modelClasses];

        foreach ($modelClasses as $modelClass) {
            $this->invalidateModel($modelClass);
        }
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
