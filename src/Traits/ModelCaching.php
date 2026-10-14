<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\CachedHasManyThrough;
use GeneaLabs\LaravelModelCaching\CachedHasOneThrough;
use GeneaLabs\LaravelModelCaching\CachedMorphToMany;
use GeneaLabs\LaravelModelCaching\CachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\CacheTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

// phpcs:ignore SlevomatCodingStandard.Classes.ClassLength.ClassTooLong
trait ModelCaching
{
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function newEloquentBuilder($query): Builder
    {
        static $building = [];
        $objectId = spl_object_id($this);

        if ($building[$objectId] ?? false) {
            return new Builder($query);
        }

        $building[$objectId] = true;

        try {
            return $this->newModelCachingEloquentBuilder($query);
        } finally {
            unset($building[$objectId]);
        }
    }

    protected function newBaseQueryBuilder(): QueryBuilder
    {
        $builder = parent::newBaseQueryBuilder();

        if ($builder::class !== QueryBuilder::class) {
            return $builder;
        }

        return new CachedQueryBuilder(
            $builder->getConnection(),
            $builder->getGrammar(),
            $builder->getProcessor(),
        );
    }

    public function __get($key): mixed
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

    public function __set($key, $value): void
    {
        if ($key === "cachePrefix") {
            $this->cachePrefix = $value;
        }

        if ($key === "cacheCooldownSeconds") {
            $this->cacheCooldownSeconds = $value;
        }

        parent::__set($key, $value);
    }

    public static function all($columns = ['*']): EloquentCollection
    {
        $class = get_called_class();
        $instance = new $class;

        if (! $instance->isCachable()) {
            return parent::all($columns);
        }

        return $instance->withCacheFallback(
            function () use ($instance, $columns) {
                $cacheTags = new CacheTags(
                    $instance->newQueryWithoutScopes()->getEagerLoads(),
                    $instance,
                    $instance->newModelQuery()->getQuery(),
                );
                $key = $instance->makeCacheKey();

                return $instance->rememberModelCache($key, $cacheTags->make(), $cacheTags->ttl(), function () use ($columns) {
                        return parent::all($columns);
                    });
            },
            'cache read failed, falling back to database',
            function () use ($columns) {
                return parent::all($columns);
            },
        );
    }

    public static function bootCachable(): void
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

    public static function destroy($ids): int
    {
        $result = parent::destroy($ids);

        if ($result) {
            $class = get_called_class();
            $instance = new $class;

            $instance->checkCooldownAndFlushAfterPersisting($instance);
        }

        return $result;
    }

    public function newModelCachingEloquentBuilder($query): Builder
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
        $secondLocalKey,
    ): HasManyThrough {
        if ($this->isThroughRelationCachable($query, $farParent)) {
            return new CachedHasManyThrough(
                $query,
                $farParent,
                $throughParent,
                $firstKey,
                $secondKey,
                $localKey,
                $secondLocalKey,
            );
        }

        return parent::newHasManyThrough(
            $query,
            $farParent,
            $throughParent,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey,
        );
    }

    protected function newHasOneThrough(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        $firstKey,
        $secondKey,
        $localKey,
        $secondLocalKey,
    ): HasOneThrough {
        if ($this->isThroughRelationCachable($query, $farParent)) {
            return new CachedHasOneThrough(
                $query,
                $farParent,
                $throughParent,
                $firstKey,
                $secondKey,
                $localKey,
                $secondLocalKey,
            );
        }

        return parent::newHasOneThrough(
            $query,
            $farParent,
            $throughParent,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey,
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
    ): BelongsToMany {
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
    ): MorphToMany {
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
