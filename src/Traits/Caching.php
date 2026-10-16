<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use Aws\DynamoDb\Exception\DynamoDbException;
use BackedEnum;
use Closure;
use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\CachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelModelCaching\Exceptions\UnkeyableValueException;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use JsonException;
use RedisException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Stringable;
use Throwable;
use UnitEnum;
use WeakMap;

trait Caching
{
    protected static array $cacheConnectionExceptions = [
        RedisException::class,
        'Predis\\Connection\\ConnectionException',
    ];
    protected static ?WeakMap $modelCacheRepositories = null;
    protected bool $isCachable = true;
    protected $scopesAreApplied = false;
    protected $macroKey = "";
    protected bool $hasUnkeyableMacroArgument = false;
    protected $withoutGlobalScopes = [];
    protected $withoutAllGlobalScopes = false;

    public function __call($method, $parameters): mixed
    {
        $parameters = $this->recordSubqueryArguments($method, $parameters);

        if (
            property_exists($this, 'innerBuilder')
            && $this->innerBuilder
            && method_exists($this->innerBuilder, $method)
        ) {
            $this->appendMacroKey($method, $parameters);

            $result = $this->innerBuilder->{$method}(...$parameters);
            $this->flushCacheAfterForwardedWrite($method);

            return $result === $this->innerBuilder
                ? $this
                : $result;
        }

        $result = parent::__call($method, $parameters);

        if (
            isset($this->localMacros[$method])
            || (method_exists(static::class, 'hasGlobalMacro') && static::hasGlobalMacro($method))
        ) {
            $this->appendMacroKey($method, $parameters);
        }

        $this->flushCacheAfterForwardedWrite($method);

        return $result;
    }

    protected function recordSubqueryArguments(string $method, array $parameters): array
    {
        if (
            ! $this instanceof Builder
            || $this->getQuery() instanceof CachedQueryBuilder
        ) {
            return $parameters;
        }

        $recorder = CachedQueryBuilder::recorderFor($this->getQuery());

        if (
            property_exists($this, "innerBuilder")
            && $this->innerBuilder
        ) {
            $recorder->recordSubqueryCallsOf($this->innerBuilder);
        }

        return $recorder->recordSubqueryCall($method, $parameters);
    }

    protected function appendMacroKey(string $method, array $parameters): void
    {
        $this->macroKey .= "-{$method}";

        if (! $parameters) {
            return;
        }

        try {
            $this->macroKey .= $this->macroKeyArguments($parameters);
        } catch (UnkeyableValueException | JsonException) {
            $this->hasUnkeyableMacroArgument = true;
        }
    }

    protected function macroKeyArguments(array $parameters): string
    {
        $arguments = collect($parameters)
            ->map(function ($parameter) {
                if (is_array($parameter)) {
                    return $this->keyableMacroArray($parameter);
                }

                if ($parameter instanceof BackedEnum) {
                    return $parameter->value;
                }

                if ($parameter instanceof UnitEnum) {
                    return $parameter->name;
                }

                if (
                    $parameter instanceof QueryBuilder
                    || $parameter instanceof Builder
                    || $parameter instanceof Relation
                ) {
                    return $this->macroBuilderArgumentKey($parameter);
                }

                throw_unless(
                    $parameter === null
                        || is_scalar($parameter)
                        || $parameter instanceof Stringable,
                    UnkeyableValueException::class,
                );

                return $parameter instanceof Stringable
                    ? (string) $parameter
                    : $parameter;
            })
            ->all();

        return json_encode($arguments, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, 1024);
    }

    protected function macroBuilderArgumentKey(QueryBuilder|Builder|Relation $builder): string
    {
        if ($builder instanceof Relation) {
            $builder = $builder->getQuery();
        }

        $key = $builder instanceof Builder
            ? new CacheKey($builder->getEagerLoads(), $builder->getModel(), $builder->toBase(), $this->builderArgumentMacroKey($builder), [], false)
            : new CacheKey([], $this->cacheModel(), $builder, "", [], false);

        return "builder:" . sha1($key->make());
    }

    protected function builderArgumentMacroKey(Builder $builder): string
    {
        return (fn () => method_exists($this, "keyableMacroKey") ? $this->keyableMacroKey() : "")
            ->call($builder);
    }

    protected function keyableMacroArray(array $values, int $depth = 1): array
    {
        throw_if($depth > 512, UnkeyableValueException::class);

        return array_map(fn ($value) => match (true) {
            is_array($value) => $this->keyableMacroArray($value, $depth + 1),
            $value instanceof Stringable && CacheKey::hidesStateFromJson($value) => (string) $value,
            is_object($value) && ! $value instanceof Stringable && ! $value instanceof UnitEnum => throw new UnkeyableValueException,
            default => $value,
        }, $values);
    }

    protected function keyableMacroKey(): string
    {
        throw_if($this->hasUnkeyableMacroArgument, UnkeyableValueException::class);

        return $this->macroKey;
    }

    protected function flushCacheAfterForwardedWrite(string $method): void
    {
        $forwardedWrites = [
            "insertgetid",
            "insertorignore",
            "insertorignorereturning",
            "insertorignoreusing",
            "insertusing",
            "updatefrom",
            "updateorinsert",
        ];

        if (
            ! $this instanceof Builder
            || ! in_array(strtolower($method), $forwardedWrites, true)
        ) {
            return;
        }

        $this->flushCacheAfterBuilderWrite($method);
    }

    protected function flushCacheAfterBuilderWrite(string $operation): void
    {
        $isEnabled = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.enabled");

        if (! $isEnabled) {
            return;
        }

        $this->withCacheFallback(function () {
            $model = $this->cacheModel();

            if ($model && ! $this->cacheCooldownAllowsFlush($model)) {
                return;
            }

            $this->modelCacheRepository()->invalidateTags($this->makeCacheTags());

            if ($model) {
                $this->flushMorphToRelatedCaches($model);
            }
        }, "cache flush failed during {$operation}");
    }

    public function applyScopes(): static|Builder
    {
        if ($this->scopesAreApplied) {
            return $this;
        }

        return parent::applyScopes();
    }

    protected function applyScopesToInstance(): void
    {
        if (
            ! property_exists($this, "scopes")
            || $this->scopesAreApplied
            || $this->withoutAllGlobalScopes
        ) {
            return;
        }

        collect($this->scopes)
            ->each(function ($scope, $identifier): void {
                if (
                    ! isset($this->scopes[$identifier])
                    || isset($this->withoutGlobalScopes[$identifier])
                ) {
                    return;
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
            });

        $this->scopesAreApplied = true;
    }

    public function cache(array $tags = []): CacheFactory|CacheRepository
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

    public function disableModelCaching(): static
    {
        $this->isCachable = false;

        return $this;
    }

    public function flushCache(array $tags = []): void
    {
        if (! $this->isCachable()) {
            return;
        }

        $this->withCacheFallback(function () use ($tags) {
            if (count($tags) === 0) {
                $tags = $this->makeCacheTags();
            }

            $this->modelCacheRepository()->invalidateTags($tags);

            $model = $this->cacheModel() ?? $this;
            [$cacheCooldown] = $this->getModelCacheCooldown($model);

            if ($cacheCooldown) {
                $this->setCacheCooldownSavedAtTimestamp($model);
            }
        }, 'cache flush failed');
    }

    protected function cacheModel(): ?Model
    {
        if ($this instanceof Relation) {
            return $this->getRelated();
        }

        if ($this instanceof Model) {
            return $this;
        }

        $model = $this->model ?? null;

        return $model instanceof Model
            ? $model
            : null;
    }

    protected function getCachePrefix(): string
    {
        return $this->cooldownCachePrefix($this->cacheModel());
    }

    protected function cooldownCachePrefix(?Model $model): string
    {
        $cachePrefix = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.cache-prefix", "");

        if (
            $model
            && property_exists($model, "cachePrefix")
        ) {
            $cachePrefix = $model->cachePrefix;
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
        string $keyDifferentiator = '',
    ): string {
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

        $query = $this->cacheKeyQuery();

        try {
            return (new CacheKey($eagerLoad, $model, $query, $this->keyableMacroKey(), $this->withoutGlobalScopes, $this->withoutAllGlobalScopes))
                ->make($columns, $idColumn, $keyDifferentiator);
        } catch (UnkeyableValueException) {
            $this->disableModelCaching();

            return "";
        }
    }

    protected function cacheKeyQuery(): mixed
    {
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

        return $query;
    }

    protected function cacheEagerLoads(): array
    {
        return match (true) {
            $this instanceof Relation => $this->getQuery()->getEagerLoads(),
            default => $this->eagerLoad ?? [],
        };
    }

    protected function eagerLoadingModel(): mixed
    {
        return match (true) {
            $this instanceof Relation => $this->getRelated(),
            property_exists($this, "model") => $this->model,
            default => null,
        };
    }

    protected function makeCacheTags(): array
    {
        $eagerLoad = $this->cacheEagerLoads();
        $model = $this->getModel() instanceof Model
            ? $this->getModel()
            : $this;
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

        $cacheTags = new CacheTags($eagerLoad, $model, $query);
        $tags = $cacheTags->make();

        if ($this instanceof BelongsToMany) {
            $tags = array_values(array_unique(array_merge(
                $tags,
                $cacheTags->getPivotTableTags($this),
            )));
        }

        return $tags;
    }

    protected function getModelCacheCooldown(Model $instance): array
    {
        if (! $instance->cacheCooldownSeconds) {
            return [null, null, null];
        }

        $cachePrefix = $this->cooldownCachePrefix($instance);
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
        string $modelClassName,
    ): array {
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

    protected function checkCooldownAndRemoveIfExpired(Model $instance): void
    {
        $this->withCacheFallback(function () use ($instance) {
            [$cacheCooldown, $invalidatedAt] = $this->getModelCacheCooldown($instance);

            if (
                ! $cacheCooldown
                || (new Carbon)->now()->diffInSeconds($invalidatedAt, true) < $cacheCooldown
            ) {
                return;
            }

            $cachePrefix = $this->cooldownCachePrefix($instance);
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

    protected function checkCooldownAndFlushAfterPersisting(Model $instance, string $relationship = ""): void
    {
        if (! $this->isCachable()) {
            return;
        }

        $this->withCacheFallback(function () use ($instance, $relationship) {
            if (! $this->cacheCooldownAllowsFlush($instance)) {
                return;
            }

            $instance->flushCache();
            $this->flushMorphToRelatedCaches($instance);
            $this->flushRelationshipCache($instance, $relationship);
        }, 'cache flush after persisting failed');
    }

    protected function cacheCooldownAllowsFlush(Model $instance): bool
    {
        [$cacheCooldown, $invalidatedAt] = $this->getModelCacheCooldown($instance);

        if (! $cacheCooldown) {
            return true;
        }

        $this->setCacheCooldownSavedAtTimestamp($instance);

        return (new Carbon)->now()->diffInSeconds($invalidatedAt, true) >= $cacheCooldown;
    }

    protected static $morphToMethodCache = [];

    protected function flushMorphToRelatedCaches(Model $instance): void
    {
        $className = get_class($instance);

        if (! isset(static::$morphToMethodCache[$className])) {
            static::$morphToMethodCache[$className] = collect((new ReflectionClass($instance))
                ->getMethods(ReflectionMethod::IS_PUBLIC))
                ->filter(function (ReflectionMethod $method) use ($className): bool {
                    $returnType = $method->getReturnType();

                    return $method->class === $className
                        && $method->getNumberOfParameters() === 0
                        && $returnType instanceof ReflectionNamedType
                        && $returnType->getName() === MorphTo::class;
                })
                ->map(fn (ReflectionMethod $method): string => $method->getName())
                ->values()
                ->all();
        }

        collect(static::$morphToMethodCache[$className])
            ->each(function (string $morphToName) use ($instance): void {
                $relation = $instance->{$morphToName}();
                $typeColumn = $relation->getMorphType();
                $idColumn = $relation->getForeignKeyName();
                $parentType = $instance->getAttribute($typeColumn);
                $parentId = $instance->getAttribute($idColumn);

                if (! $parentType || ! $parentId) {
                    return;
                }

                if ($parentType instanceof BackedEnum) {
                    $parentType = $parentType->value;
                }

                $parentClass = Relation::getMorphedModel($parentType) ?? $parentType;
                $parentModel = new $parentClass;

                if (method_exists($parentModel, 'flushCache')) {
                    $parentModel->flushCache();
                }
            });
    }

    protected function flushRelationshipCache(Model $instance, string $relationship = ""): void
    {
        if (! $relationship) {
            return;
        }

        $relationObject = $instance->$relationship();
        $relationshipInstance = $relationObject->getModel();

        if (! method_exists($relationshipInstance, "flushCache")) {
            $query = Container::getInstance()
                ->make("db")
                ->query();
            $tags = (new CacheTags([], $relationshipInstance, $query))->make();
            $repository = $instance->modelCacheRepository();
            $repository->invalidateTags($tags);

            return;
        }

        if ($this->cacheCooldownAllowsFlush($relationshipInstance)) {
            $relationshipInstance->flushCache();
        }
    }

    protected function modelCacheRepository(): ModelCacheRepository
    {
        static::$modelCacheRepositories
            ??= new WeakMap;

        return static::$modelCacheRepositories[$this]
            ??= ModelCacheRepository::make();
    }

    protected function modelCacheTtlSeconds(): ?int
    {
        return (new CacheTags($this->cacheEagerLoads(), $this->cacheModel() ?? $this, null))
            ->ttl();
    }

    protected function rememberModelCacheForever(
        string $key,
        array $tags,
        callable $callback,
        bool $hash = false,
    ): mixed {
        return $this->rememberModelCache($key, $tags, $this->modelCacheTtlSeconds(), $callback, $hash);
    }

    protected function rememberModelCache(
        string $key,
        array $tags,
        ?int $ttl,
        callable $callback,
        bool $hash = false,
    ): mixed {
        if ($ttl) {
            return $this->modelCacheRepository()->remember($key, $tags, $ttl, $callback, $hash);
        }

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
        bool $hash = false,
    ): bool {
        $ttl = $this->modelCacheTtlSeconds();

        if ($ttl) {
            $this->modelCacheRepository()->remember($key, $tags, $ttl, fn (): mixed => $value, $hash);

            return true;
        }

        return $this->modelCacheRepository()->forever($key, $value, $tags, $hash);
    }

    protected function forgetModelCacheValue(string $key, array $tags = [], bool $hash = false): bool
    {
        return $this->modelCacheRepository()->forget($key, $tags, $hash);
    }

    public function isCachable(): bool
    {
        $isCacheDisabled = ! Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.enabled");

        if ($isCacheDisabled) {
            return false;
        }

        $allRelationshipsAreCachable = true;

        if ($this->cacheEagerLoads()) {
            $allRelationshipsAreCachable = collect($this->cacheEagerLoads())
                ->keys()
                ->reduce(function ($carry, $related) {
                    if (
                        ! $this->modelHasRelation($this->eagerLoadingModel(), $related)
                        || $carry === false
                    ) {
                        return $carry;
                    }

                    $relatedModel = $this->eagerLoadingModel()->$related()->getRelated();

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

            if ($baseQuery instanceof Builder) {
                $baseQuery = $baseQuery->getQuery();
            }

            $hasLock = $baseQuery->lock !== null;
        }

        return $this->isCachable
            && $allRelationshipsAreCachable
            && ! $hasLock
            && ! $this->hasUncachableAfterQueryCallbacks();
    }

    protected function modelHasRelation(Model $model, string $relation): bool
    {
        return method_exists($model, $relation)
            || $model->isRelation($relation);
    }

    protected function hasUncachableAfterQueryCallbacks(): bool
    {
        $query = property_exists($this, "query")
            ? $this->query
            : null;

        return collect([$query, $query instanceof Builder ? $query->getQuery() : null])
            ->contains(fn ($builder): bool => $this->afterQueryCallbacksOf($builder) !== []);
    }

    protected function afterQueryCallbacksOf(mixed $builder): array
    {
        $declaringClass = collect([Builder::class, QueryBuilder::class])
            ->first(fn (string $class): bool => $builder instanceof $class);

        return $declaringClass
            ? (new ReflectionProperty($declaringClass, "afterQueryCallbacks"))->getValue($builder)
            : [];
    }

    public function shouldFallbackToDatabase(): bool
    {
        return Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.fallback-to-database", false);
    }

    public function isCacheConnectionException(Throwable $exception): bool
    {
        $isListedException = collect(static::$cacheConnectionExceptions)
            ->contains(fn (string $exceptionClass): bool => $exception instanceof $exceptionClass);

        if ($isListedException) {
            return true;
        }

        if (
            class_exists('\Aws\DynamoDb\Exception\DynamoDbException')
            && $exception instanceof DynamoDbException
        ) {
            return $exception->isConnectionError();
        }

        return false;
    }

    public function withCacheFallback(callable $operation, string $context, ?callable $fallback = null): mixed
    {
        static $inFallback = false;

        try {
            return $operation();
        } catch (Throwable $exception) {
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

    protected function setCacheCooldownSavedAtTimestamp(Model $instance): void
    {
        $this->withCacheFallback(function () use ($instance) {
            $cachePrefix = $this->cooldownCachePrefix($instance);
            $modelClassName = get_class($instance);
            $cacheKey = "{$cachePrefix}:{$modelClassName}-cooldown:saved-at";

            $instance->cache()
                ->rememberForever($cacheKey, function () {
                    return (new Carbon)->now();
                });
        }, 'cache cooldown timestamp write failed');
    }
}
