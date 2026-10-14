<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Traits\CachePrefixing;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionProperty;
use SplObjectStorage;
use Throwable;

// phpcs:ignore SlevomatCodingStandard.Files.TypeNameMatchesFileName.NoMatchBetweenTypeNameAndFileName,SlevomatCodingStandard.Classes.ClassLength.ClassTooLong
class CacheTags
{
    use CachePrefixing;

    protected int $morphTypesTtlSeconds = 60;

    protected $eagerLoad;
    protected $model;
    protected $query;

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function __construct(array $eagerLoad, $model, $query) {
        $this->eagerLoad = $eagerLoad;
        $this->model = $model;
        $this->query = $query;
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    public function make(): array
    {
        $tags = collect($this->eagerLoad)
            ->keys()
            ->flatMap(function ($relationName) {
                $morphToTags = $this->getMorphToTagsForRelation($relationName);

                if ($morphToTags !== null) {
                    return $morphToTags;
                }

                $relation = $this->getRelation($relationName);

                if (! $relation) {
                    return [];
                }

                $relatedModel = $relation->getQuery()->getModel();

                return [$this->getCachePrefixForModel($relatedModel)
                    . Str::slug($relatedModel::class)];
            })
            ->filter()
            ->unique()
            ->prepend($this->getTagName())
            ->push($this->getTableTagName())
            ->values();

        $joinTags = $this->getJoinTags();
        $subqueryWhereTags = $this->getSubqueryWhereTags();

        return $tags->merge($joinTags)
            ->merge($subqueryWhereTags)
            ->unique()
            ->values()
            ->toArray();
    }

    public function ttl(): ?int
    {
        $globalTtl = $this->globalTtl();

        return collect($this->eagerLoad)
            ->keys()
            ->flatMap(fn (string $relationName): array => $this->eagerLoadedModels($relationName))
            ->prepend($this->model)
            ->map(fn (Model $model): ?int => $this->modelTtl($model) ?? $globalTtl)
            ->filter()
            ->min();
    }

    protected function globalTtl(): ?int
    {
        $ttl = Container::getInstance()
            ->make("config")
            ->get("laravel-model-caching.ttl");

        return $this->validTtl(
            $ttl,
            "The laravel-model-caching.ttl config value (MODEL_CACHE_TTL)",
        );
    }

    protected function modelTtl(Model $model): ?int
    {
        if (! property_exists($model, "cacheTtlSeconds")) {
            return null;
        }

        $ttl = (new ReflectionProperty($model, "cacheTtlSeconds"))
            ->getValue($model);
        $modelClass = $model::class;

        return $this->validTtl($ttl, "The \$cacheTtlSeconds property of [{$modelClass}]");
    }

    protected function validTtl(mixed $ttl, string $source): ?int
    {
        if (
            $ttl === null
            || $ttl === ""
        ) {
            return null;
        }

        if (
            is_int($ttl)
            && $ttl >= 0
        ) {
            return $ttl;
        }

        if (
            is_string($ttl)
            && ctype_digit($ttl)
        ) {
            return (int) $ttl;
        }

        throw new InvalidArgumentException(
            "{$source} must be a whole number of seconds, 0 or greater. Got " . var_export($ttl, true) . ".",
        );
    }

    protected function eagerLoadedModels(string $relationName): array
    {
        return collect(explode(".", $relationName))
            ->reduce(function (array $models, string $segment): array {
                return collect($models)
                    ->flatMap(fn (Model $model): array => $this->relatedModels($model, $segment))
                    ->all();
            }, [$this->model]);
    }

    protected function relatedModels(Model $model, string $relationName): array
    {
        if (
            ! method_exists($model, $relationName)
            && ! $model->isRelation($relationName)
        ) {
            return [];
        }

        $relation = $model->{$relationName}();

        if ($relation instanceof MorphTo) {
            return collect($this->getMorphToModelClasses($relation))
                ->map(fn (string $class): Model => new $class)
                ->all();
        }

        if (! $relation instanceof Relation) {
            return [];
        }

        return [$relation->getRelated()];
    }

    protected function resolveBaseQuery(): mixed
    {
        if (method_exists($this->query, 'getQuery')) {
            return $this->query->getQuery();
        }

        return $this->query;
    }

    protected function stripTableAlias(string $table): string
    {
        if (stripos($table, ' as ') === false) {
            return $table;
        }

        return trim(explode(' as ', strtolower($table))[0]);
    }

    protected function getJoinTags(): array
    {
        $baseQuery = $this->resolveBaseQuery();

        return $this->makeTableTags(collect($baseQuery->joins ?? [])
            ->map(function ($join) {
                $table = $join->table;

                if (! is_string($table)) {
                    return null;
                }

                return $this->stripTableAlias($table);
            })
            ->merge($this->getJoinedSubqueryTables($baseQuery))
            ->filter(function ($table) {
                return $table !== null;
            })
            ->map(function ($table) {
                return ["table" => $table, "model" => null];
            })
            ->toArray());
    }

    protected function makeTableTags(array $entries): array
    {
        return collect($entries)
            ->map(function (array $entry): string {
                return $this->getCachePrefixForModel($entry["model"] ?: $this->model)
                    . Str::slug($entry["table"]);
            })
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getJoinedSubqueryTables(mixed $baseQuery): array
    {
        if (! method_exists($baseQuery, "getJoinedSubqueryTables")) {
            return [];
        }

        return collect($baseQuery->getJoinedSubqueryTables())
            ->merge($this->getDeferredJoinedSubqueryTables($baseQuery))
            ->all();
    }

    protected function getDeferredJoinedSubqueryTables(mixed $baseQuery): array
    {
        if (
            ! property_exists($baseQuery, "beforeQueryCallbacks")
            || ! $baseQuery->beforeQueryCallbacks
        ) {
            return [];
        }

        try {
            $query = clone $baseQuery;
            $query->applyBeforeQueryCallbacks();

            return $query->getJoinedSubqueryTables();
        } catch (Throwable) {
            return [];
        }
    }

    protected function getSubqueryWhereTags(): array
    {
        $entries = $this->getSubqueryTablesFromBuilder(
            $this->resolveBaseQuery(),
            new SplObjectStorage,
        );
        $modelledTables = collect($entries)
            ->filter(fn (array $entry) => $entry["model"] !== null)
            ->pluck("table")
            ->flip();

        return $this->makeTableTags(collect($entries)
            ->reject(fn (array $entry) => $entry["model"] === null
                && $modelledTables->has($entry["table"]))
            ->values()
            ->toArray());
    }

    // phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh,SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint,SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    protected function getSubqueryTablesFromBuilder($builder, SplObjectStorage $seen): array
    {
        if (
            ! is_object($builder)
            || $seen->offsetExists($builder)
        ) {
            return [];
        }

        $seen->offsetSet($builder);

        $tables = method_exists($builder, "getRelatedSubqueryTables")
            ? $builder->getRelatedSubqueryTables()
            : [];

        return collect($tables)
            ->merge(collect($builder->wheres ?? [])
                ->flatMap(function ($where) use ($seen) {
                    return $this->getSubqueryTablesFromWhere($where, $seen);
                }))
            // A joined table, such as a pivot, is recorded with no model, so its tag takes the
            // querying model's prefix, as in getJoinTags(). A pivot on another connection or
            // with its own $cachePrefix is tagged under a prefix its own writes never flush.
            // This runs on the outer query's joins too, because getSubqueryWhereTags() passes
            // resolveBaseQuery(), not only on joins nested inside a subquery.
            ->merge(collect($builder->joins ?? [])
                ->flatMap(fn ($join) => collect([$join->table ?? null])
                    ->filter(fn ($table) => is_string($table))
                    ->map(fn (string $table) => [
                        "table" => $this->stripTableAlias($table),
                        "model" => null,
                    ])
                    ->merge(collect($join->wheres ?? [])
                        ->flatMap(function ($where) use ($seen) {
                            return $this->getSubqueryTablesFromWhere($where, $seen);
                        }))))
            ->merge(collect([$builder])
                ->filter(fn ($builder) => method_exists($builder, "getJoinedSubqueryTables"))
                ->flatMap(fn ($builder) => $builder->getJoinedSubqueryTables())
                ->map(fn (string $table) => [
                    "table" => $table,
                    "model" => null,
                ]))
            ->merge(collect($builder->unions ?? [])
                ->map(fn ($union) => $union['query'] ?? null)
                ->filter(fn ($unionQuery) => is_object($unionQuery))
                ->flatMap(function ($unionQuery) use ($seen) {
                    return $this->getSubqueryTablesFromBuilder($unionQuery, $seen);
                }))
            ->all();
    }

    protected function getSubqueryTablesFromWhere(array $where, SplObjectStorage $seen): array
    {
        $type = $where['type'] ?? null;

        if (! in_array($type, ['Exists', 'NotExists', 'Nested', 'Sub'], true)) {
            return [];
        }

        $query = $where['query'] ?? null;

        if (! is_object($query)) {
            return [];
        }

        $tables = [];
        $from = $query->from ?? null;

        if (is_string($from)) {
            $tables[] = [
                "model" => null,
                "table" => $this->stripTableAlias($from),
            ];
        }

        return collect($tables)
            ->merge($this->getSubqueryTablesFromBuilder($query, $seen))
            ->all();
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    protected function getRelatedModel($carry): Model
    {
        if ($carry instanceof Relation) {
            return $carry->getQuery()->getModel();
        }

        return $carry;
    }

    // phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    protected function getRelation(string $relationName): ?Relation
    {
        return collect(explode('.', $relationName))
            ->reduce(function ($carry, $name) {
                $carry = $carry ?: $this->model;
                $carry = $this->getRelatedModel($carry);

                if (
                    ! method_exists($carry, $name)
                    && ! $carry->isRelation($name)
                ) {
                    return null;
                }

                $relation = $carry->{$name}();

                if ($relation instanceof MorphTo) {
                    return null;
                }

                return $relation;
            });
    }

    // phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    protected function getMorphToTagsForRelation(string $relationName): ?array
    {
        $segments = explode('.', $relationName);
        $model = $this->model;

        foreach ($segments as $segment) {
            if (
                ! method_exists($model, $segment)
                && ! $model->isRelation($segment)
            ) {
                return null;
            }

            $relation = $model->{$segment}();

            if ($relation instanceof MorphTo) {
                return $this->getMorphToTags($relation);
            }

            if (! ($relation instanceof Relation)) {
                return null;
            }

            $model = $relation->getQuery()->getModel();
        }

        return null;
    }

    protected function getMorphToTags(MorphTo $relation): array
    {
        return collect($this->getMorphToModelClasses($relation))
            ->map(fn (string $class): string => $this->getCachePrefix() . (new Str)->slug($class))
            ->all();
    }

    protected function getMorphToModelClasses(MorphTo $relation): array
    {
        $mappedClasses = collect(Relation::morphMap())
            ->filter(fn ($type) => class_exists($type))
            ->values()
            ->all();

        if ($mappedClasses) {
            return $mappedClasses;
        }

        $morphType = $relation->getMorphType();
        $column = last(explode('.', $morphType));
        $table = $relation->getParent()->getTable();

        return collect($this->getMorphTypesInUse($relation, $table, $column))
            ->map(fn ($type) => Relation::getMorphedModel($type) ?? $type)
            ->filter(fn ($class) => class_exists($class))
            ->values()
            ->all();
    }

    protected function getMorphTypesInUse(MorphTo $relation, string $table, string $column) : array
    {
        $owner = $relation->getParent();

        return ModelCacheRepository::make()->remember(
            $this->getCachePrefixForModel($owner) . "morph-types:" . $table . ":" . $column,
            $this->makeTableTags([["table" => $table, "model" => $owner]]),
            $this->morphTypesTtlSeconds,
            static function () use ($relation, $table, $column) : array {
                return $relation->getParent()
                    ->newQuery()
                    ->getQuery()
                    ->select($column)
                    ->from($table)
                    ->whereNotNull($column)
                    ->distinct()
                    ->pluck($column)
                    ->toArray();
            },
        );
    }

    protected function getTagName() : string
    {
        return $this->getCachePrefix()
            . (new Str)->slug(get_class($this->model));
    }

    protected function getTableTagName(): string
    {
        return $this->getCachePrefix()
            . (new Str)->slug($this->model->getTable());
    }
}
