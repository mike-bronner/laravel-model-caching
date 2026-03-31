<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
trait Buildable
{
    use CachedValueRetrievable;

    public function avg($column)
    {
        if (! $this->isCachable()) {
            return parent::avg($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-avg_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function count($columns = "*")
    {
        if (! $this->isCachable()) {
            return parent::count($columns);
        }

        $cacheKey = $this->makeCacheKey([$columns], null, "-count");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function exists()
    {
        if (! $this->isCachable()) {
            return parent::exists();
        }

        $cacheKey = $this->makeCacheKey(['*'], null, "-exists");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->withCacheFallback(function () {
            $this->modelCacheRepository()->invalidateTags($this->makeCacheTags());
        }, 'cache flush failed during decrement');

        return $this->executeOnInnerOrParent('decrement', [$column, $amount, $extra]);
    }

    public function delete()
    {
        $result = $this->executeOnInnerOrParent('delete', []);

        if ($result) {
            $this->withCacheFallback(function () {
                $this->modelCacheRepository()->invalidateTags($this->makeCacheTags());
            }, 'cache flush failed during delete');
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find($id, $columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::find($id, $columns);
        }

        $idKey = collect($id)
            ->implode('_');
        $preStr = is_array($id)
            ? 'find_list'
            : 'find';
        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns, null, "-{$preStr}_{$idKey}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function first($columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::first($columns);
        }

        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns, null, "-first");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function forceDelete()
    {
        $result = $this->executeOnInnerOrParent('forceDelete', []);

        if ($result) {
            $this->withCacheFallback(function () {
                $this->modelCacheRepository()->invalidateTags($this->makeCacheTags());
            }, 'cache flush failed during forceDelete');
        }

        return $result;
    }

    public function get($columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::get($columns);
        }

        $columns = collect($columns)->toArray();
        $cacheKey = $this->makeCacheKey($columns);

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->withCacheFallback(function () {
            $this->modelCacheRepository()->invalidateTags($this->makeCacheTags());
        }, 'cache flush failed during increment');

        return $this->executeOnInnerOrParent('increment', [$column, $amount, $extra]);
    }

    public function inRandomOrder($seed = '')
    {
        $this->isCachable = false;

        return parent::inRandomOrder($seed);
    }

    public function insert(array $values)
    {
        if (property_exists($this, "model")) {
            $this->checkCooldownAndFlushAfterPersisting($this->model);
        }

        return $this->executeOnInnerOrParent('insert', [$values]);
    }

    public function max($column)
    {
        if (! $this->isCachable()) {
            return parent::max($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-max_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function min($column)
    {
        if (! $this->isCachable()) {
            return parent::min($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-min_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function paginate(
        $perPage = null,
        $columns = ["*"],
        $pageName = "page",
        $page = null,
        $total = null,
    ) {
        if (! $this->isCachable()) {
            return parent::paginate($perPage, $columns, $pageName, $page);
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        if (is_array($page)) {
            $page = $this->recursiveImplodeWithKey($page);
        }

        $columns = collect($columns)->toArray();
        $keyDifferentiator = "-paginate_by_{$perPage}_{$pageName}_{$page}";

        if ($total !== null) {
            $total = value($total);
            $keyDifferentiator .= $total !== null
                ? "_{$total}"
                : "";
        }

        $cacheKey = $this->makeCacheKey($columns, null, $keyDifferentiator);

        $result = $this->cachedValue(func_get_args(), $cacheKey);

        if ($result instanceof AbstractPaginator) {
            $result->setPath(Paginator::resolveCurrentPath());
        }

        return $result;
    }

    protected function recursiveImplodeWithKey(array $items, string $glue = "_"): string
    {
        $result = "";

        foreach ($items as $key => $value) {
            $result .= $glue . $key . $glue . $value;
        }

        return $result;
    }

    public function pluck($column, $key = null)
    {
        if (! $this->isCachable()) {
            return parent::pluck($column, $key);
        }

        $keyDifferentiator = "-pluck_{$column}" . ($key ? "_{$key}" : "");
        $cacheKey = $this->makeCacheKey([$column], null, $keyDifferentiator);

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function sum($column)
    {
        if (! $this->isCachable()) {
            return parent::sum($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-sum_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function update(array $values)
    {
        if (property_exists($this, "model")) {
            $this->checkCooldownAndFlushAfterPersisting($this->model);
        }

        return $this->executeOnInnerOrParent('update', [$values]);
    }

    public function value($column)
    {
        if (! $this->isCachable()) {
            return parent::value($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-value_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

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
            function () use ($arguments, $method) {
                return $this->executeOnInnerOrParent($method, $arguments);
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

        return $this->retrieveCachedValue(
            $arguments,
            $cacheKey,
            $cacheTags,
            $method,
        );
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

        $closureRan = false;

        $result = $this->rememberModelCacheForever(
            $cacheKey,
            $cacheTags,
            function () use ($arguments, $cacheKey, $method, &$closureRan) {
                $closureRan = true;

                return [
                    "key" => $cacheKey,
                    "value" => $this->executeOnInnerOrParent($method, $arguments),
                ];
            },
            true,
        );

        if (! $closureRan) {
            $this->fireRetrievedEvents($result["value"] ?? null);
        }

        return $result;
    }

    protected function fireRetrievedEvents($value): void
    {
        $dispatcher = Model::getEventDispatcher();

        if (! $dispatcher) {
            return;
        }

        $models = [];

        if ($value instanceof Model) {
            $models = [$value];
        } elseif ($value instanceof Collection || $value instanceof AbstractPaginator) {
            $models = $value->filter(fn ($item) => $item instanceof Model);
        }

        foreach ($models as $model) {
            $dispatcher->dispatch("eloquent.retrieved: " . get_class($model), $model);
        }
    }

    protected function executeOnInnerOrParent(string $method, array $arguments)
    {
        if (property_exists($this, 'innerBuilder') && $this->innerBuilder) {
            $this->syncStateToInner();

            return $this->innerBuilder->{$method}(...$arguments);
        }

        return parent::{$method}(...$arguments);
    }
}
