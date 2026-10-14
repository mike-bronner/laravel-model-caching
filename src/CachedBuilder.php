<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use Closure;
use GeneaLabs\LaravelModelCaching\Traits\Buildable;
use GeneaLabs\LaravelModelCaching\Traits\BuilderCaching;
use GeneaLabs\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ReflectionClass;

class CachedBuilder extends Builder
{
    use Buildable {
        Buildable::storedValue as storedValueBeforeCallbacks;
    }
    use BuilderCaching;
    use Caching;

    protected ?Builder $innerBuilder = null;
    private static ?ReflectionClass $builderReflection = null;

    public function __construct(QueryBuilder $query)
    {
        parent::__construct(CachedQueryBuilder::fromBase($query));
    }

    public function setInnerBuilder(Builder $builder): static
    {
        $this->innerBuilder = $builder;

        if (! $builder->getQuery() instanceof CachedQueryBuilder) {
            CachedQueryBuilder::recorderFor($builder->getQuery())
                ->recordSubqueryCallsOf($builder);
        }

        return $this;
    }

    public function getInnerBuilder(): ?Builder
    {
        return $this->innerBuilder;
    }

    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        $result = $this->executeOnInnerOrParent('upsert', [$values, $uniqueBy, $update]);
        $this->flushCacheAfterBuilderWrite('upsert');

        return $result;
    }

    public function touch($column = null): int|false
    {
        $result = $this->executeOnInnerOrParent('touch', [$column]);
        $this->flushCacheAfterBuilderWrite('touch');

        return $result;
    }

    public function incrementEach(array $columns, array $extra = []): int|Builder
    {
        $result = $this->executeOnInnerOrParent('incrementEach', [$columns, $extra]);
        $this->flushCacheAfterBuilderWrite('incrementEach');

        return $result;
    }

    public function decrementEach(array $columns, array $extra = []): int|Builder
    {
        $result = $this->executeOnInnerOrParent('decrementEach', [$columns, $extra]);
        $this->flushCacheAfterBuilderWrite('decrementEach');

        return $result;
    }

    public function setModel(Model $model): static
    {
        $this->innerBuilder?->setModel($model);

        return parent::setModel($model);
    }

    protected function replayAfterQueryCallbacks(string $method, mixed $result): mixed
    {
        return match ($method) {
            'get', 'pluck' => $this->applyAfterQueryCallbacks($result),
            'paginate' => $result->setCollection(
                    $this->applyAfterQueryCallbacks($result->getCollection()),
                ),
            'first' => $this->replayAfterQueryCallbacksOnModel($result),
            'find' => match (is_iterable($result)) {
                true => $this->applyAfterQueryCallbacks($result),
                false => $this->replayAfterQueryCallbacksOnModel($result),
            },
            default => $result,
        };
    }

    protected function replayAfterQueryCallbacksOnModel(?Model $model): ?Model
    {
        $models = $this->getModel()
            ->newCollection(collect([$model])->filter()->all());

        return $this->applyAfterQueryCallbacks($models)
            ->first();
    }

    protected function storedValue(string $method, array $arguments, string $cacheKey): mixed
    {
        $callbacks = $this->afterQueryCallbacks;
        $this->afterQueryCallbacks = [];

        try {
            $result = $this->storedValueBeforeCallbacks($method, $arguments, $cacheKey);
        } finally {
            $this->afterQueryCallbacks = $callbacks;
        }

        return $this->replayAfterQueryCallbacks($method, $result);
    }

    protected function syncStateToInner(): void
    {
        if (! $this->innerBuilder) {
            return;
        }

        $this->innerBuilder->setEagerLoads($this->getEagerLoads());
        $this->innerBuilder->pendingAttributes = $this->pendingAttributes;

        if (! self::$builderReflection) {
            self::$builderReflection = new ReflectionClass(Builder::class);
        }

        collect(['scopes', 'removedScopes', 'afterQueryCallbacks'])
            ->each(function (string $prop): void {
                $p = self::$builderReflection->getProperty($prop);
                $p->setValue($this->innerBuilder, $p->getValue($this));
            });
    }

    public function __clone(): void
    {
        if ($this->innerBuilder) {
            $this->innerBuilder = clone $this->innerBuilder;
        }

        $query = $this->query;
        parent::__clone();
        CachedQueryBuilder::recorderFor($this->query, $query);
        $clonedQuery = $this->query;
        $innerBuilder = $this->innerBuilder;
        $innerBuilder?->setQuery($clonedQuery);
    }

    protected function getRelationWithoutConstraints($relation): mixed
    {
        $resolved = parent::getRelationWithoutConstraints($relation);

        $this->recordRelatedSubqueryTables($resolved);

        return $resolved;
    }

    public function has($relation, $operator = ">=", $count = 1, $boolean = "and", ?Closure $callback = null): static
    {
        if (! is_string($relation) || str_contains($relation, ".")) {
            $this->recordRelatedSubqueryTables($relation);
        }

        return parent::has($relation, $operator, $count, $boolean, $callback);
    }

    protected function recordRelatedSubqueryTables($relation): void
    {
        $query = $this->getQuery();

        if (! method_exists($query, "recordRelatedSubqueryTable")) {
            return;
        }

        if ($relation instanceof Relation) {
            $segments = [$relation];
        } elseif (is_string($relation)) {
            $segments = explode(".", $relation);
        } else {
            return;
        }

        $model = $this->getModel();

        foreach ($segments as $segment) {
            if (is_string($segment)) {
                if (! method_exists($model, $segment)) {
                    return;
                }

                $segment = Relation::noConstraints(
                    fn () => $model->{$segment}(),
                );
            }

            if (! $segment instanceof Relation || $segment instanceof MorphTo) {
                return;
            }

            $model = $segment->getRelated();
            $query->recordRelatedSubqueryTable($model->getTable(), $model);
        }
    }
}
