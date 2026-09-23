<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Traits\CachePrefixing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use SplObjectStorage;
use Throwable;

// phpcs:ignore SlevomatCodingStandard.Files.TypeNameMatchesFileName.NoMatchBetweenTypeNameAndFileName,SlevomatCodingStandard.Classes.ClassLength.ClassTooLong
class CacheTags
{
    use CachePrefixing;

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

                return [
                    $this->getCachePrefixForModel($relatedModel)
                        . Str::slug($relatedModel::class),
                    ...$this->getPivotTableTags($relation),
                ];
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

    // An eager-loaded many-to-many relation is cached inside the parent
    // query's entry, so that entry must also carry the pivot table's tag.
    // Without it, a pivot row written outside the relation's own attach/sync
    // methods (a pivot model, or a cachable model on the pivot table) never
    // invalidates the entry and the new member stays missing.
    //
    // A tag only works if it is spelled the way the writer flushes it, and
    // the writer is not known here, so the table is tagged under each prefix
    // a pivot-table write can flush:
    //
    // - the pivot Laravel builds for this relation, which carries the
    //   parent's connection and, for a custom pivot class, that class's own
    //   $cachePrefix. A pivot saved or deleted through the relation flushes
    //   exactly this. For a plain Pivot it has no $cachePrefix, which is also
    //   what a cachable model mapped to the pivot table usually flushes;
    // - the relation's parent, for a writer that shares the parent's prefix.
    //
    // CachedBelongsToMany adds the same tags to the relation's own query,
    // whose entry the rebuilt parent entry reads its related models from.
    public function getPivotTableTags(Relation $relation): array
    {
        if (! $relation instanceof BelongsToMany) {
            return [];
        }

        $table = Str::slug($this->stripTableAlias($relation->getTable()));

        return array_values(array_unique([
            $this->getCachePrefixForModel($relation->newPivot()) . $table,
            $this->getCachePrefixForModel($relation->getParent()) . $table,
        ]));
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

        return array_merge(
            $baseQuery->getJoinedSubqueryTables(),
            $this->getDeferredJoinedSubqueryTables($baseQuery),
        );
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

        $tables = array_merge(
            $tables,
            collect($builder->wheres ?? [])
                ->flatMap(function ($where) use ($seen) {
                    return $this->getSubqueryTablesFromWhere($where, $seen);
                })
                ->toArray(),
        );

        foreach ($builder->joins ?? [] as $join) {
            foreach ($join->wheres ?? [] as $where) {
                $tables = array_merge($tables, $this->getSubqueryTablesFromWhere($where, $seen));
            }
        }

        foreach ($builder->unions ?? [] as $union) {
            $unionQuery = $union['query'] ?? null;

            if (! is_object($unionQuery)) {
                continue;
            }

            $tables = array_merge($tables, $this->getSubqueryTablesFromBuilder($unionQuery, $seen));
        }

        return $tables;
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

        return array_merge($tables, $this->getSubqueryTablesFromBuilder($query, $seen));
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

                if (! method_exists($carry, $name)) {
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
            if (! method_exists($model, $segment)) {
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

    // phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh,SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    protected function getMorphToTags(MorphTo $relation): array
    {
        $morphMap = Relation::morphMap();

        if ($morphMap) {
            $tags = [];

            foreach ($morphMap as $type) {
                if (! class_exists($type)) {
                    continue;
                }

                $tags[] = $this->getCachePrefix() . (new Str)->slug($type);
            }

            if ($tags) {
                return $tags;
            }
        }

        $morphType = $relation->getMorphType();
        $column = last(explode('.', $morphType));
        $table = $relation->getParent()->getTable();

        $types = $relation->getParent()
            ->newQuery()
            ->getQuery()
            ->select($column)
            ->from($table)
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column)
            ->toArray();

        $tags = [];

        foreach ($types as $type) {
            $resolved = Relation::getMorphedModel($type) ?? $type;

            if (! class_exists($resolved)) {
                continue;
            }

            $tags[] = $this->getCachePrefix() . (new Str)->slug($resolved);
        }

        return $tags;
    }

    protected function getTagName(): string
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
