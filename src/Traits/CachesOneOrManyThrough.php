<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\CacheTags;
use GeneaLabs\LaravelModelCaching\Exceptions\UnkeyableValueException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

trait CachesOneOrManyThrough
{
    use CachedValueRetrievable {
        CachedValueRetrievable::retrieveCachedValue as baseRetrieveCachedValue;
    }

    public function get($columns = ['*']): EloquentCollection
    {
        if (! $this->isCachable()) {
            return parent::get($columns);
        }

        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns);

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function first($columns = ['*']): ?Model
    {
        if (! $this->isCachable()) {
            return parent::first($columns);
        }

        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns, null, '-first');

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function find($id, $columns = ['*']): Model|EloquentCollection|null
    {
        if (! $this->isCachable()) {
            return parent::find($id, $columns);
        }

        $idKey = collect($id)->implode('_');
        $preStr = is_array($id) ? 'find_list' : 'find';
        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns, null, "-{$preStr}_{$idKey}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function count($columns = '*'): int
    {
        if (! $this->isCachable()) {
            return parent::count($columns);
        }

        $cacheKey = $this->makeCacheKey([$columns], null, '-count');

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        if (! $this->isCachable()) {
            return parent::paginate($perPage, $columns, $pageName, $page);
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns, null, "-paginate_by_{$perPage}_{$pageName}_{$page}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function pluck($column, $key = null): Collection
    {
        if (! $this->isCachable()) {
            return parent::pluck($column, $key);
        }

        $keyDifferentiator = "-pluck_{$column}" . ($key ? "_{$key}" : '');
        $cacheKey = $this->makeCacheKey([$column], null, $keyDifferentiator);

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function avg($column): mixed
    {
        if (! $this->isCachable()) {
            return parent::avg($column);
        }

        $cacheKey = $this->makeCacheKey(['*'], null, "-avg_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function sum($column): mixed
    {
        if (! $this->isCachable()) {
            return parent::sum($column);
        }

        $cacheKey = $this->makeCacheKey(['*'], null, "-sum_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function min($column): mixed
    {
        if (! $this->isCachable()) {
            return parent::min($column);
        }

        $cacheKey = $this->makeCacheKey(['*'], null, "-min_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function max($column): mixed
    {
        if (! $this->isCachable()) {
            return parent::max($column);
        }

        $cacheKey = $this->makeCacheKey(['*'], null, "-max_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function exists(): bool
    {
        if (! $this->isCachable()) {
            return parent::exists();
        }

        $cacheKey = $this->makeCacheKey(['*'], null, '-exists');

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function value($column): mixed
    {
        if (! $this->isCachable()) {
            return parent::value($column);
        }

        $cacheKey = $this->makeCacheKey(['*'], null, "-value_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
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
            $this->reloadScopedEagerLoads($this->getQuery(), $cachedResult["value"] ?? null);

            return $cachedResult;
        }

        $innerBuilder = $this->getQuery();

        if (method_exists($innerBuilder, 'disableModelCaching')) {
            $innerBuilder->disableModelCaching();
        }

        $result = [
            "key" => $cacheKey,
            "value" => parent::{$method}(...$arguments),
        ];

        $this->putModelCacheValue($cacheKey, $result, $cacheTags, true);

        return $result;
    }

    protected function makeCacheKey(
        array $columns = ['*'],
        $idColumn = null,
        string $keyDifferentiator = '',
    ): string {
        $eagerLoad = $this->getQuery()->getEagerLoads();
        $model = $this->getModel();
        $query = $this->getQuery()->getQuery();

        try {
            return (new CacheKey(
                $eagerLoad,
                $model,
                $query,
                $this->macroKey,
                $this->withoutGlobalScopes,
                $this->withoutAllGlobalScopes,
            ))->make($columns, $idColumn, $keyDifferentiator);
        } catch (UnkeyableValueException) {
            $this->disableModelCaching();

            return "";
        }
    }

    protected function makeCacheTags(): array
    {
        $eagerLoad = $this->getQuery()->getEagerLoads();
        $model = $this->getModel();
        $query = $this->getQuery()->getQuery();

        return (new CacheTags($eagerLoad, $model, $query))->make();
    }
}
