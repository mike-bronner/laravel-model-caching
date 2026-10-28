<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait CachedValueRetrievable
{
    public function cachedValue(array $arguments, string $cacheKey): mixed
    {
        $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        return match ($this->isCachable) {
            true => $this->storedValue($method, $arguments, $cacheKey),
            false => parent::{$method}(...$arguments),
        };
    }

    protected function storedValue(string $method, array $arguments, string $cacheKey): mixed
    {
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
    ): mixed {
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
    ): array {
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

    protected function reloadScopedEagerLoads(EloquentBuilder $builder, mixed $value): void
    {
        $models = $this->modelsInCachedValue($value);

        if ($models->isEmpty()) {
            return;
        }

        $this->reloadScopedRelations($builder, $models);
    }

    protected function modelsInCachedValue(mixed $value): EloquentCollection
    {
        $items = match (true) {
            $value instanceof Model => [$value],
            $value instanceof Paginator,
            $value instanceof CursorPaginator => $value->items(),
            $value instanceof Collection => $value->all(),
            default => [],
        };

        return new EloquentCollection(collect($items)
            ->filter(fn ($item) => $item instanceof Model)
            ->values()
            ->all());
    }

    protected function reloadScopedRelations(EloquentBuilder $builder, EloquentCollection $models): void
    {
        $eagerLoads = $builder->getEagerLoads();

        collect($eagerLoads)
            ->keys()
            ->reject(fn (string $name) => str_contains($name, "."))
            ->each(function (string $name) use ($builder, $eagerLoads, $models): void {
                $relation = $builder->getRelation($name);

                if ($this->relationHasGlobalScopes($relation, $models)) {
                    $this->reloadRelation($relation, $name, data_get($eagerLoads, $name), $models);

                    return;
                }

                $nestedEagerLoads = $this->eagerLoadsNestedUnder($eagerLoads, $name);
                $this->reloadScopedRelationsOfRelated($models, $name, $nestedEagerLoads);
            });
    }

    protected function relationHasGlobalScopes(Relation $relation, EloquentCollection $models): bool
    {
        if ($relation instanceof MorphTo) {
            return $models->pluck($relation->getMorphType())
                ->map(fn ($type) => $type instanceof BackedEnum ? $type->value : $type)
                ->filter()
                ->unique()
                ->map(fn (string $type) => $relation->createModelByType($type))
                ->contains(fn (Model $related) => (bool) $related->getGlobalScopes());
        }

        return (bool) $relation->getRelated()
            ->getGlobalScopes();
    }

    protected function reloadScopedRelationsOfRelated(
        EloquentCollection $models,
        string $name,
        array $nestedEagerLoads,
    ): void {
        if (! $nestedEagerLoads) {
            return;
        }

        $models->filter(fn (Model $model) => $model->relationLoaded($name))
            ->flatMap(fn (Model $model) => Collection::wrap($model->getRelation($name))->all())
            ->filter(fn ($related) => $related instanceof Model)
            ->groupBy(fn (Model $related) => get_class($related))
            ->each(function (Collection $related) use ($nestedEagerLoads): void {
                $builder = $related->first()
                    ->newQueryWithoutRelationships()
                    ->setEagerLoads($nestedEagerLoads);

                $this->reloadScopedRelations($builder, new EloquentCollection($related->all()));
            });
    }

    protected function reloadRelation(
        Relation $relation,
        string $name,
        Closure $constraint,
        EloquentCollection $models,
    ): void {
        $relation->addEagerConstraints($models->all());
        $constraint($relation);

        $relation->match(
            $relation->initRelation($models->all(), $name),
            $relation->getEager(),
            $name,
        );
    }

    protected function eagerLoadsNestedUnder(array $eagerLoads, string $name): array
    {
        $nestedNames = collect($eagerLoads)
            ->keys()
            ->filter(fn (string $key) => str_starts_with($key, "{$name}."));

        return collect($eagerLoads)
            ->only($nestedNames)
            ->mapWithKeys(fn (Closure $constraint, string $key) => [Str::after($key, "{$name}.") => $constraint])
            ->all();
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
