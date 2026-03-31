<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\CachedHasManyThrough;
use GeneaLabs\LaravelModelCaching\CachedHasOneThrough;
use GeneaLabs\LaravelModelCaching\CachedMorphToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

// phpcs:ignore SlevomatCodingStandard.Classes.ClassLength.ClassTooLong
trait ModelCaching
{
    public function __get($key)
    {
        if ($key === "cachePrefix") {
            return $this->cachePrefix
                ?? "";
        }

        if ($key === "cacheCooldownSeconds") {
            return $this->cacheCooldownSeconds
                ?? 0;
        }

        if ($key === "query") {
            return $this->query
                ?? $this->newModelQuery();
        }

        return parent::__get($key);
    }

    public function __set($key, $value)
    {
        if ($key === "cachePrefix") {
            $this->cachePrefix = $value;
        }

        if ($key === "cacheCooldownSeconds") {
            $this->cacheCooldownSeconds = $value;
        }

        parent::__set($key, $value);
    }

    public static function all($columns = ['*'])
    {
        $class = get_called_class();
        $instance = new $class;

        if (! $instance->isCachable()) {
            return parent::all($columns);
        }

        return $instance->withCacheFallback(
            function () use ($instance, $columns) {
                $tags = $instance->makeCacheTags();
                $key = $instance->makeCacheKey();

                return $instance->rememberModelCacheForever($key, $tags, function () use ($columns) {
                        return parent::all($columns);
                    });
            },
            'cache read failed, falling back to database',
            function () use ($columns) {
                return parent::all($columns);
            }
        );
    }

    public static function bootCachable()
    {
        static::created(function ($instance) {
            $instance->checkCooldownAndFlushAfterPersisting($instance);
        });

        static::deleted(function ($instance) {
            $instance->checkCooldownAndFlushAfterPersisting($instance);
        });

        static::saved(function ($instance) {
            $instance->checkCooldownAndFlushAfterPersisting($instance);
        });

        // TODO: figure out how to add this listener
        // static::restored(function ($instance) {
        //     $instance->checkCooldownAndFlushAfterPersisting($instance);
        // });

        static::pivotSynced(function ($instance, $relationship) {
            $instance->checkCooldownAndFlushAfterPersisting($instance, $relationship);
        });

        static::pivotAttached(function ($instance, $relationship) {
            $instance->checkCooldownAndFlushAfterPersisting($instance, $relationship);
        });

        static::pivotDetached(function ($instance, $relationship) {
            $instance->checkCooldownAndFlushAfterPersisting($instance, $relationship);
        });

        static::pivotUpdated(function ($instance, $relationship) {
            $instance->checkCooldownAndFlushAfterPersisting($instance, $relationship);
        });
    }

    public static function destroy($ids)
    {
        $result = parent::destroy($ids);

        if ($result) {
            $class = get_called_class();
            $instance = new $class;

            $instance->withCacheFallback(function () use ($instance) {
                $instance->flushCache();
            }, 'cache flush failed during destroy');
        }

        return $result;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * When caching is disabled the model's custom builder (if any) is returned
     * as-is.  When caching is enabled the method delegates to
     * {@see newModelCachingEloquentBuilder()} so that custom-builder support and
     * caching are composed correctly.
     *
     * **Trait collision (AC6 / #535):** If another trait used on your model also
     * defines `newEloquentBuilder` you will encounter a PHP fatal "collision"
     * error.  Resolve it by explicitly overriding the method on the model class
     * and calling `newModelCachingEloquentBuilder()`:
     *
     * ```php
     * use Cachable, NodeTrait {
     *     Cachable::newEloquentBuilder insteadof NodeTrait;
     * }
     * ```
     *
     * Or, if you need *both* trait builders composed:
     *
     * ```php
     * use Cachable, NodeTrait {
     *     Cachable::newEloquentBuilder as newCachableEloquentBuilder;
     *     NodeTrait::newEloquentBuilder  as newNodeTraitEloquentBuilder;
     * }
     *
     * public function newEloquentBuilder($query)
     * {
     *     return $this->newModelCachingEloquentBuilder($query);
     * }
     * ```
     */
    public function newEloquentBuilder($query)
    {
        return $this->newModelCachingEloquentBuilder($query);
    }

    /**
     * Core implementation for building a caching-aware Eloquent builder.
     *
     * Extracted from {@see newEloquentBuilder()} so that it can be called
     * directly from model classes that need to resolve a trait collision by
     * overriding `newEloquentBuilder` themselves (AC6).
     *
     * Behaviour:
     * - Caching disabled → delegate to parent (custom builder returned as-is, AC1).
     * - Caching enabled + custom builder already extends CachedBuilder → return it
     *   directly so both custom query methods and caching are preserved (AC2).
     * - Caching enabled + custom builder does NOT extend CachedBuilder → wrap it
     *   inside a CachedBuilder via composition; the wrapper's `__call` proxy
     *   delegates unknown method calls to the inner builder so custom methods
     *   remain callable at runtime (AC3).
     * - No custom builder → plain CachedBuilder (existing behaviour).
     *
     * **Larastan / PHPStan (AC5):** When a custom builder is wrapped rather than
     * returned directly, static analysis tools cannot infer the custom methods
     * from the `CachedBuilder` return type.  Add a `@return CustomBuilder`
     * override annotation on your model's `newQuery()` (or `query()`) call-site,
     * or use the `@mixin` approach described in the package README to suppress
     * false-positive "undefined method" errors at level 5+.
     */
    public function newModelCachingEloquentBuilder($query)
    {
        if (! $this->isCachable()) {
            $this->isCachable = false;

            return parent::newEloquentBuilder($query);
        }

        $customBuilder = parent::newEloquentBuilder($query);

        if ($customBuilder instanceof CachedBuilder) {
            return $customBuilder;
        }

        if ($customBuilder::class !== Builder::class) {
            return (new CachedBuilder($query))->setInnerBuilder($customBuilder);
        }

        return new CachedBuilder($query);
    }

    protected function isThroughRelationCachable(Builder $query, Model $farParent): bool
    {
        $relatedIsCachable = method_exists($query->getModel(), 'isCachable')
            && $query->getModel()->isCachable();
        $parentIsCachable = method_exists($farParent, 'isCachable')
            && $farParent->isCachable();

        return $relatedIsCachable || $parentIsCachable;
    }

    protected function newHasManyThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey
    ) {
        if ($this->isThroughRelationCachable($query, $farParent)) {
            return new CachedHasManyThrough(
                $query,
                $farParent,
                $throughParent,
                $firstKey,
                $secondKey,
                $localKey,
                $secondLocalKey
            );
        }

        return parent::newHasManyThrough(
            $query,
            $farParent,
            $throughParent,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    protected function newHasOneThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey
    ) {
        if ($this->isThroughRelationCachable($query, $farParent)) {
            return new CachedHasOneThrough(
                $query,
                $farParent,
                $throughParent,
                $firstKey,
                $secondKey,
                $localKey,
                $secondLocalKey
            );
        }

        return parent::newHasOneThrough(
            $query,
            $farParent,
            $throughParent,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ) {
        $relatedIsCachable = method_exists($query->getModel(), "isCachable")
            && $query->getModel()->isCachable();
        $parentIsCachable = method_exists($parent, "isCachable")
            && $parent->isCachable();

        if ($relatedIsCachable || $parentIsCachable) {
            return new CachedBelongsToMany(
                $query,
                $parent,
                $table,
                $foreignPivotKey,
                $relatedPivotKey,
                $parentKey,
                $relatedKey,
                $relationName,
            );
        }

        return new BelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
        );
    }

    protected function newMorphToMany(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false,
    ) {
        $relatedIsCachable = method_exists($query->getModel(), "isCachable")
            && $query->getModel()->isCachable();
        $parentIsCachable = method_exists($parent, "isCachable")
            && $parent->isCachable();

        if ($relatedIsCachable || $parentIsCachable) {
            return new CachedMorphToMany(
                $query,
                $parent,
                $name,
                $table,
                $foreignPivotKey,
                $relatedPivotKey,
                $parentKey,
                $relatedKey,
                $relationName,
                $inverse,
            );
        }

        return new MorphToMany(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse,
        );
    }

    public function scopeDisableCache(Builder $query): Builder
    {
        if (
            $query instanceof CachedBuilder
            && $this->isCachable()
        ) {
            $query = $query->disableModelCaching();
        }

        return $query;
    }

    public function scopeWithCacheCooldownSeconds(
        Builder $query,
        ?int $seconds = null,
    ): Builder {
        if (! ($query instanceof CachedBuilder)) {
            return $query;
        }

        if (! $seconds) {
            $seconds = $this->cacheCooldownSeconds;
        }

        $this->withCacheFallback(function () use ($seconds) {
            $cachePrefix = $this->getCachePrefix();
            $modelClassName = get_class($this);
            $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:seconds";

            $this->cache()
                ->rememberForever($cacheKey, function () use ($seconds) {
                    return $seconds;
                });

            $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:invalidated-at";
            $this->cache()
                ->rememberForever($cacheKey, function () {
                    return (new Carbon)->now();
                });
        }, 'cache cooldown write failed');

        return $query;
    }
}
