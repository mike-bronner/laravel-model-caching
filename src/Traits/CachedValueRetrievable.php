<?php

namespace GeneaLabs\LaravelModelCaching\Traits;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait CachedValueRetrievable
{
    public function cachedValue(array $arguments, string $cacheKey)
    {
        $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $cacheTags = $this->makeCacheTags();

        return $this->withCacheFallback(
            function () use ($arguments, $cacheKey, $cacheTags, $method) {
                $result = $this->retrieveCachedValue(
                    $arguments,
                    $cacheKey,
                    $cacheTags,
                    $method,
                );

                return $this->preventHashCollision(
                    $result,
                    $arguments,
                    $cacheKey,
                    $cacheTags,
                    $method,
                );
            },
            'cache read failed, falling back to database',
            function () use ($method, $arguments) {
                return parent::{$method}(...$arguments);
            },
        );
    }

    protected function preventHashCollision(
        array $result,
        array $arguments,
        string $cacheKey,
        array $cacheTags,
        string $method,
    ) {
        if ($result["key"] === $cacheKey) {
            return $result["value"];
        }

        $this->forgetModelCacheValue($cacheKey, $cacheTags, true);

        $freshResult = $this->retrieveCachedValue(
            $arguments,
            $cacheKey,
            $cacheTags,
            $method,
        );

        return $freshResult['value'];
    }

    protected function retrieveCachedValue(
        array $arguments,
        string $cacheKey,
        array $cacheTags,
        string $method,
    ) {
        if (property_exists($this, "model")) {
            $this->checkCooldownAndRemoveIfExpired($this->model);
        }

        if (method_exists($this, "getModel")) {
            $this->checkCooldownAndRemoveIfExpired($this->getModel());
        }

        $cachedResult = $this->getModelCacheValue($cacheKey, $cacheTags, true);

        if ($cachedResult !== null) {
            $this->fireRetrievedEvents($cachedResult["value"] ?? null);

            return $cachedResult;
        }

        $result = [
            "key" => $cacheKey,
            "value" => parent::{$method}(...$arguments),
        ];

        $this->putModelCacheValue($cacheKey, $result, $cacheTags, true);

        return $result;
    }

    protected function fireRetrievedEvents($value): void
    {
        if ($value instanceof Model) {
            $this->fireRetrievedEventOnModel($value);

            return;
        }

        $models = null;

        if ($value instanceof \Illuminate\Database\Eloquent\Collection) {
            $models = $value;
        } elseif ($value instanceof Paginator) {
            $models = $value->getCollection();
        } elseif ($value instanceof Collection) {
            $models = $value->filter(function ($item) {
                return $item instanceof Model;
            });
        }

        if ($models) {
            $models->each(function ($model) {
                if ($model instanceof Model) {
                    $this->fireRetrievedEventOnModel($model);
                }
            });
        }
    }

    protected function fireRetrievedEventOnModel(Model $model): void
    {
        $dispatcher = $model::getEventDispatcher();

        if ($dispatcher) {
            $dispatcher->dispatch(
                "eloquent.retrieved: " . get_class($model),
                $model,
            );
        }
    }
}
