<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use Closure;
use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\CacheTags;
use Illuminate\Cache\TaggableStore;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

trait Caching
{
    protected static array $cacheConnectionExceptions = [
        \RedisException::class,
        'Predis\\Connection\\ConnectionException',
    ];

    protected $isCachable = true;
    protected $scopesAreApplied = false;
    protected $macroKey = "";
    protected $withoutGlobalScopes = [];
    protected $withoutAllGlobalScopes = false;

    public function __call($method, $parameters)
    {
        // Inner builder takes precedence over parent::__call when present.
        // This means if both the inner builder and a scope define the same
        // method name, the inner builder wins. This is intentional: custom
        // builder methods should not be silently overridden by scopes.
        if (
            property_exists($this, 'innerBuilder')
            && $this->innerBuilder
            && method_exists($this->innerBuilder, $method)
        ) {
            // Track the method call in macroKey for cache key differentiation,
            // matching the same treatment applied to local/global macros below.
            $this->macroKey .= "-{$method}";

            if ($parameters) {
                $this->macroKey .= implode("_", $parameters);
            }

            $result = $this->innerBuilder->{$method}(...$parameters);

            return $result === $this->innerBuilder
                ? $this
                : $result;
        }

        $result = parent::__call($method, $parameters);

        if (
            isset($this->localMacros[$method])
            || (method_exists(static::class, 'hasGlobalMacro') && static::hasGlobalMacro($method))
        ) {
            $this->macroKey .= "-{$method}";

            if ($parameters) {
                $this->macroKey .= implode("_", $parameters);
            }
        }

        return $result;
    }

    public function applyScopes()
    {
        if ($this->scopesAreApplied) {
            return $this;
        }

        return parent::applyScopes();
    }

    protected function applyScopesToInstance()
    {
        if (
            ! property_exists($this, "scopes")
            || $this->scopesAreApplied
            || $this->withoutAllGlobalScopes
        ) {
            return;
        }

        foreach ($this->scopes as $identifier => $scope) {
            if (
                ! isset($this->scopes[$identifier])
                || isset($this->withoutGlobalScopes[$identifier])
            ) {
                continue;
            }

            $this->callScope(function () use ($scope) {
                if ($scope instanceof Closure) {
                    $scope($this);
                }

                if (
                    $scope instanceof Scope
                    && $this instanceof CachedBuilder
                ) {
                    $scope->apply($this, $this->getModel());
                }
            });
        }

        $this->scopesAreApplied = true;
    }

    public function cache(array $tags = [])
    {
        $cache = Container::getInstance()
            ->make("cache");
        $config = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.store");

        if ($config) {
            $cache = $cache->store($config);
        }

        if (is_subclass_of($cache->getStore(), TaggableStore::class)) {
            $cache = $cache->tags($tags);
        }

        return $cache;
    }

    public function disableModelCaching()
    {
        $this->isCachable = false;

        return $this;
    }

    public function flushCache(array $tags = [])
    {
        $this->withCacheFallback(function () use ($tags) {
            if (count($tags) === 0) {
                $tags = $this->makeCacheTags();
            }

            $this->modelCacheRepository()->invalidateTags($tags);

            [$cacheCooldown] = $this->getModelCacheCooldown($this);

            if ($cacheCooldown) {
                $cachePrefix = $this->getCachePrefix();
                $modelClassName = get_class($this);
                $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:saved-at";

                $this->cache()
                    ->rememberForever($cacheKey, function () {
                        return (new Carbon)->now();
                    });
            }
        }, 'cache flush failed');
    }

    protected function getCachePrefix() : string
    {
        $cachePrefix = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.cache-prefix", "");

        if (
            $this->model
            && property_exists($this->model, "cachePrefix")
        ) {
            $cachePrefix = $this->model->cachePrefix;
        }

        $cachePrefix = $cachePrefix
            ? "{$cachePrefix}:"
            : "";

        return "genealabs:laravel-model-caching:"
            . $cachePrefix;
    }

    protected function makeCacheKey(
        array $columns = ['*'],
        $idColumn = null,
        string $keyDifferentiator = ''
    ) : string {
        $this->applyScopesToInstance();
        $eagerLoad = $this->eagerLoad
            ?? [];
        $model = $this;

        if (property_exists($this, "model")) {
            $model = $this->model;
        }

        if (method_exists($this, "getModel")) {
            $model = $this->getModel();
        }

        $query = $this->query
            ?? Container::getInstance()
                ->make("db")
                ->query();

        if (
            $this->query
            && method_exists($this->query, "getQuery")
        ) {
            $query = $this->query->getQuery();
        }

        return (new CacheKey($eagerLoad, $model, $query, $this->macroKey, $this->withoutGlobalScopes, $this->withoutAllGlobalScopes))
            ->make($columns, $idColumn, $keyDifferentiator);
    }

    protected function makeCacheTags() : array
    {
        $eagerLoad = $this->eagerLoad ?? [];
        $model = $this->getModel() instanceof Model
            ? $this->getModel()
            : $this;
        $query = $this->query instanceof Builder
            ? $this->query
            : Container::getInstance()
                ->make("db")
                ->query();
        $tags = (new CacheTags($eagerLoad, $model, $query))
            ->make();

        return $tags;
    }

    protected function getModelCacheCooldown(Model $instance) : array
    {
        if (! $instance->cacheCooldownSeconds) {
            return [null, null, null];
        }

        $cachePrefix = $this->getCachePrefix();
        $modelClassName = get_class($instance);
        [$cacheCooldown, $invalidatedAt, $savedAt] = $this
            ->getCacheCooldownDetails($instance, $cachePrefix, $modelClassName);

        if (! $cacheCooldown || $cacheCooldown === 0) {
            return [null, null, null];
        }

        return [$cacheCooldown, $invalidatedAt, $savedAt];
    }

    protected function getCacheCooldownDetails(
        Model $instance,
        string $cachePrefix,
        string $modelClassName
    ) : array {
        return [
            $instance
                ->cache()
                ->get("{$cachePrefix}:{$modelClassName}-cooldown:seconds"),
            $instance
                ->cache()
                ->get("{$cachePrefix}:{$modelClassName}-cooldown:invalidated-at"),
            $instance
                ->cache()
                ->get("{$cachePrefix}:{$modelClassName}-cooldown:saved-at"),
        ];
    }

    protected function checkCooldownAndRemoveIfExpired(Model $instance)
    {
        $this->withCacheFallback(function () use ($instance) {
            [$cacheCooldown, $invalidatedAt] = $this->getModelCacheCooldown($instance);

            if (
                ! $cacheCooldown
                || (new Carbon)->now()->diffInSeconds($invalidatedAt, true) < $cacheCooldown
            ) {
                return;
            }

            $cachePrefix = $this->getCachePrefix();
            $modelClassName = get_class($instance);

            $instance
                ->cache()
                ->forget("{$cachePrefix}:{$modelClassName}-cooldown:seconds");
            $instance
                ->cache()
                ->forget("{$cachePrefix}:{$modelClassName}-cooldown:invalidated-at");
            $instance
                ->cache()
                ->forget("{$cachePrefix}:{$modelClassName}-cooldown:saved-at");
            $instance->flushCache();
        }, 'cache cooldown check failed');
    }

    protected function checkCooldownAndFlushAfterPersisting(Model $instance, string $relationship = "")
    {
        if (! $this->isCachable()) {
            return;
        }

        $this->withCacheFallback(function () use ($instance, $relationship) {
            [$cacheCooldown, $invalidatedAt] = $this->getModelCacheCooldown($instance);

            if (! $cacheCooldown) {
                $instance->flushCache();
                $this->flushMorphToRelatedCaches($instance);
                $this->flushRelationshipCache($instance, $relationship);

                return;
            }

            $this->setCacheCooldownSavedAtTimestamp($instance);

            if ((new Carbon)->now()->diffInSeconds($invalidatedAt, true) >= $cacheCooldown) {
                $instance->flushCache();
                $this->flushMorphToRelatedCaches($instance);
                $this->flushRelationshipCache($instance, $relationship);
            }
        }, 'cache flush after persisting failed');
    }

    protected static $morphToMethodCache = [];

    protected function flushMorphToRelatedCaches(Model $instance): void
    {
        $className = get_class($instance);

        if (! isset(static::$morphToMethodCache[$className])) {
            static::$morphToMethodCache[$className] = [];
            $reflection = new ReflectionClass($instance);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $className
                    || $method->getNumberOfParameters() > 0
                ) {
                    continue;
                }

                $returnType = $method->getReturnType();

                if (! $returnType
                    || ! $returnType instanceof ReflectionNamedType
                    || $returnType->getName() !== MorphTo::class
                ) {
                    continue;
                }

                static::$morphToMethodCache[$className][] = $method->getName();
            }
        }

        foreach (static::$morphToMethodCache[$className] as $morphToName) {
            $relation = $instance->{$morphToName}();
            $typeColumn = $relation->getMorphType();
            $idColumn = $relation->getForeignKeyName();
            $parentType = $instance->getAttribute($typeColumn);
            $parentId = $instance->getAttribute($idColumn);

            if (! $parentType || ! $parentId) {
                continue;
            }

            $parentClass = Relation::getMorphedModel($parentType) ?? $parentType;
            $parentModel = new $parentClass;

            if (method_exists($parentModel, 'flushCache')) {
                $parentModel->flushCache();
            }
        }
    }

    protected function flushRelationshipCache(Model $instance, string $relationship = ""): void
    {
        if (! $relationship) {
            return;
        }

        $relationObject = $instance->$relationship();
        $relationshipInstance = $relationObject->getModel();

        if (method_exists($relationshipInstance, "flushCache")) {
            $relationshipInstance->flushCache();
        } else {
            // Related model is not cacheable — build the cache tag for the
            // related model and flush it so stale relationship results are
            // cleared. This covers BelongsToMany with custom pivot models
            // where only the parent model uses the Cachable trait.
            $tags = (new CacheTags(
                [],
                $relationshipInstance,
                Container::getInstance()->make("db")->query()
            ))->make();

            $instance->modelCacheRepository()->invalidateTags($tags);
        }
    }

    protected function modelCacheRepository(): ModelCacheRepository
    {
        return ModelCacheRepository::make();
    }

    protected function rememberModelCacheForever(
        string $key,
        array $tags,
        callable $callback,
        bool $hash = false
    ): mixed {
        return $this->modelCacheRepository()->rememberForever($key, $tags, $callback, $hash);
    }

    protected function getModelCacheValue(string $key, array $tags = [], bool $hash = false): mixed
    {
        return $this->modelCacheRepository()->get($key, $tags, $hash);
    }

    protected function putModelCacheValue(
        string $key,
        mixed $value,
        array $tags = [],
        bool $hash = false
    ): bool {
        return $this->modelCacheRepository()->forever($key, $value, $tags, $hash);
    }

    protected function forgetModelCacheValue(string $key, array $tags = [], bool $hash = false): bool
    {
        return $this->modelCacheRepository()->forget($key, $tags, $hash);
    }

    public function isCachable() : bool
    {
        $isCacheDisabled = ! Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.enabled");

        if ($isCacheDisabled) {
            return false;
        }

        $allRelationshipsAreCachable = true;

        if (
            property_exists($this, "eagerLoad")
            && $this->eagerLoad
        ) {
            $allRelationshipsAreCachable = collect($this
                ->eagerLoad)
                ->keys()
                ->reduce(function ($carry, $related) {
                    if (
                        ! method_exists($this->model, $related)
                        || $carry === false
                    ) {
                        return $carry;
                    }

                    $relatedModel = $this->model->$related()->getRelated();

                    if (
                        ! method_exists($relatedModel, "isCachable")
                        || ! $relatedModel->isCachable()
                    ) {
                        return false;
                    }

                    return true;
                })
                ?? true;
        }

        $hasLock = false;

        if (property_exists($this, 'query') && $this->query) {
            $baseQuery = $this->query;

            if ($baseQuery instanceof \Illuminate\Database\Eloquent\Builder) {
                $baseQuery = $baseQuery->getQuery();
            }

            $hasLock = $baseQuery->lock !== null;
        }

        return $this->isCachable
            && $allRelationshipsAreCachable
            && ! $hasLock;
    }

    public function shouldFallbackToDatabase() : bool
    {
        return Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.fallback-to-database", false);
    }

    public function isCacheConnectionException(\Throwable $exception) : bool
    {
        foreach (static::$cacheConnectionExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        if (
            class_exists('\Aws\DynamoDb\Exception\DynamoDbException')
            && $exception instanceof \Aws\DynamoDb\Exception\DynamoDbException
        ) {
            return $exception->isConnectionError();
        }

        return false;
    }

    public function withCacheFallback(callable $operation, string $context, ?callable $fallback = null)
    {
        static $inFallback = false;

        try {
            return $operation();
        } catch (\Throwable $exception) {
            if (! $this->shouldFallbackToDatabase() || ! $this->isCacheConnectionException($exception)) {
                throw $exception;
            }

            if (! $inFallback) {
                Log::warning("laravel-model-caching: {$context} — {$exception->getMessage()}");
            }

            $inFallback = true;

            try {
                return $fallback ? $fallback() : null;
            } finally {
                $inFallback = false;
            }
        }
    }

    protected function setCacheCooldownSavedAtTimestamp(Model $instance)
    {
        $this->withCacheFallback(function () use ($instance) {
            $cachePrefix = $this->getCachePrefix();
            $modelClassName = get_class($instance);
            $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:saved-at";

            $instance->cache()
                ->rememberForever($cacheKey, function () {
                    return (new Carbon)->now();
                });
        }, 'cache cooldown timestamp write failed');
    }
}
